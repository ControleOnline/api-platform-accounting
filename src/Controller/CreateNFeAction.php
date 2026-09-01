<?php

namespace ControleOnline\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Entity\Order;
use ControleOnline\Service\NFeService;
use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateNFeAction
{



    public function __construct(
        private EntityManagerInterface $manager,
        private NFeService $nFeService,
        private InvoiceTaxImportProcessor $importProcessor
    ) {}


    public function __invoke(Order $data, Request $request): JsonResponse
    {
        try {
            $model = $request->query->get('model');
            if (!in_array((string) $model, ['55', '65'], true)) {
                throw new BadRequestHttpException('O modelo fiscal deve ser informado como 55 ou 65.');
            }

            $xml = $this->nFeService->createNfe($data, (string) $model);
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
            return new JsonResponse([
                'response' => [
                    'data'    => $data->getId(),
                    'invoice_tax' => $invoiceTax->getId(),
                    'xml' => $xml,
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
                    'file' => $th->getFile(),
                    'line'=> $th->getLine(),
                    'success' => false,
                ],
            ], 500);
        }
    }
}
