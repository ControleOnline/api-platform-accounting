<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InvoicesWithoutCteService
{
    public const NF_MODELS = [55, 65];
    public const CTE_MODEL = 57;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function list(?int $issuerId = null, array $ids = []): array
    {
        $scope = $this->resolveCompanyScope();
        if (!$scope['unrestricted'] && $scope['companyIds'] === []) {
            return $this->emptyPayload();
        }
        if ($issuerId && !$scope['unrestricted'] && !in_array($issuerId, $scope['companyIds'], true)) {
            return $this->emptyPayload();
        }
        try {
            $items = $this->listViaSql($issuerId, $ids, $scope);
        } catch (\Throwable) {
            $items = $this->listViaDoctrine($issuerId, $ids, $scope);
        }
        $groups = $this->groupInvoices($items);
        $totalValue = array_reduce(
            $items,
            static fn(float $carry, array $item): float => $carry + (float) ($item['invoiceTotal'] ?? 0),
            0.0
        );
        $totalWeight = array_reduce(
            $items,
            static fn(float $carry, array $item): float => $carry + (float) ($item['weight'] ?? 0),
            0.0
        );

        return [
            'member' => $items,
            'hydra:member' => $items,
            'groups' => $groups,
            'totalItems' => count($items),
            'totalValue' => round($totalValue, 2),
            'totalWeight' => round($totalWeight, 3),
            'summary' => [
                'sum' => [
                    'invoiceTotal' => round($totalValue, 2),
                    'weight' => round($totalWeight, 3),
                ],
                'count' => [
                    'invoices' => count($items),
                ],
            ],
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
                    'totalWeight' => 0.0,
                ];
            }
            $groups[$key]['invoices'][] = $item;
            $groups[$key]['invoiceCount']++;
            $groups[$key]['totalValue'] = round($groups[$key]['totalValue'] + (float) ($item['invoiceTotal'] ?? 0), 2);
            $groups[$key]['totalWeight'] = round($groups[$key]['totalWeight'] + (float) ($item['weight'] ?? 0), 3);
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
        $company = $invoice->getCompany() ?: $issuer;
        $client = $invoice->getClient();
        $provider = $invoice->getProvider();
        $carrier = $invoice->getCarrier();
        $xml = null;
        try {
            $xml = $invoice->getInvoice();
        } catch (\Throwable) {
            $xml = null;
        }

        return [
            '@id' => '/invoice_taxes/' . $invoice->getId(),
            'id' => (int) $invoice->getId(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'invoiceKey' => $invoice->getInvoiceKey(),
            'invoiceModel' => $invoice->getInvoiceModel(),
            'invoiceTotal' => $invoice->getInvoiceTotal() === null ? 0 : (float) $invoice->getInvoiceTotal(),
            'weight' => $this->extractWeight(is_string($xml) ? $xml : null),
            'rntrc' => $this->resolveRntrc($carrier, $company, $issuer),
            'cteId' => $invoice->getCte()?->getId(),
            'companyId' => $this->peopleId($company),
            'companyName' => $this->peopleName($company, 'Empresa não informada'),
            'issuerId' => $this->peopleId($issuer),
            'issuerName' => $this->peopleName($issuer, 'Emitente não informado'),
            'clientId' => $this->peopleId($client),
            'clientName' => $this->peopleName($client, 'Destinatário não informado'),
            'providerId' => $this->peopleId($provider),
            'providerName' => $this->peopleName($provider, 'Remetente não informado'),
            'carrierId' => $this->peopleId($carrier),
            'carrierName' => $this->peopleName($carrier, 'Transportadora não informada'),
            'addressId' => $invoice->getAddress() instanceof Address ? (int) $invoice->getAddress()->getId() : null,
            'addressLabel' => $this->formatAddress($invoice->getAddress()),
            'providerAddressLabel' => $this->formatAddress($invoice->getProviderAddress()),
            'clientAddressLabel' => $this->formatAddress($invoice->getClientAddress()),
            'carrierAddressLabel' => $this->formatAddress($invoice->getCarrierAddress()),
        ];
    }

    private function resolveRntrc(?People ...$peopleList): string
    {
        $ids = [];
        foreach ($peopleList as $people) {
            if ($people instanceof People) {
                $ids[] = (int) $people->getId();
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return '';
        }

        try {
            $document = $this->entityManager->getConnection()->fetchOne(
                'SELECT d.document
                 FROM document d
                 INNER JOIN document_type t ON t.id = d.document_type_id
                 WHERE d.people_id IN (?) AND (
                    UPPER(t.document_type) LIKE ? OR UPPER(t.document_type) LIKE ?
                 )
                 ORDER BY d.id DESC',
                [$ids, '%RNTRC%', '%ANTT%'],
                [ArrayParameterType::INTEGER, \PDO::PARAM_STR, \PDO::PARAM_STR]
            );
            if (is_string($document) && trim($document) !== '') {
                return trim($document);
            }
        } catch (\Throwable) {
        }

        try {
            $config = $this->entityManager->getConnection()->fetchOne(
                'SELECT c.value
                 FROM config c
                 WHERE c.people_id IN (?) AND c.config_key = ?
                 ORDER BY c.id DESC',
                [$ids, 'receita-federal-cte-rntrc'],
                [ArrayParameterType::INTEGER, \PDO::PARAM_STR]
            );
            if (is_string($config) && trim($config) !== '') {
                return trim($config);
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function extractWeight(?string $xml): float
    {
        if (!$xml) {
            return 0.0;
        }

        $total = 0.0;
        if (preg_match_all('/<pesoB>([^<]+)<\/pesoB>/i', $xml, $matches)) {
            foreach ($matches[1] as $value) {
                $total += (float) str_replace(',', '.', trim((string) $value));
            }
        }

        return round($total, 3);
    }

    private function peopleId(?People $people): ?int
    {
        return $people instanceof People ? (int) $people->getId() : null;
    }

    private function peopleName(?People $people, string $fallback): string
    {
        if (!$people instanceof People) {
            return $fallback;
        }

        $alias = method_exists($people, 'getAlias') ? trim((string) $people->getAlias()) : '';
        $name = method_exists($people, 'getName') ? trim((string) $people->getName()) : '';

        return $alias !== '' ? $alias : ($name !== '' ? $name : $fallback);
    }

    private function listViaDoctrine(?int $issuerId, array $ids, array $scope): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('invoiceTax')
            ->from(InvoiceTax::class, 'invoiceTax')
            ->leftJoin('invoiceTax.issuer', 'issuer')
            ->leftJoin('invoiceTax.address', 'address')
            ->leftJoin('invoiceTax.client', 'client')
            ->leftJoin('invoiceTax.provider', 'provider')
            ->leftJoin('invoiceTax.carrier', 'carrier')
            ->leftJoin('invoiceTax.company', 'company')
            ->andWhere('invoiceTax.cte IS NULL')
            ->andWhere('invoiceTax.invoiceModel IS NULL OR invoiceTax.invoiceModel IN (:nfModels)')
            ->setParameter('nfModels', self::NF_MODELS)
            ->orderBy('issuer.alias', 'ASC')
            ->addOrderBy('invoiceTax.invoiceNumber', 'ASC');

        if ($issuerId) {
            $qb->andWhere('issuer.id = :issuerId')->setParameter('issuerId', $issuerId);
        }
        if (!$scope['unrestricted']) {
            $qb->andWhere('(issuer.id IN (:allowedCompanies) OR company.id IN (:allowedCompanies) OR invoiceTax.company IN (:allowedCompanies))')
                ->setParameter('allowedCompanies', $scope['companyIds']);
        }
        if ($ids) {
            $qb->andWhere('invoiceTax.id IN (:ids)')->setParameter('ids', $ids);
        }

        $invoices = $qb->getQuery()->getResult();
        $busyIds = $this->busyInvoiceIds();
        $invoices = array_values(array_filter(
            $invoices,
            static fn(InvoiceTax $invoice) => !in_array((int) $invoice->getId(), $busyIds, true)
        ));
        return array_map(fn(InvoiceTax $invoice) => $this->serializeInvoice($invoice), $invoices);
    }

    private function listViaSql(?int $issuerId, array $ids, array $scope): array
    {
        $conn = $this->entityManager->getConnection();
        $columns = $this->invoiceTaxColumnSet();
        $select = [
            'it.id',
            'it.invoice_number',
            'it.invoice_key',
            'it.invoice_model',
            'it.invoice_total',
            'it.cte_id',
            'it.issuer_id',
            'it.company_id',
            'it.client_id',
            'it.provider_id',
            'it.carrier_id',
            'it.address_id',
            'issuer.alias AS issuer_alias',
            'issuer.name AS issuer_name',
            'company.alias AS company_alias',
            'company.name AS company_name',
            'client.alias AS client_alias',
            'client.name AS client_name',
            'provider.alias AS provider_alias',
            'provider.name AS provider_name',
            'carrier.alias AS carrier_alias',
            'carrier.name AS carrier_name',
        ];
        $sql = 'SELECT ' . implode(', ', $select) . '
            FROM invoice_tax it
            LEFT JOIN people issuer ON issuer.id = it.issuer_id
            LEFT JOIN people company ON company.id = it.company_id
            LEFT JOIN people client ON client.id = it.client_id
            LEFT JOIN people provider ON provider.id = it.provider_id
            LEFT JOIN people carrier ON carrier.id = it.carrier_id
            WHERE it.cte_id IS NULL
              AND (it.invoice_model IS NULL OR it.invoice_model IN (55, 65))';
        $params = [];
        $types = [];
        if ($issuerId) {
            $sql .= ' AND it.issuer_id = ?';
            $params[] = $issuerId;
            $types[] = \PDO::PARAM_INT;
        }
        if (!$scope['unrestricted']) {
            $sql .= ' AND (it.issuer_id IN (?) OR it.company_id IN (?))';
            $params[] = $scope['companyIds'];
            $params[] = $scope['companyIds'];
            $types[] = ArrayParameterType::INTEGER;
            $types[] = ArrayParameterType::INTEGER;
        }
        if ($ids) {
            $sql .= ' AND it.id IN (?)';
            $params[] = $ids;
            $types[] = ArrayParameterType::INTEGER;
        }
        if (isset($columns['invoice_task_id'])) {
            $sql .= ' AND it.invoice_task_id IS NULL';
        }
        $sql .= ' ORDER BY issuer.alias ASC, it.invoice_number ASC';
        $rows = $conn->fetchAllAssociative($sql, $params, $types);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->serializeSqlRow($row);
        }
        return $items;
    }

    private function invoiceTaxColumnSet(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }
        $set = [];
        try {
            $names = $this->entityManager->getConnection()->fetchFirstColumn('SHOW COLUMNS FROM invoice_tax');
            foreach ($names ?: [] as $name) {
                $set[strtolower((string) $name)] = true;
            }
        } catch (\Throwable) {
            $set = [];
        }
        return $set;
    }

    private function serializeSqlRow(array $row): array
    {
        $companyId = isset($row['company_id']) && $row['company_id'] !== null ? (int) $row['company_id'] : (isset($row['issuer_id']) && $row['issuer_id'] !== null ? (int) $row['issuer_id'] : null);
        $companyName = $this->preferName($row['company_alias'] ?? null, $row['company_name'] ?? null, 'Empresa não informada');
        if ($companyName === 'Empresa não informada') {
            $companyName = $this->preferName($row['issuer_alias'] ?? null, $row['issuer_name'] ?? null, 'Empresa não informada');
        }
        return [
            '@id' => '/invoice_taxes/' . (int) $row['id'],
            'id' => (int) $row['id'],
            'invoiceNumber' => $row['invoice_number'] !== null ? (int) $row['invoice_number'] : null,
            'invoiceKey' => $row['invoice_key'] ?? null,
            'invoiceModel' => $row['invoice_model'] !== null ? (int) $row['invoice_model'] : null,
            'invoiceTotal' => $row['invoice_total'] === null ? 0 : (float) $row['invoice_total'],
            'weight' => 0.0,
            'rntrc' => '',
            'cteId' => $row['cte_id'] !== null ? (int) $row['cte_id'] : null,
            'companyId' => $companyId,
            'companyName' => $companyName,
            'issuerId' => $row['issuer_id'] !== null ? (int) $row['issuer_id'] : null,
            'issuerName' => $this->preferName($row['issuer_alias'] ?? null, $row['issuer_name'] ?? null, 'Emitente não informado'),
            'clientId' => $row['client_id'] !== null ? (int) $row['client_id'] : null,
            'clientName' => $this->preferName($row['client_alias'] ?? null, $row['client_name'] ?? null, 'Destinatário não informado'),
            'providerId' => $row['provider_id'] !== null ? (int) $row['provider_id'] : null,
            'providerName' => $this->preferName($row['provider_alias'] ?? null, $row['provider_name'] ?? null, 'Remetente não informado'),
            'carrierId' => $row['carrier_id'] !== null ? (int) $row['carrier_id'] : null,
            'carrierName' => $this->preferName($row['carrier_alias'] ?? null, $row['carrier_name'] ?? null, 'Transportadora não informada'),
            'addressId' => $row['address_id'] !== null ? (int) $row['address_id'] : null,
            'addressLabel' => 'Endereço não informado',
            'providerAddressLabel' => 'Endereço não informado',
            'clientAddressLabel' => 'Endereço não informado',
            'carrierAddressLabel' => 'Endereço não informado',
        ];
    }

    private function preferName(mixed $alias, mixed $name, string $fallback): string
    {
        $alias = trim((string) ($alias ?? ''));
        $name = trim((string) ($name ?? ''));
        return $alias !== '' ? $alias : ($name !== '' ? $name : $fallback);
    }


    private function emptyPayload(): array
    {
        return [
            'member' => [],
            'hydra:member' => [],
            'groups' => [],
            'totalItems' => 0,
            'totalValue' => 0,
            'totalWeight' => 0,
            'summary' => [
                'sum' => ['invoiceTotal' => 0, 'weight' => 0],
                'count' => ['invoices' => 0],
            ],
        ];
    }

    /**
     * @return array{unrestricted: bool, companyIds: int[]}
     */
    private function resolveCompanyScope(): array
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!is_object($user)) {
            return ['unrestricted' => false, 'companyIds' => []];
        }
        $roles = method_exists($user, 'getRoles') ? (array) $user->getRoles() : [];
        if (in_array('ROLE_SUPER', $roles, true)) {
            return ['unrestricted' => true, 'companyIds' => []];
        }

        $people = method_exists($user, 'getPeople') ? $user->getPeople() : null;
        $peopleId = $people instanceof People ? (int) $people->getId() : 0;
        if ($peopleId <= 0) {
            return ['unrestricted' => false, 'companyIds' => []];
        }

        $ids = [$peopleId];
        try {
            $linked = $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT company_id FROM people_link WHERE people_id = ? AND (enabled = 1 OR enabled IS NULL)',
                [$peopleId]
            );
            foreach ($linked ?: [] as $companyId) {
                $ids[] = (int) $companyId;
            }
        } catch (\Throwable) {
            try {
                $linked = $this->entityManager->getConnection()->fetchFirstColumn(
                    'SELECT company FROM people_link WHERE people_id = ? AND (enabled = 1 OR enabled IS NULL)',
                    [$peopleId]
                );
                foreach ($linked ?: [] as $companyId) {
                    $ids[] = (int) $companyId;
                }
            } catch (\Throwable) {
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        return ['unrestricted' => false, 'companyIds' => $ids];
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
