<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Import;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\FileService;
use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use ControleOnline\Service\ImportService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvoiceTaxUploadController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileService $fileService,
        private StatusService $statusService,
        private ImportService $importService,
        private InvoiceTaxImportProcessor $importProcessor
    ) {}

    public function __invoke(Request $request): Response
    {
        $peopleId = $request->request->get('people') ?: $request->request->get('company');
        $people = $peopleId ? $this->fileService->resolvePeopleReference($peopleId) : null;

        $uploadedFiles = [];
        $filesFromRequest = $request->files->all();

        foreach ($filesFromRequest as $fileOrArray) {
            if (is_array($fileOrArray)) {
                foreach ($fileOrArray as $item) {
                    if ($item instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                        $uploadedFiles[] = $item;
                    }
                }
            } elseif ($fileOrArray instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $uploadedFiles[] = $fileOrArray;
            }
        }

        if (empty($uploadedFiles)) {
            throw new BadRequestHttpException('Nenhum arquivo XML ou ZIP enviado.');
        }

        $importedInvoices = [];
        $createdImports = [];
        $errors = [];
        $statusOpen = $this->statusService->discoveryStatus('open', 'open', 'invoice_tax');
        $importStatusOpen = $this->statusService->discoveryStatus('open', 'open', 'integration');

        foreach ($uploadedFiles as $uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = strtolower($uploadedFile->getClientOriginalExtension());

            if ($extension === 'xml') {
                try {
                    $fileEntity = $this->fileService->addUploadedFile($uploadedFile, $people, 'invoice_tax');
                    $xmlContent = $fileEntity->getContent(true);
                    $parsed = $this->importProcessor->parseNfeXml($xmlContent);

                    $invoiceTax = new InvoiceTax();
                    $invoiceTax->setFile($fileEntity);
                    $invoiceTax->setInvoiceKey($parsed['key'] ?? '');
                    $invoiceTax->setInvoiceNumber((int) ($parsed['number'] ?? 0));
                    $invoiceTax->setStatus($statusOpen);

                    $this->entityManager->persist($invoiceTax);
                    $this->entityManager->flush();

                    $importedInvoices[] = [
                        'id' => $invoiceTax->getId(),
                        'fileName' => $originalName,
                        'invoiceKey' => $invoiceTax->getInvoiceKey(),
                        'invoiceNumber' => $invoiceTax->getInvoiceNumber(),
                        'status' => $statusOpen->getStatus(),
                    ];
                } catch (\Throwable $e) {
                    $errors[] = [
                        'file' => $originalName,
                        'error' => $e->getMessage(),
                    ];
                }
            } elseif ($extension === 'zip') {
                try {
                    $zipFileEntity = $this->fileService->addUploadedFile($uploadedFile, $people, 'import');

                    $import = new Import();
                    $import->setImportType('invoice_tax');
                    $import->setFileFormat('zip');
                    $import->setFile($zipFileEntity);
                    $import->setPeople($people);
                    $import->setStatus($importStatusOpen);

                    $this->entityManager->persist($import);
                    $this->entityManager->flush();

                    // Process ZIP immediately
                    try {
                        $this->importProcessor->process($import);
                        $statusDone = $this->statusService->discoveryStatus('pending', 'done', 'integration');
                        $import->setStatus($statusDone);
                        $this->entityManager->persist($import);
                        $this->entityManager->flush();
                    } catch (\Throwable $processError) {
                        // If background processing is used, it remains open/error
                        $import->setFeedback($processError->getMessage());
                    }

                    $createdImports[] = [
                        'id' => $import->getId(),
                        'fileName' => $originalName,
                        'status' => $import->getStatus()?->getStatus(),
                        'feedback' => $import->getFeedback(),
                    ];
                } catch (\Throwable $e) {
                    $errors[] = [
                        'file' => $originalName,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $errors[] = [
                    'file' => $originalName,
                    'error' => 'Formato não suportado. Apenas arquivos .xml e .zip são permitidos.',
                ];
            }
        }

        return new JsonResponse([
            'success' => count($errors) === 0 || count($importedInvoices) > 0 || count($createdImports) > 0,
            'importedCount' => count($importedInvoices),
            'zipCount' => count($createdImports),
            'invoices' => $importedInvoices,
            'imports' => $createdImports,
            'errors' => $errors,
        ], 200);
    }
}
