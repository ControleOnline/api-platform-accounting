<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\FileService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;

class CteEmissionProcessor
{
    public function __construct(
        private EntityManagerInterface $manager,
        private StatusService $statusService,
        private CteFiscalConfig $fiscalConfig,
        private CteXmlBuilder $xmlBuilder,
        private CteSefazClient $sefazClient,
        private FileService $fileService,
    ) {
    }

    public function processPending(int $limit = 20): int
    {
        $processed = 0;
        $processed += $this->processIntegrations($limit);
        $processed += $this->processTasks($limit);

        return $processed;
    }

    /**
     * Integração criada por EmitCteService (queueName=CteEmission, body JSON).
     * Usado pelo cron tenant:integration:start via CteEmissionService::integrate.
     */
    public function processMessengerIntegration(Integration $integration): InvoiceTax
    {
        $body = json_decode((string) $integration->getBody(), true) ?: [];
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            $body['invoiceTaxIds'] ?? $body['selected'] ?? []
        ))));
        $cfop = (string) ($body['cfop'] ?? '');
        $extra = is_array($body['extra'] ?? null) ? $body['extra'] : [];

        if ($ids === [] || $cfop === '') {
            throw new \RuntimeException('Payload da integração CteEmission incompleto (ids/CFOP).');
        }

        if (method_exists($integration, 'setPayload')) {
            $integration->setPayload(json_encode([
                'invoiceTaxIds' => $ids,
                'cfop' => $cfop,
                'extra' => $extra,
            ], JSON_UNESCAPED_UNICODE));
        }
        if (method_exists($integration, 'setCfop') && $cfop !== '') {
            $integration->setCfop($cfop);
        }
        if (method_exists($integration, 'setTaskType')) {
            $integration->setTaskType('cte_emission');
        }

        return $this->processTask($integration);
    }

    public function processTask(Integration $task): InvoiceTax
    {
        $processing = $this->statusService->discoveryStatus('processing', 'processing', 'invoice_task');
        $task->setStatus($processing);
        $this->manager->persist($task);
        $this->manager->flush();

        try {
            $payloadRaw = '';
            if (method_exists($task, 'getPayload')) {
                $payloadRaw = (string) $task->getPayload();
            }
            if ($payloadRaw === '' && method_exists($task, 'getBody')) {
                $payloadRaw = (string) $task->getBody();
            }
            $payload = json_decode($payloadRaw, true) ?: [];
            $ids = array_values(array_unique(array_filter(array_map(
                'intval',
                $payload['invoiceTaxIds'] ?? $payload['selected'] ?? []
            ))));
            $cfop = (string) ($payload['cfop'] ?? (method_exists($task, 'getCfop') ? ($task->getCfop() ?? '') : '') ?: '');
            $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];
            if ($ids === [] || $cfop === '') {
                throw new \RuntimeException('Payload da invoice_task incompleto (ids/CFOP).');
            }

            $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
            if (count($invoices) !== count($ids)) {
                throw new \RuntimeException('NFs da tarefa não encontradas.');
            }

            $company = null;
            if (method_exists($task, 'getCompany')) {
                $company = $task->getCompany();
            }
            if (!$company instanceof People) {
                $company = $invoices[0]->getCompany() ?: $invoices[0]->getIssuer();
            }
            $fiscal = $this->fiscalConfig->load($company instanceof People ? $company : null);
            if (empty($fiscal['certificateBinary']) || empty($fiscal['receita-federal-certificate-password'])) {
                throw new \RuntimeException('Configuração fiscal incompleta: certificado e senha são obrigatórios.');
            }

            $xml = $this->xmlBuilder->build($invoices, $fiscal, $cfop, $extra);
            $sefaz = $this->sefazClient->signAndSend($xml, $fiscal);
            $authorizedXml = (string) $sefaz['xml'];
            $key = (string) ($sefaz['key'] ?: $this->xmlBuilder->extractKey($authorizedXml));
            $number = $this->xmlBuilder->extractNumber($authorizedXml) ?: (int) $fiscal['nextNumber'];

            $cte = $this->persistCte($company instanceof People ? $company : null, $invoices, $authorizedXml, $key, $number, $task);
            $this->fiscalConfig->incrementLastNumber($company instanceof People ? $company : null, $number);

            $closed = $this->statusService->discoveryStatus('closed', 'closed', 'invoice_task');
            $task->setStatus($closed);
            $this->manager->persist($task);
            $this->manager->flush();

            return $cte;
        } catch (\Throwable $exception) {
            $error = $this->statusService->discoveryStatus('error', 'error', 'invoice_task');
            $task->setStatus($error);
            $errPayload = [];
            if (method_exists($task, 'getPayload')) {
                $errPayload = json_decode((string) $task->getPayload(), true) ?: [];
            }
            if ($errPayload === [] && method_exists($task, 'getBody')) {
                $errPayload = json_decode((string) $task->getBody(), true) ?: [];
            }
            $errPayload['error'] = $exception->getMessage();
            if (method_exists($task, 'setPayload')) {
                $task->setPayload(json_encode($errPayload, JSON_UNESCAPED_UNICODE));
            } elseif (method_exists($task, 'setBody')) {
                $task->setBody(json_encode($errPayload, JSON_UNESCAPED_UNICODE));
            }
            $this->manager->persist($task);
            $this->manager->flush();
            throw $exception;
        }
    }

    private function persistCte(?People $company, array $invoices, string $xml, string $key, int $number, Integration $task): InvoiceTax
    {
        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) ($invoice->getInvoiceTotal() ?? 0);
        }

        $file = $this->fileService->addFile(
            $company,
            $xml,
            'invoice_tax',
            sprintf('cte-%s.xml', $key !== '' ? $key : $number),
            'application',
            'xml',
            false
        );

        $cte = new InvoiceTax();
        $cte->setFile($file);
        $cte->setInvoiceKey($key !== '' ? $key : null);
        $cte->setInvoiceNumber($number);
        $cte->setInvoiceModel(57);
        $cte->setInvoiceTotal(number_format($total, 2, '.', ''));
        $cte->syncFiscalDocumentFieldsFromXml($xml);
        $cte->setCompany($company);
        $cte->setIssuer($company);
        $cte->setIntegration($task);
        $cte->setStatus($this->statusService->discoveryStatus('closed', 'closed', 'invoice_tax'));
        $first = $invoices[0];
        $cte->setProvider($first->getProvider());
        $cte->setClient($first->getClient());
        $cte->setCarrier($first->getCarrier());
        $cte->setAddress($first->getAddress());
        $cte->setProviderAddress($first->getProviderAddress());
        $cte->setClientAddress($first->getClientAddress());
        $cte->setCarrierAddress($first->getCarrierAddress());
        $this->manager->persist($cte);
        $this->manager->flush();

        foreach ($invoices as $invoice) {
            $invoice->setCte($cte);
            $invoice->setIntegration($task);
            $this->manager->persist($invoice);
        }
        $this->manager->flush();

        return $cte;
    }

    private function processIntegrations(int $limit): int
    {
        if (!class_exists(Integration::class)) {
            return 0;
        }

        $open = $this->statusService->discoveryStatus('open', 'open', 'integration');
        $qb = $this->manager->createQueryBuilder()
            ->select('integration')
            ->from(Integration::class, 'integration')
            ->andWhere('integration.queueName IN (:queues)')
            ->setParameter('queues', ['cte_emission', 'CteEmission'])
            ->setMaxResults($limit)
            ->orderBy('integration.id', 'ASC');
        if ($open) {
            $qb->andWhere('integration.status = :status')->setParameter('status', $open);
        }
        $items = $qb->getQuery()->getResult();
        $count = 0;
        foreach ($items as $integration) {
            try {
                $this->processMessengerIntegration($integration);
                $closed = $this->statusService->discoveryStatus('closed', 'closed', 'integration');
                $integration->setStatus($closed);
                $this->manager->persist($integration);
                $this->manager->flush();
                $count++;
            } catch (\Throwable) {
                $error = $this->statusService->discoveryStatus('error', 'error', 'integration');
                $integration->setStatus($error);
                $this->manager->persist($integration);
                $this->manager->flush();
            }
        }

        return $count;
    }

    private function processTasks(int $limit): int
    {
        $pending = $this->statusService->discoveryStatus('pending', 'pending', 'invoice_task');
        $qb = $this->manager->createQueryBuilder()
            ->select('task')
            ->from(Integration::class, 'task')
            ->andWhere('task.taskType = :type')
            ->setParameter('type', 'cte_emission')
            ->setMaxResults($limit)
            ->orderBy('task.id', 'ASC');
        if ($pending) {
            $qb->andWhere('task.status = :status')->setParameter('status', $pending);
        }
        $tasks = $qb->getQuery()->getResult();
        $count = 0;
        foreach ($tasks as $task) {
            try {
                $this->processTask($task);
                $count++;
            } catch (\Throwable) {
                // status already persisted as error
            }
        }

        return $count;
    }
}
