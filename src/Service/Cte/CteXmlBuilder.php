<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;

class CteXmlBuilder
{
    public function build(array $invoices, array $fiscal, string $cfop, array $extra = []): string
    {
        if ($invoices === []) {
            throw new \InvalidArgumentException('Nenhuma NF para montar o CT-e.');
        }

        /** @var InvoiceTax $first */
        $first = $invoices[0];
        $company = $first->getCompany() ?: $first->getIssuer();
        $serie = (int) ($fiscal['receita-federal-cte-serie'] ?? 1);
        $number = (int) ($fiscal['nextNumber'] ?? 1);
        $tpAmb = (string) ($fiscal['receita-federal-environment'] ?? '2');
        $cUF = substr((string) ($fiscal['receita-federal-ibge-code'] ?? '3550308'), 0, 2);
        $dhEmi = date('Y-m-d\TH:i:sP');
        $cnpj = $this->peopleDocument($company);
        $mod = '57';
        $tpEmis = '1';
        $cCT = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $chave43 = sprintf(
            '%02d%02d%02d%s%02d%03d%09d%01d%s',
            (int) $cUF,
            (int) date('y'),
            (int) date('m'),
            str_pad(preg_replace('/\D+/', '', $cnpj) ?: '00000000000000', 14, '0', STR_PAD_LEFT),
            (int) $mod,
            $serie,
            $number,
            (int) $tpEmis,
            $cCT
        );
        $cDV = $this->checkDigit($chave43);
        $chave = $chave43 . $cDV;

        $total = 0.0;
        $infNFe = '';
        foreach ($invoices as $invoice) {
            if (!$invoice instanceof InvoiceTax) {
                continue;
            }
            $total += (float) ($invoice->getInvoiceTotal() ?? 0);
            $key = preg_replace('/\D+/', '', (string) $invoice->getInvoiceKey());
            if ($key !== '') {
                $infNFe .= sprintf('<infNFe><chave>%s</chave></infNFe>', htmlspecialchars($key, ENT_XML1));
            }
        }

        $emit = $this->partyXml('emit', $company, $first->getProviderAddress() ?: $first->getAddress(), $fiscal);
        $rem = $this->partyXml('rem', $first->getProvider() ?: $first->getIssuer(), $first->getProviderAddress(), $fiscal);
        $dest = $this->partyXml('dest', $first->getClient(), $first->getClientAddress() ?: $first->getAddress(), $fiscal);
        $enderToma = $this->enderXml($first->getClientAddress() ?: $first->getAddress(), $fiscal);
        $natOp = htmlspecialchars((string) ($extra['natOp'] ?? 'PRESTACAO DE SERVICO DE TRANSPORTE'), ENT_XML1);
        $vTPrest = number_format($total, 2, '.', '');
        $munEnv = htmlspecialchars((string) ($extra['xMunEnv'] ?? $this->cityName($first->getAddress())), ENT_XML1);
        $ufEnv = htmlspecialchars((string) ($extra['UFEnv'] ?? $this->uf($first->getAddress())), ENT_XML1);
        $cMunEnv = htmlspecialchars((string) ($fiscal['receita-federal-ibge-code'] ?? '3550308'), ENT_XML1);
        $rntrc = preg_replace('/\D+/', '', (string) ($extra['rntrc'] ?? $fiscal['rntrc'] ?? $fiscal['receita-federal-cte-rntrc'] ?? '00000000')) ?: '00000000';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<CTe xmlns="http://www.portalfiscal.inf.br/cte">'
            . sprintf('<infCte Id="CTe%s" versao="4.00">', $chave)
            . '<ide>'
            . sprintf('<cUF>%s</cUF><cCT>%s</cCT><CFOP>%s</CFOP><natOp>%s</natOp>', $cUF, $cCT, htmlspecialchars($cfop, ENT_XML1), $natOp)
            . sprintf('<mod>%s</mod><serie>%d</serie><nCT>%d</nCT><dhEmi>%s</dhEmi>', $mod, $serie, $number, $dhEmi)
            . sprintf('<tpImp>1</tpImp><tpEmis>%s</tpEmis><cDV>%s</cDV><tpAmb>%s</tpAmb>', $tpEmis, $cDV, $tpAmb)
            . '<tpCTe>0</tpCTe><procEmi>0</procEmi><verProc>controleonline-1.0</verProc>'
            . sprintf('<cMunEnv>%s</cMunEnv><xMunEnv>%s</xMunEnv><UFEnv>%s</UFEnv>', $cMunEnv, $munEnv, $ufEnv)
            . '<modal>01</modal><tpServ>0</tpServ>'
            . sprintf('<cMunIni>%s</cMunIni><xMunIni>%s</xMunIni><UFIni>%s</UFIni>', $cMunEnv, $munEnv, $ufEnv)
            . sprintf('<cMunFim>%s</cMunFim><xMunFim>%s</xMunFim><UFFim>%s</UFFim>', $cMunEnv, $munEnv, $ufEnv)
            . '<retira>1</retira><indIEToma>1</indIEToma>'
            . '</ide>'
            . '<compl><xObs>CTe gerado via ControleOnline</xObs></compl>'
            . $emit
            . $rem
            . $dest
            . sprintf('<vPrest><vTPrest>%s</vTPrest><vRec>%s</vRec></vPrest>', $vTPrest, $vTPrest)
            . '<imp><ICMS><ICMS00><CST>00</CST><vBC>0.00</vBC><pICMS>0.00</pICMS><vICMS>0.00</vICMS></ICMS00></ICMS></imp>'
            . '<infCTeNorm><infCarga><vCarga>' . $vTPrest . '</vCarga><proPred>MERCADORIA</proPred></infCarga>'
            . '<infDoc>' . $infNFe . '</infDoc><infModal version="4.00"><rodo><RNTRC>' . htmlspecialchars($rntrc, ENT_XML1) . '</RNTRC></rodo></infModal></infCTeNorm>'
            . '</infCte></CTe>';
    }

    public function extractKey(string $xml): ?string
    {
        if (preg_match('/Id="CTe([0-9]{44})"/', $xml, $match)) {
            return $match[1];
        }
        if (preg_match('/<chCTe>([0-9]{44})<\/chCTe>/', $xml, $match)) {
            return $match[1];
        }

        return null;
    }

    public function extractNumber(string $xml): int
    {
        if (preg_match('/<nCT>(\d+)<\/nCT>/', $xml, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    public function checkDigit(string $chave43): string
    {
        $weights = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0;
        $weightIndex = 0;
        for ($i = strlen($chave43) - 1; $i >= 0; $i--) {
            $sum += ((int) $chave43[$i]) * $weights[$weightIndex];
            $weightIndex = ($weightIndex + 1) % count($weights);
        }
        $resto = $sum % 11;

        return ($resto === 0 || $resto === 1) ? '0' : (string) (11 - $resto);
    }

    private function partyXml(string $tag, ?People $people, ?Address $address, array $fiscal): string
    {
        $doc = $this->peopleDocument($people);
        $isCpf = strlen($doc) === 11;
        $docTag = $isCpf
            ? '<CPF>' . htmlspecialchars($doc, ENT_XML1) . '</CPF>'
            : '<CNPJ>' . htmlspecialchars(str_pad($doc ?: '00000000000000', 14, '0', STR_PAD_LEFT), ENT_XML1) . '</CNPJ>';
        $name = htmlspecialchars((string) ($people?->getName() ?: $people?->getAlias() ?: 'SEM NOME'), ENT_XML1);

        return sprintf('<%s>%s<xNome>%s</xNome>%s</%s>', $tag, $docTag, $name, $this->enderXml($address, $fiscal, false), $tag);
    }

    private function enderXml(?Address $address, array $fiscal, bool $wrapToma = true): string
    {
        $inner = sprintf(
            '<xLgr>%s</xLgr><nro>%s</nro><xBairro>%s</xBairro><cMun>%s</cMun><xMun>%s</xMun><CEP>%s</CEP><UF>%s</UF><cPais>1058</cPais><xPais>Brasil</xPais>',
            htmlspecialchars($this->streetName($address), ENT_XML1),
            htmlspecialchars($this->streetNumber($address), ENT_XML1),
            htmlspecialchars($this->districtName($address), ENT_XML1),
            htmlspecialchars((string) ($fiscal['receita-federal-ibge-code'] ?? '3550308'), ENT_XML1),
            htmlspecialchars($this->cityName($address), ENT_XML1),
            htmlspecialchars($this->postalCode($address), ENT_XML1),
            htmlspecialchars($this->uf($address), ENT_XML1)
        );

        return $wrapToma ? '<enderToma>' . $inner . '</enderToma>' : '<ender>' . $inner . '</ender>';
    }

    private function peopleDocument(?People $people): string
    {
        if ($people === null) {
            return '';
        }
        if (method_exists($people, 'getOneDocument')) {
            $document = $people->getOneDocument();
            if (is_object($document) && method_exists($document, 'getDocument')) {
                return preg_replace('/\D+/', '', (string) $document->getDocument()) ?: '';
            }
        }

        return '';
    }

    private function streetName(?Address $address): string
    {
        $street = $address && method_exists($address, 'getStreet') ? $address->getStreet() : null;
        if (is_object($street) && method_exists($street, 'getStreet')) {
            return (string) ($street->getStreet() ?: 'RUA');
        }

        return 'RUA';
    }

    private function streetNumber(?Address $address): string
    {
        if ($address && method_exists($address, 'getNumber')) {
            return (string) ($address->getNumber() ?: 'S/N');
        }

        return 'S/N';
    }

    private function districtName(?Address $address): string
    {
        $street = $address && method_exists($address, 'getStreet') ? $address->getStreet() : null;
        $district = is_object($street) && method_exists($street, 'getDistrict') ? $street->getDistrict() : null;
        if (is_object($district) && method_exists($district, 'getDistrict')) {
            return (string) ($district->getDistrict() ?: 'CENTRO');
        }

        return 'CENTRO';
    }

    private function cityName(?Address $address): string
    {
        $street = $address && method_exists($address, 'getStreet') ? $address->getStreet() : null;
        $district = is_object($street) && method_exists($street, 'getDistrict') ? $street->getDistrict() : null;
        $city = is_object($district) && method_exists($district, 'getCity') ? $district->getCity() : null;
        if (is_object($city) && method_exists($city, 'getCity')) {
            return (string) ($city->getCity() ?: 'SAO PAULO');
        }

        return 'SAO PAULO';
    }

    private function uf(?Address $address): string
    {
        $street = $address && method_exists($address, 'getStreet') ? $address->getStreet() : null;
        $district = is_object($street) && method_exists($street, 'getDistrict') ? $street->getDistrict() : null;
        $city = is_object($district) && method_exists($district, 'getCity') ? $district->getCity() : null;
        $state = is_object($city) && method_exists($city, 'getState') ? $city->getState() : null;
        if (is_object($state) && method_exists($state, 'getUf')) {
            return strtoupper((string) ($state->getUf() ?: 'SP'));
        }

        return 'SP';
    }

    private function postalCode(?Address $address): string
    {
        $street = $address && method_exists($address, 'getStreet') ? $address->getStreet() : null;
        $cep = is_object($street) && method_exists($street, 'getCep') ? $street->getCep() : null;
        if (is_object($cep) && method_exists($cep, 'getCep')) {
            return preg_replace('/\D+/', '', (string) $cep->getCep()) ?: '00000000';
        }

        return '00000000';
    }
}
