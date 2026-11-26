<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\EmitMdfeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmitMdfeAction
{
    public function __construct(private EmitMdfeService $emitMdfeService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?: [];

        try {
            $mdfe = $this->emitMdfeService->emit($payload);
            return new JsonResponse([
                'id' => $mdfe->getId(),
                '@id' => '/mdfes/' . $mdfe->getId(),
                'status' => $mdfe->getStatus(),
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage() ?: 'Falha ao emitir MDF-e.'], 500);
        }
    }
}
