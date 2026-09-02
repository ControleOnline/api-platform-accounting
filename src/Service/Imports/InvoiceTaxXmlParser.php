<?php

namespace ControleOnline\Service\Imports;

use SimpleXMLElement;

class InvoiceTaxXmlParser
{
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

            $infNfse = $this->firstXPath($xml, '//*[local-name()="infNFSe"]');
            if ($infNfse instanceof SimpleXMLElement) {
                return $this->parseNfseXml($xml, $infNfse);
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

    private function parseNfseXml(SimpleXMLElement $xml, SimpleXMLElement $infNfse): ?array
    {
        $attributes = $infNfse->attributes();
        $key = preg_replace('/^NFS/i', '', (string) ($attributes['Id'] ?? '')) ?: '';
        $number = $this->text($this->firstXPath($infNfse, './/*[local-name()="nNFSe"]'));
        $series = $this->text($this->firstXPath($infNfse, './/*[local-name()="serie"]'));
        $total = $this->text($this->firstXPath($infNfse, './/*[local-name()="vServ"]'));
        if ($key === '' || $number === '') {
            return null;
        }

        return [
            'key' => $key,
            'number' => $number,
            'series' => $series,
            'model' => '99',
            'total' => $total ?: null,
            'provider' => $this->parseParty($this->firstXPath($infNfse, './/*[local-name()="prest"]'), ''),
            'client' => $this->parseParty($this->firstXPath($infNfse, './/*[local-name()="toma"]'), ''),
            'carrier' => null,
        ];
    }

    /**
     * @return array<int, array{name: string, content: string}>
     */
    public function extractXmlEntries(string $content, string $fallbackName): array
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

    public function xmlFileName(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml' ? $filename : $filename . '.xml';
    }

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
            'address' => $addressTag !== '' ? $this->parseAddress($this->child($party, $addressTag)) : [],
        ];
    }

    private function parseCarrier(SimpleXMLElement $infNFe): ?array
    {
        $carrier = $this->child($this->child($infNFe, 'transp'), 'transporta');

        if (!$carrier instanceof SimpleXMLElement) {
            return null;
        }

        $document = $this->text($this->child($carrier, 'CNPJ')) ?: $this->text($this->child($carrier, 'CPF'));
        if ($document === '') {
            return null;
        }

        return [
            'document' => $this->digits($document),
            'name' => $this->text($this->child($carrier, 'xNome')),
            'stateRegistration' => $this->text($this->child($carrier, 'IE')),
            'address' => [
                'street' => $this->text($this->child($carrier, 'xEnder')),
                'number' => '0',
                'complement' => '',
                'district' => '',
                'cityCode' => '',
                'city' => $this->text($this->child($carrier, 'xMun')),
                'state' => $this->text($this->child($carrier, 'UF')),
                'postalCode' => '',
                'countryCode' => '',
                'country' => '',
            ],
        ];
    }

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
}
