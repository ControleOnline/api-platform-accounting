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

            $invoiceTax = $this->nFeService->createNfe($data, 55);
            return new JsonResponse([
                'response' => [
                    'data'    => $data->getId(),
                    'invoice_tax' => $invoiceTax->getId(),
                    'xml' => $invoiceTax->getInvoice(),
                    'count'   => 1,
                    'error'   => '',
                    'success' => true,
                ],
            ]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'response' => [
                    'count'   => 0,
                    'error'   => $th->getMessage(),
                    'success' => false,
                ],
            ]);
        }
    }
}
