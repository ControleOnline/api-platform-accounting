<?php

namespace ControleOnline\Entity {
    if (!class_exists(Integration::class, false) && !class_exists(Integration::class)) {
        class Integration
        {
            public function getBody(): ?string
            {
                return null;
            }
        }
    }
}

namespace ControleOnline\Tests\Service {

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTask;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Service\Cte\CteEmissionProcessor;
use ControleOnline\Service\CteEmissionService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class CteEmissionServiceTest extends TestCase
{
    public function testIntegrateThrowsWhenPayloadIncomplete(): void
    {
        $processor = $this->createMock(CteEmissionProcessor::class);
        $processor->expects(self::never())->method('processTask');

        $manager = $this->createMock(EntityManagerInterface::class);
        $statusService = $this->createMock(StatusService::class);

        $service = new CteEmissionService($processor, $manager, $statusService);

        $integration = $this->createMock(Integration::class);
        $integration->method('getBody')->willReturn(json_encode(['foo' => 'bar']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payload CteEmission incompleto');
        $service->integrate($integration);
    }

    public function testIntegrateDelegatesExistingInvoiceTask(): void
    {
        $task = new InvoiceTask();
        $expected = new InvoiceTax();
        $expected->setInvoiceModel(57);

        $processor = $this->createMock(CteEmissionProcessor::class);
        $processor->expects(self::once())->method('processTask')->with($task)->willReturn($expected);

        $taskRepo = $this->createMock(EntityRepository::class);
        $taskRepo->method('find')->with(42)->willReturn($task);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(InvoiceTask::class)->willReturn($taskRepo);

        $service = new CteEmissionService(
            $processor,
            $manager,
            $this->createMock(StatusService::class)
        );

        $integration = $this->createMock(Integration::class);
        $integration->method('getBody')->willReturn(json_encode(['invoiceTaskId' => 42]));

        $result = $service->integrate($integration);
        self::assertSame($expected, $result);
        self::assertSame(57, $result->getInvoiceModel());
    }

    public function testIntegrateCreatesTaskFromLegacyIdsAndDelegates(): void
    {
        $company = new People();
        $nfA = new InvoiceTax();
        $nfA->setInvoiceTotal('10.00');
        $nfA->setCompany($company);
        $nfB = new InvoiceTax();
        $nfB->setInvoiceTotal('5.50');
        $nfB->setCompany($company);

        $pending = new Status();
        if (method_exists($pending, 'setRealStatus')) {
            $pending->setRealStatus('pending');
        }

        $expected = new InvoiceTax();
        $expected->setInvoiceModel(57);

        $processor = $this->createMock(CteEmissionProcessor::class);
        $processor->expects(self::once())
            ->method('processTask')
            ->willReturnCallback(function (InvoiceTask $task) use ($expected) {
                self::assertSame('cte_emission', $task->getTaskType());
                self::assertSame('5353', $task->getCfop());
                $payload = json_decode((string) $task->getPayload(), true);
                self::assertSame([10, 11], $payload['invoiceTaxIds']);
                self::assertSame('5353', $payload['cfop']);
                return $expected;
            });

        $taxRepo = $this->createMock(EntityRepository::class);
        $taxRepo->method('findBy')->with(['id' => [10, 11]])->willReturn([$nfA, $nfB]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(InvoiceTax::class)->willReturn($taxRepo);
        $manager->expects(self::once())->method('persist')->with(self::isInstanceOf(InvoiceTask::class));
        $manager->expects(self::once())->method('flush');

        $statusService = $this->createMock(StatusService::class);
        $statusService->method('discoveryStatus')->with('pending', 'pending', 'invoice_task')->willReturn($pending);

        $service = new CteEmissionService($processor, $manager, $statusService);

        $integration = $this->createMock(Integration::class);
        $integration->method('getBody')->willReturn(json_encode([
            'invoiceTaxIds' => [10, 11],
            'cfop' => '5353',
            'extra' => ['origem' => 'ui'],
        ]));

        $result = $service->integrate($integration);
        self::assertSame($expected, $result);
    }

    public function testIntegrateThrowsWhenLegacyNfsMissing(): void
    {
        $processor = $this->createMock(CteEmissionProcessor::class);
        $processor->expects(self::never())->method('processTask');

        $taxRepo = $this->createMock(EntityRepository::class);
        $taxRepo->method('findBy')->willReturn([new InvoiceTax()]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($taxRepo);

        $service = new CteEmissionService(
            $processor,
            $manager,
            $this->createMock(StatusService::class)
        );

        $integration = $this->createMock(Integration::class);
        $integration->method('getBody')->willReturn(json_encode([
            'selected' => [1, 2],
            'cfop' => '5353',
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NFs da integração CteEmission não encontradas');
        $service->integrate($integration);
    }
}
}
