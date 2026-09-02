<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Order;
use ControleOnline\Library\NFePHP;
use NFePHP\Common\Signer;

final class NfseNationalService extends NFePHP
{
    public function emit(Order $order, string $serviceCode, string $description, string $value): array
    {
        $this->model = '99';
        $serviceCode = preg_replace('/\D+/', '', trim($serviceCode));
        $description = trim($description);
        $value = trim($value);
        if ($serviceCode === '' || $description === '' || $value === '' || (float) $value <= 0) {
            throw new \InvalidArgumentException('Código de serviço, descrição e valor são obrigatórios para NFS-e.');
        }
        if (strlen($serviceCode) !== 6) {
            throw new \InvalidArgumentException('O código nacional de serviço deve conter 6 dígitos.');
        }

        $provider = $order->getProvider();
        $providerDocument = $provider?->getOneDocument();
        $address = $provider?->getAddress()[0] ?? null;
        if (!$providerDocument || !$address) {
            throw new \RuntimeException('Emitente deve possuir CNPJ e endereço para NFS-e.');
        }
        $city = $address->getStreet()->getDistrict()->getCity();
        $cityCode = $this->getCodMunicipio($city->getCity(), $city->getState()->getUf());
        $series = $this->configValue($provider, 'receita-federal-nfse-serie');
        if ($series === null || $series === '') {
            throw new \RuntimeException('A série DPS da NFS-e não está configurada.');
        }
        $series = str_pad($series, 5, '0', STR_PAD_LEFT);
        if (strlen($series) !== 5) {
            throw new \RuntimeException('A série DPS deve conter até 5 dígitos.');
        }
        $number = $this->getLastFiscalNumber($provider);
        $cnpj = preg_replace('/\D+/', '', $providerDocument->getDocument());
        if (strlen($cnpj) !== 14) {
            throw new \RuntimeException('O emitente da NFS-e deve possuir CNPJ válido.');
        }
        $id = sprintf('DPS%s1%s%s%015d', str_pad($cityCode, 7, '0', STR_PAD_LEFT), $cnpj, $series, $number);
        if (strlen($id) !== 45) {
            throw new \RuntimeException('Não foi possível formar o identificador DPS válido.');
        }

        $xml = $this->buildDpsXml($order, $id, $cityCode, $series, $number, $serviceCode, $description, $value);
        $signed = Signer::sign($this->getCertificate($order), $xml, 'infDPS', 'Id', OPENSSL_ALGO_SHA256);
        $signed = iconv('UTF-8', 'UTF-8//IGNORE', $signed);
        $signed = '<?xml version="1.0" encoding="UTF-8"?>' . $signed;
        $response = $this->post($order, $signed);
        $encoded = $response['nfseXmlGZipB64'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            throw new \RuntimeException('A SEFIN não retornou o XML autorizado da NFS-e.');
        }
        $decoded = base64_decode($encoded, true);
        $nfseXml = is_string($decoded) ? gzdecode($decoded) : false;
        if (!is_string($nfseXml) || trim($nfseXml) === '') {
            throw new \RuntimeException('O XML autorizado retornado pela SEFIN é inválido.');
        }

        return ['xml' => $nfseXml, 'response' => $response, 'number' => $number];
    }

    private function buildDpsXml(Order $order, string $id, string $cityCode, string $series, int $number, string $serviceCode, string $description, string $value): string
    {
        $provider = $order->getProvider();
        $client = $order->getClient();
        $clientDocument = $client?->getOneDocument();
        if (!$clientDocument) {
            throw new \RuntimeException('O cliente do pedido deve possuir CPF ou CNPJ para NFS-e.');
        }
        $doc = preg_replace('/\D+/', '', $clientDocument->getDocument());
        $tag = strlen($doc) === 11 ? 'CPF' : 'CNPJ';
        $environment = $this->configValue($provider, 'receita-federal-environment');
        if ($environment === null || !in_array($environment, ['1', '2'], true)) {
            throw new \RuntimeException('O ambiente fiscal do emitente não está configurado.');
        }
        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:sP');
        $numberText = (string) $number;
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $root = $xml->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $root->setAttribute('versao', '1.01');
        $xml->appendChild($root);
        $inf = $xml->createElement('infDPS');
        $inf->setAttribute('Id', $id);
        $root->appendChild($inf);
        foreach ([
            'tpAmb' => $environment,
            'dhEmi' => $now,
            'verAplic' => 'ControleOnline',
            'serie' => $series,
            'nDPS' => $numberText,
            'dCompet' => (new \DateTimeImmutable())->format('Y-m-d'),
            'tpEmit' => '1',
            'cLocEmi' => $cityCode,
        ] as $name => $text) {
            $inf->appendChild($xml->createElement($name, $text));
        }
        $prest = $xml->createElement('prest');
        $prest->appendChild($xml->createElement('CNPJ', preg_replace('/\D+/', '', $provider->getOneDocument()->getDocument())));
        $inf->appendChild($prest);
        $toma = $xml->createElement('toma');
        $toma->appendChild($xml->createElement($tag, $doc));
        $inf->appendChild($toma);
        $serv = $xml->createElement('serv');
        $loc = $xml->createElement('locPrest');
        $loc->appendChild($xml->createElement('cLocPrestacao', $cityCode));
        $serv->appendChild($loc);
        $cserv = $xml->createElement('cServ');
        $cserv->appendChild($xml->createElement('cTribNac', $serviceCode));
        $cserv->appendChild($xml->createElement('xDescServ', $description));
        $serv->appendChild($cserv);
        $inf->appendChild($serv);
        $values = $xml->createElement('valores');
        $service = $xml->createElement('vServPrest');
        $service->appendChild($xml->createElement('vServ', number_format((float) $value, 2, '.', '')));
        $values->appendChild($service);
        $inf->appendChild($values);
        return $xml->saveXML();
    }

    private function post(Order $order, string $xml): array
    {
        $environment = $this->configValue($order->getProvider(), 'receita-federal-environment');
        $base = $environment === '1'
            ? 'https://sefin.nfse.gov.br/SefinNacional'
            : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
        $cert = $this->getCertificate($order);
        $certPath = tempnam(sys_get_temp_dir(), 'nfse-cert-');
        $keyPath = tempnam(sys_get_temp_dir(), 'nfse-key-');
        if (!$certPath || !$keyPath) {
            throw new \RuntimeException('Não foi possível preparar o certificado para a SEFIN.');
        }
        try {
            file_put_contents($certPath, (string) $cert->publicKey);
            file_put_contents($keyPath, (string) $cert->privateKey);
            $body = json_encode(['dpsXmlGZipB64' => base64_encode(gzencode($xml, 9))], JSON_THROW_ON_ERROR);
            $handle = curl_init($base . '/nfse');
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_SSLCERT => $certPath,
                CURLOPT_SSLKEY => $keyPath,
                CURLOPT_TIMEOUT => 60,
            ]);
            $raw = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            $response = is_string($raw) ? json_decode($raw, true) : null;
            if ($error !== '') {
                throw new \RuntimeException('Falha de comunicação com a SEFIN: ' . $error);
            }
            if (!is_array($response)) {
                throw new \RuntimeException('A SEFIN retornou uma resposta inválida (HTTP ' . $status . ').');
            }
            if ($status < 200 || $status >= 300) {
                $message = $response['mensagem'] ?? $response['message'] ?? json_encode($response);
                throw new \RuntimeException('SEFIN rejeitou a NFS-e (HTTP ' . $status . '): ' . $message);
            }
            return $response;
        } finally {
            @unlink($certPath);
            @unlink($keyPath);
        }
    }
}
