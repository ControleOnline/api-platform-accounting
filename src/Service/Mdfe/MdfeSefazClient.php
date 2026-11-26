<?php

namespace ControleOnline\Service\Mdfe;

use NFePHP\Common\Certificate;
use NFePHP\MDFe\Common\Standardize;
use NFePHP\MDFe\Tools;

class MdfeSefazClient
{
    /**
     * @return array{xml: string, key: ?string, authorized: bool, raw: mixed}
     */
    public function signAndSend(string $xml, array $fiscal): array
    {
        $certificate = $fiscal['certificateBinary'] ?? null;
        $password = (string) ($fiscal['receita-federal-certificate-password'] ?? '');
        if (!is_string($certificate) || $certificate === '' || $password === '') {
            throw new \RuntimeException('Certificado fiscal ou senha ausentes na configuração da empresa.');
        }
        if (!class_exists(Certificate::class) || !class_exists(Tools::class)) {
            throw new \RuntimeException('Pacote nfephp-org/sped-mdfe não está disponível neste runtime.');
        }

        $cert = Certificate::readPfx($certificate, $password);
        $configJson = json_encode([
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (int) ($fiscal['receita-federal-environment'] ?? 2),
            'razaosocial' => (string) ($fiscal['certificateName'] ?? $fiscal['razaosocial'] ?? ''),
            'cnpj' => preg_replace('/\D+/', '', (string) ($fiscal['certificateDocument'] ?? $fiscal['cnpj'] ?? '')),
            'siglaUF' => (string) ($fiscal['uf'] ?? 'SP'),
            'schemes' => 'PL_MDFe_300',
            'versao' => '3.00',
        ], JSON_UNESCAPED_UNICODE);

        $tools = new Tools($configJson, $cert);
        if (method_exists($tools, 'model')) {
            $tools->model('58');
        }

        $signed = method_exists($tools, 'signMDFe')
            ? $tools->signMDFe($xml)
            : $tools->signCTe($xml);

        $response = $tools->sefazEnviaLote([$signed], str_pad((string) time(), 15, '0', STR_PAD_LEFT), 1);
        $std = class_exists(Standardize::class)
            ? (new Standardize($response))->toStd()
            : json_decode(json_encode($response));

        $cStat = (string) ($std->cStat ?? $std->protMDFe->infProt->cStat ?? '');
        $authorized = in_array($cStat, ['100', '104'], true);
        $authorizedXml = $signed;
        $key = null;
        if (isset($std->protMDFe->infProt->chMDFe)) {
            $key = (string) $std->protMDFe->infProt->chMDFe;
        }

        if ($authorized && method_exists($tools, 'addProtocolo')) {
            try {
                $authorizedXml = $tools->addProtocolo($signed, $response, true);
            } catch (\Throwable) {
                $authorizedXml = $signed;
            }
        }

        return [
            'xml' => $authorizedXml,
            'key' => $key,
            'authorized' => $authorized,
            'raw' => $std,
        ];
    }
}
