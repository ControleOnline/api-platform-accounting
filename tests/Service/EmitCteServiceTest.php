<?php

namespace ControleOnline\Entity {
    if (!class_exists(Integration::class, false) && !class_exists(Integration::class)) {
        class Integration
        {
            public function getId(): int
            {
                return 662166;
            }
        }
    }
}

namespace ControleOnline\Service {
    if (!class_exists(IntegrationService::class, false) && !class_exists(IntegrationService::class)) {
        class IntegrationService
        {
            public function addIntegration($payload, $queue, $status, $user)
            {
                return null;
            }
        }
    }

    if (!class_exists(StatusService::class, false) && !class_exists(StatusService::class)) {
        class StatusService
        {
        }
    }
}

namespace ControleOnline\Tests\Service {

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\User;
use ControleOnline\Service\EmitCteService;
use ControleOnline\Service\IntegrationService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class EmitCteServiceTest extends TestCase
{
    public function testEmitBindsSelectedInvoicesToCreatedTaskBeforeSefaz(): void
    {
        $invoiceA = new InvoiceTax();
        $invoiceA->setInvoiceNumber(101);
        $invoiceB = new InvoiceTax();
        $invoiceB->setInvoiceNumber(102);
        $this->setId($invoiceA, 10);
        $this->setId($invoiceB, 11);

        $task = new Integration();
        $manager = $this->entityManager($invoiceA, $invoiceB, busy: null);
        $persisted = [];
        $manager->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            }
        );
        $manager->expects(self::once())->method('flush');

        $integrationService = $this->createMock(IntegrationService::class);
        $integrationService->expects(self::once())
            ->method('addIntegration')
            ->willReturn($task);

        $service = new EmitCteService(
            $manager,
            $this->createMock(StatusService::class),
            $this->superTokenStorage(),
            $integrationService
        );

        $returned = $service->emit([10, 11], '5353');

        self::assertSame($task, $returned);
        self::assertSame($task, $invoiceA->getIntegration());
        self::assertSame($invoiceA, $persisted[0] ?? null);
        self::assertSame($task, $invoiceB->getIntegration());
        self::assertSame($invoiceB, $persisted[1] ?? null);
    }

    public function testEmitRejectsWhenInvoiceAlreadyHasTask(): void
    {
        $invoice = new InvoiceTax();
        $invoice->setInvoiceNumber(101);
        $this->setId($invoice, 10);

        $service = new EmitCteService(
            $this->entityManager($invoice, busy: ['id' => 10]),
            $this->createMock(StatusService::class),
            $this->superTokenStorage(),
            $this->createMock(IntegrationService::class)
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('já estão em emissão ou emitidas');
        $service->emit([10], '5353');
    }

    public function testEmitRejectsWhenInvoiceAlreadyHasCte(): void
    {
        $cte = new InvoiceTax();
        $invoice = new InvoiceTax();
        $invoice->setInvoiceNumber(101);
        $invoice->setCte($cte);
        $this->setId($invoice, 10);

        $integrationService = $this->createMock(IntegrationService::class);
        $integrationService->expects(self::never())->method('addIntegration');

        $service = new EmitCteService(
            $this->entityManager($invoice, busy: null),
            $this->createMock(StatusService::class),
            $this->superTokenStorage(),
            $integrationService
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('já possui CT-e vinculado');
        $service->emit([10], '5353');
    }

    public function testEmitRequiresAtLeastOneInvoiceAndCfop(): void
    {
        $service = new EmitCteService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(StatusService::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(IntegrationService::class)
        );

        try {
            $service->emit([], '5353');
            self::fail('empty list should fail');
        } catch (BadRequestHttpException $e) {
            self::assertStringContainsString('ao menos uma NF', $e->getMessage());
        }

        try {
            $service->emit([10], '  ');
            self::fail('blank cfop should fail');
        } catch (BadRequestHttpException $e) {
            self::assertStringContainsString('CFOP', $e->getMessage());
        }
    }

    private function entityManager(InvoiceTax $first, ?InvoiceTax $second = null, mixed $busy = null): EntityManagerInterface
    {
        $invoices = $second ? [$first, $second] : [$first];
        $ids = array_map(fn(InvoiceTax $invoice) => (int) $invoice->getId(), $invoices);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->with(['id' => $ids])->willReturn($invoices);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($busy);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(InvoiceTax::class)->willReturn($repo);
        $manager->method('createQueryBuilder')->willReturn($qb);

        return $manager;
    }

    private function superTokenStorage(): TokenStorageInterface
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_SUPER']);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty(InvoiceTax::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
}
