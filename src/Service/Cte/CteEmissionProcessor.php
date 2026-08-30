<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTask;
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

    public function processTask(InvoiceTask $task): InvoiceTax
    {
        $processing = $this->statusService->discoveryStatus('processing', 'processing', 'invoice_task');
        $task->setStatus($processing);
        $this->manager->persist($task);
        $this->manager->flush();

        try {
            $payload = json_decode((string) $task->getPayload(), true) ?: [];
            $ids = array_values(array_unique(array_filter(array_map(
                'intval',
                $payload['invoiceTaxIds'] ?? []
            ))));
            $cfop = (string) ($payload['cfop'] ?? $task->getCfop() ?? '');
            $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];
            if ($ids === [] || $cfop === '') {
                throw new \RuntimeException('Payload da invoice_task incompleto (ids/CFOP).');
            }

            $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
            if (count($invoices) !== count($ids)) {
                throw new \RuntimeException('NFs da tarefa não encontradas.');
            }

            $company = $task->getCompany() ?: ($invoices[0]->getCompany() ?: $invoices[0]->getIssuer());
            $fiscal = $this->fiscalConfig->load($company instanceof People ? $company : null);
            if (empty($fiscal['receita-federal-cte-enabled']) && ($fiscal['receita-federal-cte-enabled'] !== '1' && $fiscal['receita-federal-cte-enabled'] !== 1 && $fiscal['receita-federal-cte-enabled'] !== true)) {
                // enabled flag is advisory; certificate is the hard requirement
            }
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
            $task->setPayload(json_encode(array_merge(
                json_decode((string) $task->getPayload(), true) ?: [],
                ['error' => $exception->getMessage()]
            ), JSON_UNESCAPED_UNICODE));
            $this->manager->persist($task);
            $this->manager->flush();
            throw $exception;
        }
    }

    private function persistCte(?People $company, array $invoices, string $xml, string $key, int $number, InvoiceTask $task): InvoiceTax
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
        $cte->setInvoice($xml);
        $cte->setInvoiceKey($key !== '' ? $key : null);
        $cte->setInvoiceNumber($number);
        $cte->setInvoiceModel(57);
        $cte->setInvoiceTotal(number_format($total, 2, '.', ''));
        $cte->setCompany($company);
        $cte->setIssuer($company);
        $cte->setInvoiceTask($task);
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
            $invoice->setInvoiceTask($task);
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
            ->andWhere('integration.queueName = :queue')
            ->setParameter('queue', 'cte_emission')
            ->setMaxResults($limit)
            ->orderBy('integration.id', 'ASC');
        if ($open) {
            $qb->andWhere('integration.status = :status')->setParameter('status', $open);
        }
        $items = $qb->getQuery()->getResult();
        $count = 0;
        foreach ($items as $integration) {
            $body = json_decode((string) $integration->getBody(), true) ?: [];
            $taskId = (int) ($body['invoiceTaskId'] ?? 0);
            $task = $taskId ? $this->manager->getRepository(InvoiceTask::class)->find($taskId) : null;
            try {
                if ($task instanceof InvoiceTask) {
                    $this->processTask($task);
                }
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
            ->from(InvoiceTask::class, 'task')
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
