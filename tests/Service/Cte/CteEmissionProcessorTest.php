<?php

namespace ControleOnline\Tests\Service\Cte;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Service\Cte\CteEmissionProcessor;
use ControleOnline\Service\Cte\CteFiscalConfig;
use ControleOnline\Service\Cte\CteSefazClient;
use ControleOnline\Service\Cte\CteXmlBuilder;
use ControleOnline\Service\FileService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class CteEmissionProcessorTest extends TestCase
{
    public function testProcessTaskPersistsModel57AndLinksSourceInvoices(): void
    {
        $nfA = $this->invoice(10, '11111111111111111111111111111111111111111111');
        $nfB = $this->invoice(20, '22222222222222222222222222222222222222222222');
        $this->setId($nfA, 10);
        $this->setId($nfB, 11);

        $company = new People();
        $nfA->setCompany($company);
        $nfB->setCompany($company);
        $task = new Integration();
        $task->setQueueName('CteEmission');
        $task->setBody(json_encode([
            'invoiceTaxIds' => [10, 11],
            'cfop' => '5932',
            'extra' => [
                'modal' => '01',
                'tipoServico' => '0',
                'tipoCte' => '0',
                'tomador' => '3',
                'valorFrete' => '30.00',
                'valorReceber' => '30.00',
            ],
        ]));

        $statuses = [];
        $statusService = $this->createMock(StatusService::class);
        $statusService->method('discoveryStatus')->willReturnCallback(
            function (string $real) use (&$statuses) {
                if (!isset($statuses[$real])) {
                    $status = new Status();
                    if (method_exists($status, 'setRealStatus')) {
                        $status->setRealStatus($real);
                    }
                    $statuses[$real] = $status;
                }
                return $statuses[$real];
            }
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$nfA, $nfB]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repo);
        $manager->expects(self::atLeastOnce())->method('persist');
        $manager->expects(self::atLeastOnce())->method('flush');

        $fiscal = $this->createMock(CteFiscalConfig::class);
        $fiscal->method('load')->willReturn([
            'certificateBinary' => 'PKCS12',
            'receita-federal-certificate-password' => 'secret',
            'receita-federal-cte-serie' => '1',
            'receita-federal-environment' => '2',
            'receita-federal-ibge-code' => '3550308',
            'receita-federal-cte-rntrc' => '12345678',
            'nextNumber' => 7,
        ]);
        $fiscal->expects(self::once())->method('incrementLastNumber')->with($company, 7);

        $xml = (new CteXmlBuilder())->build([$nfA, $nfB], [
            'receita-federal-cte-serie' => '1',
            'receita-federal-environment' => '2',
            'receita-federal-ibge-code' => '3550308',
            'receita-federal-cte-rntrc' => '12345678',
            'nextNumber' => 7,
        ], '5932', [
            'modal' => '01',
            'tipoServico' => '0',
            'tipoCte' => '0',
            'tomador' => '3',
            'valorFrete' => '30.00',
            'valorReceber' => '30.00',
        ]);

        $sefaz = $this->createMock(CteSefazClient::class);
        $sefaz->method('signAndSend')->willReturn([
            'xml' => $xml,
            'key' => (new CteXmlBuilder())->extractKey($xml),
            'authorized' => true,
            'raw' => null,
        ]);

        $fileService = $this->createMock(FileService::class);
        $fileService->method('addFile')->willReturn(new \ControleOnline\Entity\File());

        $processor = new CteEmissionProcessor(
            $manager,
            $statusService,
            $fiscal,
            new CteXmlBuilder(),
            $sefaz,
            $fileService
        );

        $cte = $processor->processTask($task);

        self::assertSame(57, $cte->getInvoiceModel());
        self::assertSame(7, $cte->getInvoiceNumber());
        self::assertSame($cte, $nfA->getCte());
        self::assertSame($cte, $nfB->getCte());
        self::assertSame('closed', $task->getStatus()?->getRealStatus());
    }

    public function testProcessTaskMarksErrorWhenCertificateMissing(): void
    {
        $task = new Integration();
        $task->setQueueName('CteEmission');
        $task->setBody(json_encode(['invoiceTaxIds' => [1, 2], 'cfop' => '5353']));

        $statusService = $this->createMock(StatusService::class);
        $statusService->method('discoveryStatus')->willReturnCallback(function (string $real) {
            $status = new Status();
            if (method_exists($status, 'setRealStatus')) {
                $status->setRealStatus($real);
            }
            return $status;
        });

        $nf = $this->invoice(1, '1');
        $this->setId($nf, 1);
        $nf2 = $this->invoice(1, '2');
        $this->setId($nf2, 2);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$nf, $nf2]);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repo);
        $manager->method('persist');
        $manager->method('flush');

        $fiscal = $this->createMock(CteFiscalConfig::class);
        $fiscal->method('load')->willReturn([
            'certificateBinary' => null,
            'receita-federal-certificate-password' => null,
            'nextNumber' => 1,
        ]);

        $processor = new CteEmissionProcessor(
            $manager,
            $statusService,
            $fiscal,
            new CteXmlBuilder(),
            $this->createMock(CteSefazClient::class),
            $this->createMock(FileService::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('certificado');
        $processor->processTask($task);
        self::assertSame('error', $task->getStatus()?->getRealStatus());
    }

    private function invoice(float $total, string $key): InvoiceTax
    {
        $invoice = new InvoiceTax();
        $invoice->setInvoiceKey($key);
        $invoice->setInvoiceNumber(1);
        $invoice->setInvoiceModel(55);
        $invoice->setInvoiceTotal(number_format($total, 2, '.', ''));

        return $invoice;
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
