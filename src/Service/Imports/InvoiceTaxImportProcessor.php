<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\Cep;
use ControleOnline\Entity\City;
use ControleOnline\Entity\District;
use ControleOnline\Entity\Document;
use ControleOnline\Entity\DocumentType;
use ControleOnline\Entity\Import;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\Language;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\State;
use ControleOnline\Entity\Street;
use ControleOnline\Service\FileService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use SimpleXMLElement;

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
        if (trim((string) $content) === '') {
            throw new \RuntimeException('Conteudo do arquivo de importacao esta vazio.');
        }

        $statusOpen = $this->statusService->discoveryStatus('open', 'open', 'invoice_tax');
        $importedCount = 0;
        $ignoredCount = 0;

        foreach ($this->extractXmlEntries((string) $content, $file->getFileName() ?: 'invoice_tax.xml') as $entry) {
            $parsed = $this->parseNfeXml($entry['content']);
            if (!$parsed) {
                $ignoredCount++;
                continue;
            }

            $this->persistInvoiceTax($import->getPeople(), $entry['name'], $entry['content'], $parsed, $statusOpen);
            $importedCount++;
        }

        $this->entityManager->flush();

        $import->setFeedback(sprintf(
            '%d nota(s) fiscal(is) importada(s); %d arquivo(s) ignorado(s).',
            $importedCount,
            $ignoredCount
        ));
    }

    public function importXmlContent(?People $company, string $filename, string $xmlContent): ?InvoiceTax
    {
        $parsed = $this->parseNfeXml($xmlContent);
        if (!$parsed) {
            return null;
        }

        $invoiceTax = $this->persistInvoiceTax(
            $company,
            $this->xmlFileName($filename),
            $xmlContent,
            $parsed,
            $this->statusService->discoveryStatus('open', 'open', 'invoice_tax')
        );

        $this->entityManager->flush();

        return $invoiceTax;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseNfeXml(string $xmlContent): ?array
    {
        try {
            $useErrors = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);

            if (!$xml instanceof SimpleXMLElement) {
                return null;
            }

            $infNFe = $this->firstXPath($xml, '//*[local-name()="infNFe"]');
            if (!$infNFe instanceof SimpleXMLElement) {
                return null;
            }

            $attributes = $infNFe->attributes();
            $rawId = (string) ($attributes['Id'] ?? '');
            $key = preg_replace('/^NFe/i', '', $rawId) ?: '';
            $protocolKey = $this->text($this->firstXPath($xml, '//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="chNFe"]'));
            $number = $this->text($this->child($this->child($infNFe, 'ide'), 'nNF'));

            if ($key === '' && $protocolKey !== '') {
                $key = $protocolKey;
            }

            if ($number === '' && $key !== '' && strlen($key) >= 34) {
                $number = (string) (int) substr($key, 25, 9);
            }

            if ($key === '' && $number === '') {
                return null;
            }

            $ide = $this->child($infNFe, 'ide');
            $total = $this->child($this->child($this->child($infNFe, 'total'), 'ICMSTot'), 'vNF');

            return [
                'key' => $key,
                'number' => $number !== '' ? $number : '0',
                'model' => $this->text($this->child($ide, 'mod')) ?: null,
                'total' => $this->text($total) ?: null,
                'provider' => $this->parseParty($this->child($infNFe, 'emit'), 'enderEmit'),
                'client' => $this->parseParty($this->child($infNFe, 'dest'), 'enderDest'),
                'carrier' => $this->parseCarrier($infNFe),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{name: string, content: string}>
     */
    private function extractXmlEntries(string $content, string $fallbackName): array
    {
        if ($this->looksLikeXml($content)) {
            return [[
                'name' => $this->xmlFileName($fallbackName),
                'content' => $content,
            ]];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'nfe_zip_');
        if ($tempPath === false) {
            throw new \RuntimeException('Nao foi possivel criar arquivo temporario para importacao.');
        }

        file_put_contents($tempPath, $content);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Nao foi possivel abrir o arquivo ZIP ou XML informado.');
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'] ?? '';

            if ($filename === '' || str_ends_with($filename, '/')) {
                continue;
            }

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $xmlContent = $zip->getFromIndex($i);
            if (is_string($xmlContent) && trim($xmlContent) !== '') {
                $entries[] = [
                    'name' => basename($filename),
                    'content' => $xmlContent,
                ];
            }
        }

        $zip->close();
        @unlink($tempPath);

        return $entries;
    }

    /**
     * @param array<string, mixed> $parsed
     */
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

        $provider = $this->resolveParty($providerData);
        $client = $this->resolveParty($clientData);
        $carrier = $this->resolveParty($carrierData);

        $providerAddress = $this->resolvePartyAddress($provider, $providerData);
        $clientAddress = $this->resolvePartyAddress($client, $clientData);
        $carrierAddress = $this->resolvePartyAddress($carrier, $carrierData);

        if ($company instanceof People) {
            $this->ensureLink($company, $client, 'client');
            $this->ensureLink($company, $provider, 'provider');
            $this->ensureLink($company, $carrier, 'provider');
        }

        $invoiceTax = $this->findInvoiceTax($parsed['key'] ?? '', $parsed['number'] ?? null) ?? new InvoiceTax();

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
        $invoiceTax->setAddress($clientAddress ?? $providerAddress ?? $carrierAddress);

        $this->entityManager->persist($invoiceTax);

        return $invoiceTax;
    }

    private function findInvoiceTax(?string $key, mixed $number): ?InvoiceTax
    {
        $key = trim((string) $key);
        if ($key !== '') {
            return $this->entityManager->getRepository(InvoiceTax::class)->findOneBy(['invoiceKey' => $key]);
        }

        $number = (int) $number;
        if ($number > 0) {
            return $this->entityManager->getRepository(InvoiceTax::class)->findOneBy(['invoiceNumber' => $number]);
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $party
     */
    private function resolveParty(?array $party): ?People
    {
        if (!$party || ($party['document'] ?? '') === '') {
            return null;
        }

        $documentNumber = $this->digits($party['document']);
        $documentType = strlen($documentNumber) > 11 ? 'CNPJ' : 'CPF';
        $document = $this->entityManager->getRepository(Document::class)->findOneBy([
            'document' => $documentNumber,
            'documentType' => $this->resolveDocumentType($documentType),
        ]);

        if ($document instanceof Document) {
            $people = $document->getPeople();
            if (($party['name'] ?? '') !== '' && $people->getName() === 'NAME NOT GIVEN') {
                $people->setName($party['name']);
            }

            return $people;
        }

        $people = new People();
        $people->setName($party['name'] ?: 'Name not given');
        $people->setAlias('');
        $people->setEnabled(true);
        $people->setPeopleType($documentType === 'CNPJ' ? 'J' : 'F');
        $people->setLanguage($this->resolveDefaultLanguage());

        if (($party['stateRegistration'] ?? '') !== '') {
            $people->addOtherInformations('stateRegistration', $party['stateRegistration']);
        }

        $this->entityManager->persist($people);

        $document = new Document();
        $document->setDocument((int) $documentNumber);
        $document->setDocumentType($this->resolveDocumentType($documentType));
        $document->setPeople($people);

        $this->entityManager->persist($document);

        return $people;
    }

    private function resolveDefaultLanguage(): ?Language
    {
        return $this->entityManager->getRepository(Language::class)->findOneBy(['language' => 'pt-br']);
    }

    private function resolveDocumentType(string $documentType): DocumentType
    {
        $documentTypeEntity = $this->entityManager->getRepository(DocumentType::class)->findOneBy([
            'documentType' => $documentType,
        ]);

        if ($documentTypeEntity instanceof DocumentType) {
            return $documentTypeEntity;
        }

        $documentTypeEntity = new DocumentType();
        $documentTypeEntity->setDocumentType($documentType);
        $documentTypeEntity->setPeopleType($documentType === 'CNPJ' ? 'J' : 'F');
        $this->entityManager->persist($documentTypeEntity);

        return $documentTypeEntity;
    }

    /**
     * @param array<string, mixed>|null $party
     */
    private function resolvePartyAddress(?People $people, ?array $party): ?Address
    {
        if (!$people instanceof People || !is_array($party['address'] ?? null)) {
            return null;
        }

        return $this->resolveAddress($people, $party['address']);
    }

    /**
     * @param array<string, string> $data
     */
    private function resolveAddress(People $people, array $data): ?Address
    {
        $postalCode = $this->digits($data['postalCode'] ?? '');
        $streetName = trim((string) ($data['street'] ?? ''));
        $districtName = trim((string) ($data['district'] ?? ''));
        $cityName = trim((string) ($data['city'] ?? ''));
        $stateUf = strtoupper(trim((string) ($data['state'] ?? '')));

        if ($postalCode === '' || $streetName === '' || $districtName === '' || $cityName === '' || $stateUf === '') {
            return null;
        }

        $state = $this->entityManager->getRepository(State::class)->findOneBy(['uf' => $stateUf]);
        if (!$state instanceof State) {
            return null;
        }

        $city = $this->entityManager->getRepository(City::class)->findOneBy([
            'city' => $cityName,
            'state' => $state,
        ]);

        if (!$city instanceof City && ($data['cityCode'] ?? '') !== '') {
            $city = $this->entityManager->getRepository(City::class)->findOneBy([
                'cod_ibge' => (int) $data['cityCode'],
            ]);
        }

        if (!$city instanceof City) {
            $city = new City();
            $city->setCity($cityName);
            $city->setState($state);
            $city->setIbge(($data['cityCode'] ?? '') !== '' ? (int) $data['cityCode'] : null);
            $city->setSeo(false);
            $this->entityManager->persist($city);
        }

        $district = $this->entityManager->getRepository(District::class)->findOneBy([
            'district' => $districtName,
            'city' => $city,
        ]);

        if (!$district instanceof District) {
            $district = new District();
            $district->setDistrict($districtName);
            $district->setCity($city);
            $this->entityManager->persist($district);
        }

        $cep = $this->entityManager->getRepository(Cep::class)->findOneBy(['cep' => (int) $postalCode]);
        if (!$cep instanceof Cep) {
            $cep = new Cep();
            $cep->setCep((int) $postalCode);
            $this->entityManager->persist($cep);
        }

        $street = $this->entityManager->getRepository(Street::class)->findOneBy([
            'street' => $streetName,
            'district' => $district,
        ]);

        if (!$street instanceof Street) {
            $street = new Street();
            $street->setStreet($streetName);
            $street->setDistrict($district);
            $street->setCep($cep);
            $street->setConfirmed(true);
            $this->entityManager->persist($street);
        }

        $number = $this->normalizeAddressNumber($data['number'] ?? '');
        $complement = trim((string) ($data['complement'] ?? ''));
        $address = $this->entityManager->getRepository(Address::class)->findOneBy([
            'people' => $people,
            'street' => $street,
            'number' => $number,
            'complement' => $complement,
        ]);

        if ($address instanceof Address) {
            return $address;
        }

        $address = new Address();
        $address->setPeople($people);
        $address->setStreet($street);
        $address->setNumber($number);
        $address->setComplement($complement);
        $address->setNickname('NF-e');
        $address->setLatitude(0);
        $address->setLongitude(0);
        $address->setLocator('');
        $address->setOpeningTime(null);
        $address->setClosingTime(null);
        $address->setSearchFor('');

        $this->entityManager->persist($address);

        return $address;
    }

    private function ensureLink(People $company, ?People $people, string $linkType): void
    {
        if (!$people instanceof People || $company === $people) {
            return;
        }

        $link = $this->entityManager->getRepository(PeopleLink::class)->findOneBy([
            'company' => $company,
            'people' => $people,
            'linkType' => $linkType,
        ]);

        if ($link instanceof PeopleLink) {
            return;
        }

        $link = new PeopleLink();
        $link->setCompany($company);
        $link->setPeople($people);
        $link->setLinkType($linkType);
        $link->setEnabled(true);

        $this->entityManager->persist($link);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseParty(?SimpleXMLElement $party, string $addressTag): ?array
    {
        if (!$party instanceof SimpleXMLElement) {
            return null;
        }

        $document = $this->text($this->child($party, 'CNPJ')) ?: $this->text($this->child($party, 'CPF'));
        if ($document === '') {
            return null;
        }

        return [
            'document' => $this->digits($document),
            'name' => $this->text($this->child($party, 'xNome')),
            'stateRegistration' => $this->text($this->child($party, 'IE')),
            'address' => $this->parseAddress($this->child($party, $addressTag)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseCarrier(SimpleXMLElement $infNFe): ?array
    {
        $carrier = $this->child($this->child($infNFe, 'transp'), 'transporta');

        return $this->parseParty($carrier, 'enderTransp');
    }

    /**
     * @return array<string, string>
     */
    private function parseAddress(?SimpleXMLElement $address): array
    {
        if (!$address instanceof SimpleXMLElement) {
            return [];
        }

        return [
            'street' => $this->text($this->child($address, 'xLgr')),
            'number' => $this->text($this->child($address, 'nro')),
            'complement' => $this->text($this->child($address, 'xCpl')),
            'district' => $this->text($this->child($address, 'xBairro')),
            'cityCode' => $this->text($this->child($address, 'cMun')),
            'city' => $this->text($this->child($address, 'xMun')),
            'state' => $this->text($this->child($address, 'UF')),
            'postalCode' => $this->text($this->child($address, 'CEP')),
            'countryCode' => $this->text($this->child($address, 'cPais')),
            'country' => $this->text($this->child($address, 'xPais')),
        ];
    }

    private function child(?SimpleXMLElement $node, string $name): ?SimpleXMLElement
    {
        if (!$node instanceof SimpleXMLElement) {
            return null;
        }

        foreach ($node->children() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }

        foreach ($node->getNamespaces(true) as $namespace) {
            foreach ($node->children($namespace) as $child) {
                if ($child->getName() === $name) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function firstXPath(SimpleXMLElement $xml, string $path): ?SimpleXMLElement
    {
        $nodes = $xml->xpath($path);

        return is_array($nodes) && $nodes !== [] && $nodes[0] instanceof SimpleXMLElement ? $nodes[0] : null;
    }

    private function text(?SimpleXMLElement $node): string
    {
        return trim((string) $node);
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function looksLikeXml(string $content): bool
    {
        return str_starts_with(ltrim($content), '<');
    }

    private function xmlFileName(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml' ? $filename : $filename . '.xml';
    }

    private function normalizeAddressNumber(mixed $number): int
    {
        $digits = $this->digits($number);

        return $digits === '' ? 0 : (int) $digits;
    }
}
