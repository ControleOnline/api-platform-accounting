<?php

namespace ControleOnline\Controller;


use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Entity\Order;
use ControleOnline\Service\NFeService;
use Symfony\Component\HttpKernel\KernelInterface;

class CreateDacteAction
{
    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;
    private $tools;
    private $appKernel;

    public function __construct(EntityManagerInterface $entityManager, KernelInterface $appKernel, private NFeService $nFeService)
    {
        $this->manager = $entityManager;
        $this->appKernel = $appKernel;
    }



    public function __invoke(Order $data, Request $request): JsonResponse
    {
        try {

            $invoiceTax = $this->nFeService->createNfe($data, 57);
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
