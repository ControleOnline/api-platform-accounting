<?php

namespace ControleOnline\Tests\Service\Imports;

use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZipArchive;

final class InvoiceTaxImportProcessorTest extends TestCase
{
    public function testParseNfeXmlExtractsInvoicePeopleAndTotals(): void
    {
        $parsed = $this->processor()->parseNfeXml($this->nfeXml());

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
        $entries = $this->extractXmlEntries($this->nfeXml(), 'nota.xml');

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
            $entries = $this->extractXmlEntries((string) file_get_contents($zipPath), 'notas.zip');
        } finally {
            @unlink($zipPath);
        }

        self::assertCount(1, $entries);
        self::assertSame('nota.xml', $entries[0]['name']);
        self::assertStringContainsString('<nfeProc', $entries[0]['content']);
    }

    private function processor(): InvoiceTaxImportProcessor
    {
        $reflection = new ReflectionClass(InvoiceTaxImportProcessor::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @return array<int, array{name: string, content: string}>
     */
    private function extractXmlEntries(string $content, string $fallbackName): array
    {
        $reflection = new ReflectionClass(InvoiceTaxImportProcessor::class);
        $method = $reflection->getMethod('extractXmlEntries');
        $method->setAccessible(true);

        return $method->invoke($this->processor(), $content, $fallbackName);
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
