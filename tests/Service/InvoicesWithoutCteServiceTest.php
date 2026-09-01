<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\InvoicesWithoutCteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class InvoicesWithoutCteServiceTest extends TestCase
{
    public function testSerializeInvoiceExposesInferredCteDefaultsFromNfeXml(): void
    {
        $invoice = new class extends InvoiceTax {
            public function __construct()
            {
                parent::__construct();
                $this->setInvoiceNumber(10);
                $this->setInvoiceKey('35240112345678000190550010000001231000001234');
                $this->setInvoiceModel(55);
                $this->setInvoiceTotal('1268.08');
            }

            public function getInvoice()
            {
                return '<NFe><infNFe><det><prod><CFOP>6152</CFOP></prod></det><transp><modFrete>0</modFrete></transp><total><ICMSTot><vFrete>42.50</vFrete></ICMSTot></total></infNFe></NFe>';
            }
        };
        $this->setId($invoice, 4);

        $service = new InvoicesWithoutCteService($this->createMock(EntityManagerInterface::class));
        $data = $service->serializeInvoice($invoice);

        self::assertSame('6932', $data['cteDefaults']['cfop']);
        self::assertSame('0', $data['cteDefaults']['tomador']);
        self::assertSame('42.50', $data['cteDefaults']['valorFrete']);
        self::assertSame('42.50', $data['cteDefaults']['valorReceber']);
        self::assertContains('cfop', $data['cteReadonlyFields']);
        self::assertContains('tomador', $data['cteReadonlyFields']);
        self::assertContains('valorFrete', $data['cteReadonlyFields']);
        self::assertContains('valorReceber', $data['cteReadonlyFields']);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty(InvoiceTax::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
