<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\InvoicesWithoutCteService;
use Doctrine\DBAL\Connection;
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

        self::assertSame('6353', $data['cteDefaults']['cfop']);
        self::assertSame('0', $data['cteDefaults']['tomador']);
        self::assertSame('42.50', $data['cteDefaults']['valorFrete']);
        self::assertSame('42.50', $data['cteDefaults']['valorReceber']);
        self::assertContains('cfop', $data['cteReadonlyFields']);
        self::assertContains('tomador', $data['cteReadonlyFields']);
        self::assertContains('valorFrete', $data['cteReadonlyFields']);
        self::assertContains('valorReceber', $data['cteReadonlyFields']);
    }

    public function testListDoesNotReadAnUndefinedXmlVariable(): void
    {
        $sqlSeen = null;
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql) {
                if (str_starts_with($sql, 'SHOW COLUMNS')) {
                    return [
                        'id', 'invoice_number', 'invoice_key', 'invoice_model', 'invoice_total',
                        'cte_id', 'integration_id', 'issuer_id', 'company_id', 'client_id',
                        'provider_id', 'carrier_id', 'address_id',
                    ];
                }

                return [];
            }
        );
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql) use (&$sqlSeen) {
                $sqlSeen = $sql;

                return [];
            }
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $result = (new InvoicesWithoutCteService($entityManager))->list();

        self::assertSame([], $result['member']);
        self::assertSame(0, $result['totalItems']);
        self::assertIsString($sqlSeen);
        self::assertStringContainsString('it.cte_id IS NULL', (string) $sqlSeen);
        self::assertStringContainsString('it.integration_id IS NULL', (string) $sqlSeen);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty(InvoiceTax::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
