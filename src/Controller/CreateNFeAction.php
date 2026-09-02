<?php

namespace ControleOnline\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoiceTax;
use ControleOnline\Service\NFeService;
use ControleOnline\Service\NfseNationalService;
use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateNFeAction
{



    public function __construct(
        private EntityManagerInterface $manager,
        private NFeService $nFeService,
        private InvoiceTaxImportProcessor $importProcessor,
        private ?NfseNationalService $nfseNationalService = null
    ) {}


    public function __invoke(Order $data, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $requestedIds = is_array($payload) && is_array($payload['orderIds'] ?? null)
                ? $payload['orderIds']
                : [$data->getId()];
            $orderIds = array_values(array_unique(array_filter(
                array_map('intval', $requestedIds),
                static fn (int $id): bool => $id > 0
            )));
            if ($orderIds === []) {
                throw new BadRequestHttpException('Selecione ao menos um pedido para emitir a nota fiscal.');
            }

            $orders = $this->manager->getRepository(Order::class)->findBy(['id' => $orderIds]);
            $ordersById = [];
            foreach ($orders as $order) {
                $ordersById[(int) $order->getId()] = $order;
            }
            if (count($ordersById) !== count($orderIds) || !isset($ordersById[(int) $data->getId()])) {
                throw new BadRequestHttpException('Um ou mais pedidos selecionados não foram encontrados.');
            }
            $orders = array_map(static fn (int $id): Order => $ordersById[$id], $orderIds);
            foreach ($orders as $order) {
                if ((int) $order->getProvider()?->getId() !== (int) $data->getProvider()?->getId()) {
                    throw new BadRequestHttpException('Todos os pedidos devem pertencer à mesma empresa corrente.');
                }
            }

            $model = $request->query->get('model');
            if (!in_array((string) $model, ['55', '65', '99', 'NFSE'], true)) {
                throw new BadRequestHttpException('O modelo fiscal deve ser informado como 55, 65 ou 99.');
            }

            if ((string) $model === '99' || (string) $model === 'NFSE') {
                if (!$this->nfseNationalService) {
                    throw new \RuntimeException('O serviço nacional de NFS-e não está disponível.');
                }
                $serviceCode = (string) ($payload['serviceCode'] ?? '');
                $description = (string) ($payload['serviceDescription'] ?? '');
                $serviceValue = (string) ($payload['serviceValue'] ?? '');
                $emission = $this->nfseNationalService->emit($data, $serviceCode, $description, $serviceValue);
                $xml = $emission['xml'];
                $fiscalNumber = (int) $emission['number'];
            } else {
                $xml = $this->nFeService->createNfe($orders, (string) $model);
                $fiscalNumber = null;
            }
            if (!is_string($xml) || trim($xml) === '') {
                throw new \RuntimeException('A assinatura não retornou o XML fiscal.');
            }
            $invoiceTax = $this->importProcessor->importXmlContent(
                $data->getProvider(),
                sprintf('nf-%s-%s.xml', $model, $data->getId()),
                $xml
            );
            if (!$invoiceTax) {
                throw new \RuntimeException('O XML assinado não pôde ser persistido.');
            }
            if ((string) $model === '99' || (string) $model === 'NFSE') {
                $this->nfseNationalService->registerFiscalNumber($data, $fiscalNumber);
            } else {
                $this->nFeService->registerFiscalNumber($data, (int) $invoiceTax->getFiscalNumber());
            }
            foreach ($orders as $order) {
                $link = $this->manager->getRepository(OrderInvoiceTax::class)->findOneBy([
                    'order' => $order,
                    'invoiceType' => ((string) $model === 'NFSE' ? 99 : (int) $model),
                    'issuer' => $order->getProvider(),
                ]);
                if ($link instanceof OrderInvoiceTax) {
                    $link->setInvoiceTax($invoiceTax);
                } else {
                    $link = new OrderInvoiceTax();
                    $link->setOrder($order);
                    $link->setInvoiceTax($invoiceTax);
                    $link->setInvoiceType((string) $model === 'NFSE' ? 99 : (int) $model);
                    $link->setIssuer($order->getProvider());
                    $this->manager->persist($link);
                }
            }
            $this->manager->flush();

            return new JsonResponse([
                'response' => [
                    'data'    => array_map(static fn (Order $order): int => (int) $order->getId(), $orders),
                    'invoice_tax' => $invoiceTax->getId(),
                    'xml' => $xml,
                    'count'   => count($orders),
                    'error'   => '',
                    'success' => true,
                ],
            ]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'response' => [
                    'count'   => 0,
                    'error'   => $th->getMessage(),
                    'file' => $th->getFile(),
                    'line'=> $th->getLine(),
                    'success' => false,
                ],
            ], 500);
        }
    }
}
