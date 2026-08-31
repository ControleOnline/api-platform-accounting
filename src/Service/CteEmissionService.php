<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTask;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\Cte\CteEmissionProcessor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handler do messenger quando queueName = "CteEmission" (legado).
 * Delega ao fluxo real do CteEmissionProcessor; nunca inventa Closed/XML.
 */
class CteEmissionService
{
    public function __construct(
        private CteEmissionProcessor $processor,
        private EntityManagerInterface $manager,
        private StatusService $statusService,
    ) {
    }

    public function integrate(Integration $integration): InvoiceTax
    {
        $body = json_decode((string) $integration->getBody(), true) ?: [];
        $taskId = (int) ($body['invoiceTaskId'] ?? 0);
        if ($taskId > 0) {
            $task = $this->manager->getRepository(InvoiceTask::class)->find($taskId);
            if ($task instanceof InvoiceTask) {
                return $this->processor->processTask($task);
            }
        }

        // Legado: body carrega ids/cfop sem InvoiceTask pré-criado
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            $body['invoiceTaxIds'] ?? $body['selected'] ?? []
        ))));
        $cfop = (string) ($body['cfop'] ?? '');
        $extra = is_array($body['extra'] ?? null) ? $body['extra'] : [];
        if ($ids === [] || $cfop === '') {
            throw new \RuntimeException('Payload CteEmission incompleto (invoiceTaskId ou ids/CFOP).');
        }

        $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
        if (count($invoices) !== count($ids)) {
            throw new \RuntimeException('NFs da integração CteEmission não encontradas.');
        }

        $first = $invoices[0];
        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) ($invoice->getInvoiceTotal() ?? 0);
        }

        $pending = $this->statusService->discoveryStatus('pending', 'pending', 'invoice_task');
        $task = new InvoiceTask();
        $task->setTaskType('cte_emission');
        $task->setStatus($pending);
        $task->setCompany($first->getCompany());
        $task->setAddress($first->getAddress());
        $task->setCfop($cfop);
        $task->setInvoiceTotal(number_format($total, 2, '.', ''));
        $task->setPayload(json_encode([
            'invoiceTaxIds' => $ids,
            'cfop' => $cfop,
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE));
        $this->manager->persist($task);
        $this->manager->flush();

        return $this->processor->processTask($task);
    }
}
