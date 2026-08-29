<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\EmitCteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmitCteAction
{
    public function __construct(private EmitCteService $emitCteService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $ids = $payload['invoiceTaxIds'] ?? $payload['selected'] ?? [];
        $cfop = (string) ($payload['cfop'] ?? '');
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

        try {
            $task = $this->emitCteService->emit(is_array($ids) ? $ids : [], $cfop, $extra);
            return new JsonResponse([
                'id' => $task->getId(),
                '@id' => '/invoice_tasks/' . $task->getId(),
                '@type' => 'InvoiceTask',
                'taskType' => $task->getTaskType(),
                'cfop' => $task->getCfop(),
                'invoiceTotal' => $task->getInvoiceTotal(),
                'status' => $task->getStatus()?->getRealStatus(),
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 500);
        }
    }
}
