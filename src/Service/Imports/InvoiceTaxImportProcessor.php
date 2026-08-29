<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\File;
use ControleOnline\Entity\Import;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\FileService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceTaxImportProcessor implements ImportProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileService $fileService,
        private StatusService $statusService
    ) {}

    public function getType(): string
    {
        return 'invoice_tax';
    }

    public function getExampleCsv(): array
    {
        return [];
    }

    public function process(Import $import): void
    {
        $file = $import->getFile();
        if (!$file) {
            throw new \RuntimeException('Arquivo de importacao nao encontrado.');
        }

        $content = $file->getContent(true);
        if (empty($content)) {
            throw new \RuntimeException('Conteudo do arquivo ZIP esta vazio.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'nfe_zip_');
        file_put_contents($tempPath, $content);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Nao foi possivel abrir o arquivo ZIP.');
        }

        $statusOpen = $this->statusService->discoveryStatus('open', 'open', 'invoice_tax');
        $people = $import->getPeople();
        $importedCount = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'] ?? '';

            if (empty($filename) || str_ends_with($filename, '/')) {
                continue;
            }

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $xmlContent = $zip->getFromIndex($i);
            if (empty($xmlContent)) {
                continue;
            }

            $parsed = $this->parseNfeXml($xmlContent);
            if (!$parsed) {
                continue;
            }

            $xmlFile = $this->fileService->addFile(
                $people,
                $xmlContent,
                'invoice_tax',
                basename($filename),
                'application',
                'xml',
                false
            );

            $invoiceTax = new InvoiceTax();
            $invoiceTax->setFile($xmlFile);
            $invoiceTax->setInvoiceKey($parsed['key']);
            $invoiceTax->setInvoiceNumber((int) $parsed['number']);
            $invoiceTax->setStatus($statusOpen);

            $this->entityManager->persist($invoiceTax);
            $importedCount++;
        }

        $zip->close();
        @unlink($tempPath);

        $this->entityManager->flush();
        $import->setFeedback(sprintf('%d notas fiscais importadas com sucesso do arquivo ZIP.', $importedCount));
    }

    public function parseNfeXml(string $xmlContent): ?array
    {
        try {
            $useErrors = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            libxml_use_internal_errors($useErrors);

            if (!$xml) {
                return null;
            }

            $namespaces = $xml->getNamespaces(true);
            $nfe = $xml;
            if (isset($xml->NFe)) {
                $nfe = $xml->NFe;
            }

            $infNFe = $nfe->infNFe ?? null;
            $key = null;
            $number = null;

            if ($infNFe) {
                $attributes = $infNFe->attributes();
                $rawId = (string) ($attributes['Id'] ?? '');
                $key = preg_replace('/^NFe/i', '', $rawId);
                $number = (string) ($infNFe->ide->nNF ?? '');
            }

            if (empty($key) && isset($xml->protNFe->infProt->chNFe)) {
                $key = (string) $xml->protNFe->infProt->chNFe;
            }

            if (empty($number) && !empty($key) && strlen($key) >= 34) {
                // nNF is positions 26 to 34 (9 digits)
                $number = (string) (int) substr($key, 25, 9);
            }

            if (empty($key) && empty($number)) {
                return null;
            }

            return [
                'key' => $key ?: '',
                'number' => $number ?: '0',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
