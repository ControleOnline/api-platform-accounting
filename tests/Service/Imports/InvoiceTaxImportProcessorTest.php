<?php

namespace ControleOnline\Tests\Service\Imports;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\FileService;
use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use ControleOnline\Service\Imports\InvoiceTaxPartyResolver;
use ControleOnline\Service\Imports\InvoiceTaxXmlParser;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class InvoiceTaxImportProcessorTest extends TestCase
{
    public function testParseNfeXmlExtractsInvoicePeopleAndTotals(): void
    {
        $parsed = (new InvoiceTaxXmlParser())->parseNfeXml($this->nfeXml());

        self::assertIsArray($parsed);
        self::assertSame('35240112345678000190550010000001231000001234', $parsed['key']);
        self::assertSame('123', $parsed['number']);
        self::assertSame('55', $parsed['model']);
        self::assertSame('99.90', $parsed['total']);
        self::assertSame('12345678000190', $parsed['provider']['document']);
        self::assertSame('Fornecedor LTDA', $parsed['provider']['name']);
        self::assertSame('01001000', $parsed['provider']['address']['postalCode']);
        self::assertSame('12345678901', $parsed['client']['document']);
        self::assertSame('Cliente Teste', $parsed['client']['name']);
        self::assertSame('99888777000166', $parsed['carrier']['document']);
        self::assertSame('Transportadora', $parsed['carrier']['name']);
    }

    public function testExtractXmlEntriesAcceptsSingleXmlFile(): void
    {
        $entries = (new InvoiceTaxXmlParser())->extractXmlEntries($this->nfeXml(), 'nota.xml');

        self::assertCount(1, $entries);
        self::assertSame('nota.xml', $entries[0]['name']);
        self::assertStringContainsString('<nfeProc', $entries[0]['content']);
    }

    public function testExtractXmlEntriesAcceptsZipWithXmlFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is not available.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'nfe_test_');
        self::assertIsString($zipPath);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::OVERWRITE));
        $zip->addFromString('ignored.txt', 'ignore me');
        $zip->addFromString('folder/nota.xml', $this->nfeXml());
        $zip->close();

        try {
            $entries = (new InvoiceTaxXmlParser())->extractXmlEntries((string) file_get_contents($zipPath), 'notas.zip');
        } finally {
            @unlink($zipPath);
        }

        self::assertCount(1, $entries);
        self::assertSame('nota.xml', $entries[0]['name']);
        self::assertStringContainsString('<nfeProc', $entries[0]['content']);
    }

    public function testFindInvoiceTaxDoesNotTreatForeignCompanyRowAsReusable(): void
    {
        if (!$this->canRunTenantTests()) {
            self::markTestSkipped('People/FileService/StatusService not available in this package checkout.');
        }

        $ownerA = $this->people(1);
        $ownerB = $this->people(2);
        $existing = new InvoiceTax();
        $existing->setCompany($ownerA);
        $existing->setInvoiceKey('35240112345678000190550010000001231000001234');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['invoiceKey' => '35240112345678000190550010000001231000001234'])
            ->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $processor = $this->processor($em);

        $found = $processor->findInvoiceTax($ownerB, '35240112345678000190550010000001231000001234', 123);

        self::assertSame($existing, $found);
        self::assertTrue($processor->belongsToOtherCompany($found, $ownerB));
        self::assertFalse($processor->belongsToOtherCompany($found, $ownerA));
    }

    public function testImportXmlContentRejectsCrossTenantReuse(): void
    {
        if (!$this->canRunTenantTests()) {
            self::markTestSkipped('People/FileService/StatusService not available in this package checkout.');
        }

        $ownerA = $this->people(1);
        $ownerB = $this->people(2);
        $existing = new InvoiceTax();
        $existing->setCompany($ownerA);
        $existing->setInvoiceKey('35240112345678000190550010000001231000001234');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $processor = $this->processor($em);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outra empresa');
        $processor->importXmlContent($ownerB, 'nota.xml', $this->nfeXml());
    }

    public function testFindInvoiceTaxReusesSameCompanyRow(): void
    {
        if (!$this->canRunTenantTests()) {
            self::markTestSkipped('People/FileService/StatusService not available in this package checkout.');
        }

        $owner = $this->people(9);
        $existing = new InvoiceTax();
        $existing->setCompany($owner);
        $existing->setInvoiceKey('35240112345678000190550010000001231000001234');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['invoiceKey' => '35240112345678000190550010000001231000001234'])
            ->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $found = $this->processor($em)->findInvoiceTax(
            $owner,
            '35240112345678000190550010000001231000001234',
            123
        );

        self::assertSame($existing, $found);
    }

    private function canRunTenantTests(): bool
    {
        return class_exists(People::class)
            && class_exists(FileService::class)
            && class_exists(StatusService::class)
            && class_exists(EntityRepository::class);
    }

    private function processor(EntityManagerInterface $em): InvoiceTaxImportProcessor
    {
        return new InvoiceTaxImportProcessor(
            $em,
            $this->createMock(FileService::class),
            $this->createMock(StatusService::class),
            new InvoiceTaxXmlParser(),
            new InvoiceTaxPartyResolver($em)
        );
    }

    private function people(int $id): People
    {
        $people = new People();
        $ref = new \ReflectionProperty(People::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($people, $id);

        return $people;
    }

    private function nfeXml(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe35240112345678000190550010000001231000001234">
      <ide>
        <mod>55</mod>
        <nNF>123</nNF>
      </ide>
      <emit>
        <CNPJ>12345678000190</CNPJ>
        <xNome>Fornecedor LTDA</xNome>
        <IE>123</IE>
        <enderEmit>
          <xLgr>Rua A</xLgr>
          <nro>10</nro>
          <xBairro>Centro</xBairro>
          <cMun>3550308</cMun>
          <xMun>Sao Paulo</xMun>
          <UF>SP</UF>
          <CEP>01001000</CEP>
        </enderEmit>
      </emit>
      <dest>
        <CPF>12345678901</CPF>
        <xNome>Cliente Teste</xNome>
        <enderDest>
          <xLgr>Rua B</xLgr>
          <nro>20</nro>
          <xBairro>Bairro</xBairro>
          <xMun>Sao Paulo</xMun>
          <UF>SP</UF>
          <CEP>01002000</CEP>
        </enderDest>
      </dest>
      <transp>
        <transporta>
          <CNPJ>99888777000166</CNPJ>
          <xNome>Transportadora</xNome>
          <xEnder>Rua C</xEnder>
          <xMun>Sao Paulo</xMun>
          <UF>SP</UF>
        </transporta>
      </transp>
      <total>
        <ICMSTot>
          <vNF>99.90</vNF>
        </ICMSTot>
      </total>
    </infNFe>
  </NFe>
  <protNFe>
    <infProt>
      <chNFe>35240112345678000190550010000001231000001234</chNFe>
    </infProt>
  </protNFe>
</nfeProc>
XML;
    }
}
