<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;

class InvoicesWithoutCteService
{
    public const NF_MODELS = [55, 65];
    public const CTE_MODEL = 57;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function list(?int $issuerId = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('invoiceTax')
            ->from(InvoiceTax::class, 'invoiceTax')
            ->leftJoin('invoiceTax.issuer', 'issuer')
            ->leftJoin('invoiceTax.address', 'address')
            ->andWhere('invoiceTax.cte IS NULL')
            ->andWhere('invoiceTax.invoiceModel IS NULL OR invoiceTax.invoiceModel IN (:nfModels)')
            ->setParameter('nfModels', self::NF_MODELS)
            ->orderBy('issuer.alias', 'ASC')
            ->addOrderBy('invoiceTax.invoiceNumber', 'ASC');

        if ($issuerId) {
            $qb->andWhere('issuer.id = :issuerId')->setParameter('issuerId', $issuerId);
        }

        $invoices = $qb->getQuery()->getResult();
        $busyIds = $this->busyInvoiceIds();
        $invoices = array_values(array_filter(
            $invoices,
            static fn(InvoiceTax $invoice) => !in_array((int) $invoice->getId(), $busyIds, true)
        ));
        $items = array_map(fn(InvoiceTax $invoice) => $this->serializeInvoice($invoice), $invoices);
        $groups = $this->groupInvoices($items);
        $totalValue = array_reduce(
            $items,
            static fn(float $carry, array $item): float => $carry + (float) ($item['invoiceTotal'] ?? 0),
            0.0
        );

        return [
            'member' => $items,
            'hydra:member' => $items,
            'groups' => $groups,
            'totalItems' => count($items),
            'totalValue' => round($totalValue, 2),
        ];
    }

    public function groupInvoices(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $this->groupKey($item);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $key,
                    'companyId' => $item['companyId'] ?? null,
                    'companyName' => $item['companyName'] ?? 'Empresa não informada',
                    'addressId' => $item['addressId'] ?? null,
                    'addressLabel' => $item['addressLabel'] ?? 'Endereço não informado',
                    'invoices' => [],
                    'invoiceCount' => 0,
                    'totalValue' => 0.0,
                ];
            }
            $groups[$key]['invoices'][] = $item;
            $groups[$key]['invoiceCount']++;
            $groups[$key]['totalValue'] = round($groups[$key]['totalValue'] + (float) ($item['invoiceTotal'] ?? 0), 2);
        }
        return array_values($groups);
    }

    public function groupKey(array $item): string
    {
        return (string) ($item['companyId'] ?? 'none') . ':' . (string) ($item['addressId'] ?? 'none');
    }

    public function serializeInvoice(InvoiceTax $invoice): array
    {
        $issuer = $invoice->getIssuer();
        $address = $invoice->getAddress();
        return [
            '@id' => '/invoice_taxes/' . $invoice->getId(),
            'id' => (int) $invoice->getId(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'invoiceKey' => $invoice->getInvoiceKey(),
            'invoiceModel' => $invoice->getInvoiceModel(),
            'invoiceTotal' => $invoice->getInvoiceTotal() === null ? 0 : (float) $invoice->getInvoiceTotal(),
            'cteId' => $invoice->getCte()?->getId(),
            'companyId' => $issuer instanceof People ? (int) $issuer->getId() : null,
            'companyName' => $issuer instanceof People
                ? trim((string) ($issuer->getAlias() ?: $issuer->getName()) ?: 'Empresa não informada')
                : 'Empresa não informada',
            'addressId' => $address instanceof Address ? (int) $address->getId() : null,
            'addressLabel' => $this->formatAddress($address),
        ];
    }

    private function busyInvoiceIds(): array
    {
        try {
            $ids = $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT id FROM invoice_tax WHERE invoice_task_id IS NOT NULL'
            );
            return array_map('intval', $ids ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function formatAddress(?Address $address): string
    {
        if (!$address instanceof Address) {
            return 'Endereço não informado';
        }
        $street = method_exists($address, 'getStreet') ? $address->getStreet() : null;
        $district = $street && method_exists($street, 'getDistrict') ? $street->getDistrict() : null;
        $city = $district && method_exists($district, 'getCity') ? $district->getCity() : null;
        $state = $city && method_exists($city, 'getState') ? $city->getState() : null;
        $parts = array_filter([
            $street && method_exists($street, 'getStreet') ? $street->getStreet() : null,
            method_exists($address, 'getNumber') ? $address->getNumber() : null,
            $district && method_exists($district, 'getDistrict') ? $district->getDistrict() : null,
            $city && method_exists($city, 'getCity') ? $city->getCity() : null,
            $state && method_exists($state, 'getUf') ? $state->getUf() : null,
        ]);
        return $parts === [] ? 'Endereço não informado' : implode(', ', $parts);
    }
}
