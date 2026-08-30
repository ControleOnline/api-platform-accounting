<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTask;
use ControleOnline\Entity\InvoiceTax;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmitCteService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private StatusService $statusService,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function emit(array $invoiceTaxIds, string $cfop, array $extra = []): InvoiceTask
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $invoiceTaxIds))));
        if (count($ids) < 1) {
            throw new BadRequestHttpException('Selecione ao menos uma NF para emitir o CT-e.');
        }
        if (trim($cfop) === '') {
            throw new BadRequestHttpException('CFOP é obrigatório.');
        }

        $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
        if (count($invoices) !== count($ids)) {
            throw new BadRequestHttpException('Uma ou mais NFs não foram encontradas.');
        }

        $busy = $this->manager->getConnection()->fetchFirstColumn(
            'SELECT id FROM invoice_tax WHERE id IN (?) AND invoice_task_id IS NOT NULL',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        if ($busy) {
            throw new BadRequestHttpException('Uma ou mais NFs já estão em emissão ou emitidas.');
        }

        $first = $invoices[0];
        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) ($invoice->getInvoiceTotal() ?? 0);
        }

        $status = $this->statusService->discoveryStatus('pending', 'pending', 'invoice_task');
        $task = new InvoiceTask();
        $task->setTaskType('cte_emission');
        $task->setStatus($status);
        $task->setCompany($first->getCompany());
        $task->setAddress($first->getAddress());
        $task->setCfop($cfop);
        $task->setInvoiceTotal(number_format($total, 2, '.', ''));
        $task->setPayload(json_encode([
            'invoiceTaxIds' => $ids,
            'cfop' => $cfop,
            'extra' => $extra,
            'companyId' => $first->getCompany()?->getId(),
            'addressId' => $first->getAddress()?->getId(),
            'invoiceTotal' => $total,
        ], JSON_UNESCAPED_UNICODE));
        $this->manager->persist($task);
        $this->manager->flush();

        $this->manager->getConnection()->executeStatement(
            'UPDATE invoice_tax SET invoice_task_id = ? WHERE id IN (?) AND invoice_task_id IS NULL',
            [$task->getId(), $ids],
            [\PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );

        $this->enqueue($task, $ids, $cfop, $extra);
        $this->manager->flush();

        return $task;
    }

    private function enqueue(InvoiceTask $task, array $ids, string $cfop, array $extra): void
    {
        if (!class_exists(Integration::class)) {
            return;
        }

        $queueStatus = $this->statusService->discoveryStatus('open', 'open', 'integration');
        $integration = new Integration();
        $integration->setQueueName('cte_emission');
        $integration->setStatus($queueStatus);
        $integration->setBody(json_encode([
            'invoiceTaskId' => $task->getId(),
            'invoiceTaxIds' => $ids,
            'cfop' => $cfop,
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE));
        $user = $this->tokenStorage->getToken()?->getUser();
        if (is_object($user) && method_exists($integration, 'setUser')) {
            $integration->setUser($user);
        }
        $this->manager->persist($integration);
    }
}
