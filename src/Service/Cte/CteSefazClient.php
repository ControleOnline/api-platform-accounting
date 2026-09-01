<?php

namespace ControleOnline\Service\Cte;

use NFePHP\Common\Certificate;
use NFePHP\CTe\Common\Standardize;
use NFePHP\CTe\Tools;

class CteSefazClient
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
            throw new \RuntimeException('Pacote nfephp-org/sped-cte não está disponível neste runtime.');
        }

        $cert = Certificate::readPfx($certificate, $password);
        $configJson = json_encode([
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (int) ($fiscal['receita-federal-environment'] ?? 2),
            'razaosocial' => (string) ($fiscal['razaosocial'] ?? ''),
            'cnpj' => preg_replace('/\D+/', '', (string) ($fiscal['cnpj'] ?? '')),
            'siglaUF' => (string) ($fiscal['uf'] ?? 'SP'),
            'schemes' => 'PL_CTe_400',
            'versao' => '4.00',
        ], JSON_UNESCAPED_UNICODE);

        $tools = new Tools($configJson, $cert);
        $tools->model('57');
        $signed = $tools->signCTe($xml);

        $response = $tools->sefazEnviaCTe($signed);
        $std = class_exists(Standardize::class)
            ? (new Standardize($response))->toStd()
            : json_decode(json_encode($response));

        $cStat = (string) ($std->cStat ?? $std->protCTe->infProt->cStat ?? '');
        $authorized = false;
        $authorizedXml = $signed;
        if ($cStat === '100') {
            $authorized = true;
            if (method_exists($tools, 'addProtocolo')) {
                try {
                    $authorizedXml = $tools->addProtocolo($signed, $response, true);
                } catch (\Throwable) {
                    $authorizedXml = $signed;
                }
            }
        } elseif ($cStat === '103' || $cStat === '104') {
            $nRec = $std->infRec->nRec ?? null;
            if ($nRec) {
                $recibo = $tools->sefazConsultaRecibo($nRec);
                $prot = class_exists(Standardize::class)
                    ? (new Standardize($recibo))->toStd()
                    : json_decode(json_encode($recibo));
                $protStat = (string) ($prot->protCTe->infProt->cStat ?? $prot->cStat ?? '');
                $authorized = $protStat === '100';
                if (method_exists($tools, 'addProtocolo')) {
                    try {
                        $authorizedXml = $tools->addProtocolo($signed, $recibo, true);
                    } catch (\Throwable) {
                        $authorizedXml = $signed;
                    }
                }
                if (!$authorized) {
                    throw new \RuntimeException('CT-e não autorizado: ' . $protStat . ' ' . ($prot->protCTe->infProt->xMotivo ?? $prot->xMotivo ?? ''));
                }
            }
        } else {
            throw new \RuntimeException('SEFAZ recusou o CT-e: ' . $cStat . ' ' . ($std->xMotivo ?? $std->protCTe->infProt->xMotivo ?? ''));
        }

        $builder = new CteXmlBuilder();

        return [
            'xml' => $authorizedXml,
            'key' => $builder->extractKey($authorizedXml) ?: $builder->extractKey($xml),
            'authorized' => $authorized,
            'raw' => $std,
        ];
    }
}
