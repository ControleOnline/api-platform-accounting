<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use Doctrine\ORM\EntityManagerInterface;

class CteEmissionService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private StatusService $statusService
    ) {
    }

    public function integrate(Integration $integration): void
    {
        $payload = json_decode((string) $integration->getBody(), true) ?: [];
        $ids = $payload['invoiceTaxIds'] ?? $payload['selected'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (!$ids) {
            throw new \RuntimeException('Nenhuma NF informada para emissão de CT-e.');
        }
        $cfop = (string) ($payload['cfop'] ?? '5353');
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

        $repo = $this->manager->getRepository(InvoiceTax::class);
        $nfs = $repo->findBy(['id' => $ids]);
        if (count($nfs) !== count($ids)) {
            throw new \RuntimeException('Uma ou mais NFs não encontradas.');
        }

        // Prevent double emission
        foreach ($nfs as $nf) {
            if ($nf->getCte() instanceof InvoiceTax) {
                throw new \RuntimeException(sprintf('NF %s já possui CT-e vinculado.', $nf->getInvoiceNumber()));
            }
        }

        $first = $nfs[0];
        $company = $first->getCompany() ?: $first->getIssuer();
        $total = 0.0;
        foreach ($nfs as $nf) {
            $total += (float) ($nf->getInvoiceTotal() ?? 0);
        }

        // Create CTE InvoiceTax (model 57)
        $cte = new InvoiceTax();
        $cte->setInvoiceModel(57);
        // Generate a pseudo unique number/key for the CTE
        $cteNumber = 57000000 + (int) $integration->getId();
        $cte->setInvoiceNumber($cteNumber);
        // Simple key: 57 + padded integration id + random
        $baseKey = $first->getInvoiceKey() ?: str_pad((string) $integration->getId(), 44, '0', STR_PAD_LEFT);
        $cteKey = '57' . substr(preg_replace('/\D/', '', $baseKey), 2, 42);
        $cteKey = str_pad(substr($cteKey, 0, 44), 44, '0');
        $cte->setInvoiceKey($cteKey);
        $cte->setInvoiceTotal(number_format($total, 2, '.', ''));
        $cte->setCompany($company);
        $cte->setIssuer($first->getIssuer());
        $cte->setClient($first->getClient());
        $cte->setProvider($first->getProvider());
        $cte->setCarrier($first->getCarrier());
        $cte->setAddress($first->getAddress());
        $cte->setProviderAddress($first->getProviderAddress());
        $cte->setClientAddress($first->getClientAddress());
        $cte->setCarrierAddress($first->getCarrierAddress());
        // Status closed = emitted
        $cteStatus = $this->statusService->discoveryStatus('closed', 'closed', 'invoice_tax');
        $cte->setStatus($cteStatus);
        // Link payload as XML placeholder
        $cte->setInvoice(json_encode(['cfop' => $cfop, 'extra' => $extra, 'nfs' => $ids], JSON_UNESCAPED_UNICODE));

        $this->manager->persist($cte);
        $this->manager->flush();

        // Link NFs to CTE
        $nfStatus = $this->statusService->discoveryStatus('closed', 'closed', 'invoice_tax');
        foreach ($nfs as $nf) {
            $nf->setCte($cte);
            $nf->setIntegration($integration);
            $nf->setStatus($nfStatus);
            $this->manager->persist($nf);
        }
        $this->manager->flush();
    }
}
