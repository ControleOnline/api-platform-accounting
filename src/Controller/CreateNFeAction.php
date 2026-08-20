<?php

namespace ControleOnline\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Entity\Order;
use ControleOnline\Service\NFeService;

class CreateNFeAction
{
    public function __construct(
        private EntityManagerInterface $manager,
        private NFeService $nFeService
    ) {}

    public function __invoke(Order $data, Request $request): JsonResponse
    {
        try {
            $payload = [];
            $content = $request->getContent();
            if (is_string($content) && $content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            // NFC-e (modelo 65) is the default for order cupom fiscal; 55 = NF-e.
            $model = (string) ($payload['model'] ?? $request->query->get('model') ?? '65');
            if (!in_array($model, ['55', '65', '57'], true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported NF model: %s', $model));
            }

            $invoiceTax = $this->nFeService->createNfe($data, $model);

            return new JsonResponse([
                'response' => [
                    'data' => $data->getId(),
                    'invoice_tax' => $invoiceTax->getId(),
                    'invoice_number' => $invoiceTax->getInvoiceNumber(),
                    'invoice_key' => $invoiceTax->getInvoiceKey(),
                    'model' => $model,
                    'count' => 1,
                    'error' => '',
                    'success' => true,
                ],
            ]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'response' => [
                    'count' => 0,
                    'error' => $th->getMessage(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                    'success' => false,
                ],
            ], 500);
        }
    }
}
