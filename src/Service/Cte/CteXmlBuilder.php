<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use NFePHP\CTe\MakeCTe;

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

        // Use MakeCTe from nfephp/sped-cte for correct schema order and required fields
        $make = new MakeCTe();
        $chave = $this->buildChave($invoices, $fiscal, $cfop);
        // infCte
        $infCte = new \stdClass();
        $infCte->Id = $chave;
        $infCte->versao = '4.00';
        $make->taginfCTe($infCte);

        // ide
        $ide = $this->buildIde($invoices, $fiscal, $cfop, $extra);
        $make->tagide($ide);

        // toma3 - tomador 3 = destinatário
        $toma3 = new \stdClass();
        $toma3->toma = '3';
        $make->tagtoma3($toma3);

        // compl
        $compl = new \stdClass();
        $compl->xObs = 'CTe gerado via ControleOnline';
        $make->tagcompl($compl);

        // emit
        $emit = $this->buildEmit($company, $first->getProviderAddress() ?: $first->getAddress(), $fiscal);
        $make->tagemit($emit);
        $make->tagenderEmit($this->buildEnder($first->getProviderAddress() ?: $first->getAddress()));

        // rem
        $rem = $this->buildParty($first->getProvider() ?: $first->getIssuer(), $first->getProviderAddress(), $fiscal);
        $make->tagrem($rem);
        $make->tagenderReme($this->buildEnder($first->getProviderAddress()));

        // dest
        $dest = $this->buildParty($first->getClient(), $first->getClientAddress() ?: $first->getAddress(), $fiscal);
        $make->tagdest($dest);
        $make->tagenderDest($this->buildEnder($first->getClientAddress() ?: $first->getAddress()));

        // vPrest
        $total = 0.0;
        foreach ($invoices as $inv) {
            $total += (float) ($inv->getInvoiceTotal() ?? 0);
        }
        $vPrest = new \stdClass();
        $vPrest->vTPrest = number_format($total, 2, '.', '');
        $vPrest->vRec = number_format($total, 2, '.', '');
        $make->tagvPrest($vPrest);

        // imp
        $icms = new \stdClass();
        $icms->cst = '00';
        $icms->vBC = '0.00';
        $icms->pICMS = '0.00';
        $icms->vICMS = '0.00';
        $make->tagicms($icms);

        // infCTeNorm
        $make->taginfCTeNorm();
        $infCarga = new \stdClass();
        $infCarga->vCarga = number_format($total, 2, '.', '');
        $infCarga->proPred = 'MERCADORIA';
        $make->taginfCarga($infCarga);
        $infQ = new \stdClass();
        $infQ->cUnid = '01';
        $infQ->tpMed = 'KG';
        $infQ->qCarga = '1';
        $make->taginfQ($infQ);

        foreach ($invoices as $inv) {
            $key = preg_replace('/\D+/', '', (string) $inv->getInvoiceKey());
            if ($key !== '') {
                $infNFe = new \stdClass();
                $infNFe->chave = $key;
                $make->taginfNFe($infNFe);
            }
        }

        $infModal = new \stdClass();
        $infModal->versaoModal = '4.00';
        $make->taginfModal($infModal);
        $rodo = new \stdClass();
        $rntrc = preg_replace('/\D+/', '', (string) ($extra['rntrc'] ?? $fiscal['rntrc'] ?? $fiscal['receita-federal-cte-rntrc'] ?? '00000000')) ?: '00000000';
        $rodo->RNTRC = $rntrc;
        $make->tagrodo($rodo);
        $make->taginfRespTec($this->buildInfRespTec($fiscal));

        try {
            return $make->getXML();
        } catch (\RuntimeException $exception) {
            $errors = $make->getErrors();
            if ($errors !== []) {
                throw new \RuntimeException(
                    $exception->getMessage() . ' ' . json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }

    private function buildChave(array $invoices, array $fiscal, string $cfop): string
    {
        $first = $invoices[0];
        $company = $first->getCompany() ?: $first->getIssuer();
        $cnpj = $this->issuerDocument($company, $fiscal);
        $ibgeCode = $this->ibgeCode($fiscal);
        $cUF = substr($ibgeCode, 0, 2);
        $mod = '57';
        $serie = (int) ($fiscal['receita-federal-cte-serie'] ?? 1);
        $nCT = (int) ($fiscal['nextNumber'] ?? 1);
        $tpEmis = '1';
        $cCT = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $chave43 = sprintf('%02d%02d%02d%s%02d%03d%09d%01d%s', (int) $cUF, (int) date('y'), (int) date('m'), str_pad($cnpj, 14, '0', STR_PAD_LEFT), (int) $mod, $serie, $nCT, (int) $tpEmis, $cCT);
        return $chave43 . $this->checkDigit($chave43);
    }

    private function buildIde(array $invoices, array $fiscal, string $cfop, array $extra): \stdClass
    {
        $first = $invoices[0];
        $ide = new \stdClass();
        $ibgeCode = $this->ibgeCode($fiscal);
        $ide->cUF = substr($ibgeCode, 0, 2);
        $ide->cCT = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $ide->CFOP = $cfop;
        $ide->natOp = $extra['natureza'] ?? 'PRESTACAO DE SERVICO DE TRANSPORTE';
        $ide->mod = '57';
        $ide->serie = (string) ($fiscal['receita-federal-cte-serie'] ?? '1');
        $ide->nCT = (string) ($fiscal['nextNumber'] ?? '1');
        $ide->dhEmi = date('Y-m-d\TH:i:sP');
        $ide->tpImp = '1';
        $ide->tpEmis = '1';
        $ide->cDV = '0';
        $ide->tpAmb = (string) ($fiscal['receita-federal-environment'] ?? '2');
        $ide->tpCTe = '0';
        $ide->procEmi = '0';
        $ide->verProc = 'controleonline-1.0';
        $ide->cMunEnv = $ibgeCode;
        $ide->xMunEnv = $this->cityName($first->getAddress());
        $ide->UFEnv = $this->uf($first->getAddress());
        $ide->modal = '01';
        $ide->tpServ = '0';
        $ide->cMunIni = $ide->cMunEnv;
        $ide->xMunIni = $ide->xMunEnv;
        $ide->UFIni = $ide->UFEnv;
        $ide->cMunFim = $ide->cMunEnv;
        $ide->xMunFim = $ide->xMunEnv;
        $ide->UFFim = $ide->UFEnv;
        $ide->retira = '1';
        $ide->indIEToma = $this->tomadorIeIndicator($first->getClient());
        return $ide;
    }

    private function buildEmit(?People $company, ?Address $address, array $fiscal): \stdClass
    {
        $doc = $this->issuerDocument($company, $fiscal);
        $isCpf = strlen($doc) === 11;
        $emit = new \stdClass();
        if ($isCpf) {
            $emit->CPF = $doc;
        } else {
            $emit->CNPJ = str_pad($doc ?: '00000000000000', 14, '0', STR_PAD_LEFT);
        }
        $emit->IE = 'ISENTO';
        $certificateName = trim((string) ($fiscal['certificateName'] ?? $fiscal['receita-federal-certificate-name'] ?? ''));
        $emit->xNome = $certificateName !== '' ? $certificateName : ($company?->getName() ?: $company?->getAlias() ?: 'SEM NOME');
        $emit->xFant = $company?->getAlias() ?: $emit->xNome;
        $emit->CRT = (string) ($fiscal['receita-federal-tax-regime'] ?? $fiscal['crt'] ?? '1');
        return $emit;
    }

    private function issuerDocument(?People $company, array $fiscal): string
    {
        $certificateDocument = preg_replace(
            '/\D+/',
            '',
            (string) ($fiscal['certificateDocument'] ?? $fiscal['receita-federal-certificate-document'] ?? '')
        );

        return $certificateDocument !== '' ? $certificateDocument : $this->peopleDocument($company);
    }

    private function ibgeCode(array $fiscal): string
    {
        $code = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-ibge-code'] ?? ''));
        return $code !== '' ? $code : '3550308';
    }

    private function buildParty(?People $people, ?Address $address, array $fiscal): \stdClass
    {
        $doc = $this->peopleDocument($people);
        $isCpf = strlen($doc) === 11;
        $std = new \stdClass();
        if ($isCpf) {
            $std->CPF = $doc;
        } else {
            $std->CNPJ = str_pad($doc ?: '00000000000000', 14, '0', STR_PAD_LEFT);
        }
        $std->xNome = $people?->getName() ?: $people?->getAlias() ?: 'SEM NOME';
        $stateRegistration = $this->stateRegistration($people);
        if ($stateRegistration !== '') {
            $std->IE = $stateRegistration;
        }
        return $std;
    }

    private function tomadorIeIndicator(?People $tomador): string
    {
        $stateRegistration = $this->stateRegistration($tomador);
        if ($stateRegistration === '') {
            return '9';
        }

        return strtoupper($stateRegistration) === 'ISENTO' ? '2' : '1';
    }

    private function stateRegistration(?People $people): string
    {
        if (!$people instanceof People || !method_exists($people, 'getOtherInformations')) {
            return '';
        }

        $info = $people->getOtherInformations(true);
        $value = is_object($info) ? ($info->stateRegistration ?? $info->ie ?? null) : null;
        $stateRegistration = strtoupper(trim((string) $value));
        if ($stateRegistration === 'ISENTO') {
            return $stateRegistration;
        }

        return preg_replace('/\D+/', '', $stateRegistration) ?: '';
    }

    private function buildEnder(?Address $address): \stdClass
    {
        $ender = new \stdClass();
        $ender->xLgr = $this->streetName($address);
        $ender->nro = $this->streetNumber($address);
        $ender->xBairro = $this->districtName($address);
        $ender->cMun = (string) ($this->getFiscalValue($address, 'cMun') ?? '3550308');
        $ender->xMun = $this->cityName($address);
        $ender->CEP = $this->postalCode($address);
        $ender->UF = $this->uf($address);
        $ender->cPais = '1058';
        $ender->xPais = 'Brasil';
        return $ender;
    }

    private function buildInfRespTec(array $fiscal): \stdClass
    {
        $resp = new \stdClass();
        $resp->CNPJ = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-cte-responsavel-cnpj'] ?? '99999999999999'));
        $resp->xContato = (string) ($fiscal['receita-federal-cte-responsavel-contato'] ?? 'Controle Online');
        $resp->email = (string) ($fiscal['receita-federal-cte-responsavel-email'] ?? 'suporte@controleonline.com');
        $resp->fone = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-cte-responsavel-fone'] ?? '1130000000'));
        return $resp;
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

    private function getFiscalValue(?Address $address, string $key): ?string
    {
        return null;
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
