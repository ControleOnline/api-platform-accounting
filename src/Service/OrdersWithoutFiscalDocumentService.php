<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

final class OrdersWithoutFiscalDocumentService
{
    private const DOCUMENT_TYPES = ['nfe' => 55, 'nfce' => 65, 'nfse' => 99];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function list(string $documentType): array
    {
        $type = strtolower(trim($documentType));
        if (!array_key_exists($type, self::DOCUMENT_TYPES)) {
            throw new \InvalidArgumentException('Tipo de documento fiscal invalido.');
        }

        $orders = $this->entityManager->createQueryBuilder()
            ->select([
                'ord.id AS id',
                'ord.orderDate AS orderDate',
                'ord.alterDate AS alterDate',
                'ord.price AS price',
                'ord.app AS app',
                'ord.orderType AS orderType',
                'client.id AS clientId',
                'client.name AS clientName',
                'client.alias AS clientAlias',
                'provider.id AS providerId',
                'provider.name AS providerName',
                'provider.alias AS providerAlias',
                'status.id AS statusId',
                'status.status AS statusName',
                'status.realStatus AS realStatus',
            ])
            ->from(Order::class, 'ord')
            ->leftJoin('ord.invoiceTax', 'invoiceLink', 'WITH', 'invoiceLink.invoiceType = :invoiceType')
            ->leftJoin('ord.client', 'client')
            ->leftJoin('ord.provider', 'provider')
            ->leftJoin('ord.status', 'status')
            ->andWhere('invoiceLink.id IS NULL')
            ->setParameter('invoiceType', self::DOCUMENT_TYPES[$type])
            ->orderBy('ord.orderDate', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $order): array {
            $person = static function (array $row, string $prefix): ?array {
                if ($row[$prefix . 'Id'] === null) {
                    return null;
                }

                return [
                    'id' => (int) $row[$prefix . 'Id'],
                    'name' => $row[$prefix . 'Name'],
                    'alias' => $row[$prefix . 'Alias'],
                ];
            };

            return [
                '@id' => '/orders/' . $order['id'],
                'id' => (int) $order['id'],
                'orderDate' => $order['orderDate']?->format(DATE_ATOM),
                'alterDate' => $order['alterDate']?->format(DATE_ATOM),
                'price' => $order['price'],
                'client' => $person($order, 'client'),
                'provider' => $person($order, 'provider'),
                'status' => $order['statusId'] !== null ? [
                    'id' => (int) $order['statusId'],
                    'status' => $order['statusName'],
                    'realStatus' => $order['realStatus'],
                ] : null,
                'app' => $order['app'],
                'orderType' => $order['orderType'],
            ];
        }, $orders);
    }
}
