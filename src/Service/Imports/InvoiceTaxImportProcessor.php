<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Import;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\FileService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceTaxImportProcessor implements ImportProcessorInterface
{
    /** @var array<string, InvoiceTax> */
    private array $invoiceTaxesByKey = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileService $fileService,
        private StatusService $statusService,
        private InvoiceTaxXmlParser $xmlParser,
        private InvoiceTaxPartyResolver $partyResolver
    ) {
    }

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
        if (trim((string) $content) === '') {
            throw new \RuntimeException('Conteudo do arquivo de importacao esta vazio.');
        }

        $statusOpen = $this->statusService->discoveryStatus('open', 'open', 'invoice_tax');
        $importedCount = 0;
        $reusedCount = 0;
        $ignoredCount = 0;

        foreach ($this->xmlParser->extractXmlEntries((string) $content, $file->getFileName() ?: 'invoice_tax.xml') as $entry) {
            $parsed = $this->xmlParser->parseNfeXml($entry['content']);
            if (!$parsed) {
                $ignoredCount++;
                continue;
            }

            $existing = $this->findInvoiceTax($import->getPeople(), $parsed['key'] ?? '', $parsed['number'] ?? null);
            if ($existing instanceof InvoiceTax && $this->belongsToOtherCompany($existing, $import->getPeople())) {
                $ignoredCount++;
                continue;
            }

            $this->persistInvoiceTax($import->getPeople(), $entry['name'], $entry['content'], $parsed, $statusOpen);
            if ($existing instanceof InvoiceTax) {
                $reusedCount++;
            } else {
                $importedCount++;
            }
        }

        $this->entityManager->flush();

        $import->setFeedback(sprintf(
            '%d nota(s) fiscal(is) importada(s); %d ja existente(s) reutilizada(s); %d arquivo(s) ignorado(s).',
            $importedCount,
            $reusedCount,
            $ignoredCount
        ));
    }

    public function importXmlContent(?People $company, string $filename, string $xmlContent): ?InvoiceTax
    {
        $parsed = $this->xmlParser->parseNfeXml($xmlContent);
        if (!$parsed) {
            return null;
        }

        $existing = $this->findInvoiceTax($company, $parsed['key'] ?? '', $parsed['number'] ?? null);
        if ($existing instanceof InvoiceTax && $this->belongsToOtherCompany($existing, $company)) {
            throw new \RuntimeException('NF-e ja importada por outra empresa; chave nao pode ser reatribuida.');
        }

        $invoiceTax = $this->persistInvoiceTax(
            $company,
            $this->xmlParser->xmlFileName($filename),
            $xmlContent,
            $parsed,
            $this->statusService->discoveryStatus('open', 'open', 'invoice_tax')
        );

        $this->entityManager->flush();

        return $invoiceTax;
    }

    public function parseNfeXml(string $xmlContent): ?array
    {
        return $this->xmlParser->parseNfeXml($xmlContent);
    }

    public function belongsToOtherCompany(InvoiceTax $invoiceTax, ?People $company): bool
    {
        $owner = $invoiceTax->getCompany();
        if (!$owner instanceof People || !$company instanceof People) {
            return false;
        }

        if ($owner === $company) {
            return false;
        }

        $ownerId = $owner->getId();
        $companyId = $company->getId();

        return $ownerId !== null && $companyId !== null && $ownerId !== $companyId;
    }

    /**
     * Lookup scoped to the importing company. A global unique invoice_key may
     * already exist for another tenant — that row must not be mutated.
     */
    public function findInvoiceTax(?People $company, ?string $key, mixed $number): ?InvoiceTax
    {
        $key = trim((string) $key);
        $cacheKey = $this->cacheKey($company, $key !== '' ? $key : (string) (int) $number);

        if ($cacheKey !== '' && isset($this->invoiceTaxesByKey[$cacheKey])) {
            $cached = $this->invoiceTaxesByKey[$cacheKey];
            if (!$this->belongsToOtherCompany($cached, $company)) {
                return $cached;
            }
        }

        if ($key !== '') {
            $found = $this->entityManager->getRepository(InvoiceTax::class)->findOneBy(['invoiceKey' => $key]);
            if ($found instanceof InvoiceTax) {
                if ($this->belongsToOtherCompany($found, $company)) {
                    return $found;
                }
                $this->invoiceTaxesByKey[$this->cacheKey($company, $key)] = $found;

                return $found;
            }
        }

        $number = (int) $number;
        if ($number > 0 && $company instanceof People) {
            return $this->entityManager->getRepository(InvoiceTax::class)->findOneBy([
                'invoiceNumber' => $number,
                'company' => $company,
            ]);
        }

        return null;
    }

    private function persistInvoiceTax(
        ?People $company,
        string $filename,
        string $xmlContent,
        array $parsed,
        mixed $statusOpen
    ): InvoiceTax {
        $providerData = is_array($parsed['provider'] ?? null) ? $parsed['provider'] : null;
        $clientData = is_array($parsed['client'] ?? null) ? $parsed['client'] : null;
        $carrierData = is_array($parsed['carrier'] ?? null) ? $parsed['carrier'] : null;

        $provider = $this->partyResolver->resolveParty($providerData);
        $client = $this->partyResolver->resolveParty($clientData);
        $carrier = $this->partyResolver->resolveParty($carrierData);
        $this->entityManager->flush();

        $providerAddress = $this->partyResolver->resolvePartyAddress($provider, $providerData);
        $clientAddress = $this->partyResolver->resolvePartyAddress($client, $clientData);
        $carrierAddress = $this->partyResolver->resolvePartyAddress($carrier, $carrierData);

        if ($company instanceof People) {
            $this->partyResolver->ensureLink($company, $client, 'client');
            $this->partyResolver->ensureLink($company, $provider, 'provider');
            $this->partyResolver->ensureLink($company, $carrier, 'provider');
        }

        $invoiceTax = $this->findInvoiceTax($company, $parsed['key'] ?? '', $parsed['number'] ?? null);
        if ($invoiceTax instanceof InvoiceTax && $this->belongsToOtherCompany($invoiceTax, $company)) {
            throw new \RuntimeException('NF-e ja importada por outra empresa; chave nao pode ser reatribuida.');
        }
        if (!$invoiceTax instanceof InvoiceTax) {
            $invoiceTax = new InvoiceTax();
        }

        if (!$invoiceTax->getFile()) {
            $xmlFile = $this->fileService->addFile(
                $company,
                $xmlContent,
                'invoice_tax',
                $filename,
                'application',
                'xml',
                false
            );
            $invoiceTax->setFile($xmlFile);
        }

        $invoiceTax->setInvoice($xmlContent);
        $invoiceTax->setInvoiceKey($parsed['key'] ?? '');
        $invoiceTax->setInvoiceNumber((int) ($parsed['number'] ?? 0));
        $invoiceTax->setInvoiceModel($parsed['model'] ?? null);
        $invoiceTax->setInvoiceTotal($parsed['total'] ?? null);
        $invoiceTax->setStatus($statusOpen);
        $invoiceTax->setCompany($company instanceof People ? $company : null);
        $invoiceTax->setProvider($provider);
        $invoiceTax->setIssuer($provider);
        $invoiceTax->setClient($client);
        $invoiceTax->setCarrier($carrier);
        $invoiceTax->setProviderAddress($providerAddress);
        $invoiceTax->setClientAddress($clientAddress);
        $invoiceTax->setCarrierAddress($carrierAddress);
        $invoiceTax->setAddress($clientAddress ?? $providerAddress ?? $carrierAddress);
        $this->entityManager->persist($invoiceTax);
        $this->entityManager->flush();

        $key = trim((string) ($parsed['key'] ?? ''));
        if ($key !== '') {
            $this->invoiceTaxesByKey[$this->cacheKey($company, $key)] = $invoiceTax;
        }

        return $invoiceTax;
    }

    private function cacheKey(?People $company, string $key): string
    {
        if ($key === '') {
            return '';
        }

        $companyId = $company instanceof People && $company->getId() !== null ? (string) $company->getId() : 'none';

        return $companyId . ':' . $key;
    }
}
