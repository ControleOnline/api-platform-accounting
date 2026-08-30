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
        $ids = $this->readIds($request);

        try {
            return new JsonResponse($this->invoicesWithoutCteService->list(
                $issuer ? (int) $issuer : null,
                $ids
            ));
        } catch (\Throwable) {
            return new JsonResponse([
                'member' => [],
                'hydra:member' => [],
                'groups' => [],
                'totalItems' => 0,
                'totalValue' => 0,
                'totalWeight' => 0,
                'error' => 'Unable to list invoices without CT-e',
            ], 500);
        }
    }

    private function readIds(Request $request): array
    {
        $raw = $request->query->all('id');
        if (!$raw) {
            $raw = $request->query->all('id[]');
        }
        if (!$raw) {
            $raw = preg_split('/[\s,]+/', (string) $request->query->get('ids', '')) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($value) => (int) preg_replace('/\D+/', '', (string) $value),
            is_array($raw) ? $raw : [$raw]
        ))));
    }
}
