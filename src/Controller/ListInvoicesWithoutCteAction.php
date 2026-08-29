<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\InvoicesWithoutCteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ListInvoicesWithoutCteAction
{
    public function __construct(private InvoicesWithoutCteService $invoicesWithoutCteService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $issuer = preg_replace('/\D+/', '', (string) $request->query->get('issuer', ''));

        try {
            return new JsonResponse($this->invoicesWithoutCteService->list($issuer ? (int) $issuer : null));
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'member' => [],
                'hydra:member' => [],
                'groups' => [],
                'totalItems' => 0,
                'totalValue' => 0,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
