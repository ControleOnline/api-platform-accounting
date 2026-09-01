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
            ->select('ord', 'client', 'provider', 'status')
            ->from(Order::class, 'ord')
            ->leftJoin('ord.invoiceTax', 'invoiceLink', 'WITH', 'invoiceLink.invoiceType = :invoiceType')
            ->leftJoin('ord.client', 'client')
            ->leftJoin('ord.provider', 'provider')
            ->leftJoin('ord.status', 'status')
            ->andWhere('invoiceLink.id IS NULL')
            ->setParameter('invoiceType', self::DOCUMENT_TYPES[$type])
            ->orderBy('ord.orderDate', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static function (Order $order): array {
            $person = static fn($value): ?array => $value ? [
                'id' => $value->getId(),
                'name' => method_exists($value, 'getName') ? $value->getName() : null,
                'alias' => method_exists($value, 'getAlias') ? $value->getAlias() : null,
            ] : null;
            $status = $order->getStatus();

            return [
                '@id' => '/orders/' . $order->getId(),
                'id' => (int) $order->getId(),
                'orderDate' => $order->getOrderDate()?->format(DATE_ATOM),
                'alterDate' => $order->getAlterDate()?->format(DATE_ATOM),
                'price' => $order->getPrice(),
                'client' => $person($order->getClient()),
                'provider' => $person($order->getProvider()),
                'status' => $status ? [
                    'id' => $status->getId(),
                    'status' => $status->getStatus(),
                    'realStatus' => $status->getRealStatus(),
                ] : null,
                'app' => $order->getApp(),
                'orderType' => $order->getOrderType(),
            ];
        }, $orders);
    }
}
