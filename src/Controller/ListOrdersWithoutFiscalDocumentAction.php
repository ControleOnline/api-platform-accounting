<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\OrdersWithoutFiscalDocumentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ListOrdersWithoutFiscalDocumentAction
{
    public function __construct(private OrdersWithoutFiscalDocumentService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $items = $this->service->list(
            (string) $request->query->get('documentType', ''),
            (string) $request->query->get('provider', ''),
        );

        return new JsonResponse([
            'member' => $items,
            'hydra:member' => $items,
            'totalItems' => count($items),
        ]);
    }
}
