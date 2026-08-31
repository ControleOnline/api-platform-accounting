<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTask;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

        $this->assertTenantCanEmit($invoices);

        foreach ($invoices as $invoice) {
            if ($invoice->getCte() instanceof InvoiceTax) {
                throw new BadRequestHttpException(sprintf(
                    'NF %s já possui CT-e vinculado.',
                    $invoice->getInvoiceNumber()
                ));
            }
        }

        $busy = $this->manager->getConnection()->fetchFirstColumn(
            'SELECT id FROM invoice_tax WHERE id IN (?) AND invoice_task_id IS NOT NULL',
            [$ids],
            [ArrayParameterType::INTEGER]
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

        // Bind immediately so /invoice_taxes/without-cte excludes NFs while task is still open/pending.
        $updated = $this->manager->getConnection()->executeStatement(
            'UPDATE invoice_tax SET invoice_task_id = ? WHERE id IN (?) AND invoice_task_id IS NULL',
            [$task->getId(), $ids],
            [ParameterType::INTEGER, ArrayParameterType::INTEGER]
        );
        if ((int) $updated < count($ids)) {
            // ORM fallback if column mapping differs or concurrent race
            foreach ($invoices as $invoice) {
                if (method_exists($invoice, 'setIntegration')) {
                    $invoice->setIntegration($task);
                }
                $this->manager->persist($invoice);
            }
            $this->manager->flush();
        }

        $this->enqueue($task, $ids, $cfop, $extra);
        $this->manager->flush();

        return $task;
    }

    private function assertTenantCanEmit(array $invoices): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!is_object($user)) {
            throw new AccessDeniedHttpException('Authentication required.');
        }
        $roles = method_exists($user, 'getRoles') ? (array) $user->getRoles() : [];
        if (in_array('ROLE_SUPER', $roles, true)) {
            return;
        }

        $people = method_exists($user, 'getPeople') ? $user->getPeople() : null;
        $peopleId = $people instanceof People ? (int) $people->getId() : 0;
        $allowed = $peopleId > 0 ? [$peopleId] : [];
        if ($peopleId > 0) {
            try {
                $linked = $this->manager->getConnection()->fetchFirstColumn(
                    'SELECT company_id FROM people_link WHERE people_id = ? AND (enabled = 1 OR enabled IS NULL)',
                    [$peopleId],
                    [ParameterType::INTEGER]
                );
                foreach ($linked ?: [] as $companyId) {
                    $allowed[] = (int) $companyId;
                }
            } catch (\Throwable) {
            }
        }
        $allowed = array_values(array_unique(array_filter($allowed)));
        if ($allowed === []) {
            throw new AccessDeniedHttpException('Sem permissão para emitir CT-e.');
        }

        foreach ($invoices as $invoice) {
            $companyId = (int) ($invoice->getCompany()?->getId() ?? 0);
            $issuerId = (int) ($invoice->getIssuer()?->getId() ?? 0);
            if (!in_array($companyId, $allowed, true) && !in_array($issuerId, $allowed, true)) {
                throw new AccessDeniedHttpException('NF fora do escopo da empresa autenticada.');
            }
        }
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
