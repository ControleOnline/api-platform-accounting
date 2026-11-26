<?php

namespace ControleOnline\Service\Mdfe;

use ControleOnline\Entity\DeliveryCourierVehicle;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Entity\People;

class MdfeXmlBuilder
{
    public function build(Mdfe $mdfe, array $fiscal): string
    {
        if (!class_exists(\NFePHP\MDFe\Make::class)) {
            throw new \RuntimeException('Pacote nfephp-org/sped-mdfe não está disponível neste runtime.');
        }

        $company = $mdfe->getCompany();
        $vehicle = $mdfe->getVehicle();
        $driver = $mdfe->getDriver();
        $documents = $mdfe->getDocuments()->toArray();
        if (!$company instanceof People || !$vehicle || !$driver instanceof People || $documents === []) {
            throw new \RuntimeException('MDF-e sem empresa, veículo, motorista ou documentos vinculados.');
        }

        $make = new \NFePHP\MDFe\Make();
        $cnpj = $this->digits((string) ($fiscal['certificateDocument'] ?? $fiscal['cnpj'] ?? ''));
        $uf = (string) ($fiscal['uf'] ?? 'SP');
        $cUF = $this->ufCode($uf);
        $ibge = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-ibge-code'] ?? '')) ?: ($cUF . '00000');
        $nMDF = (string) ($fiscal['mdfeNextNumber'] ?? 1);
        $serie = (string) ($fiscal['receita-federal-mdfe-serie'] ?? '1');
        $tpAmb = (string) ($fiscal['receita-federal-environment'] ?? '2');
        $cMDF = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);

        $ide = new \stdClass();
        $ide->cUF = $cUF;
        $ide->tpAmb = $tpAmb;
        $ide->tpEmit = '1';
        $ide->tpTransp = '1';
        $ide->mod = '58';
        $ide->serie = $serie;
        $ide->nMDF = $nMDF;
        $ide->cMDF = $cMDF;
        $ide->cDV = '0';
        $ide->modal = '1';
        $ide->dhEmi = date('Y-m-d\TH:i:sP');
        $ide->tpEmis = '1';
        $ide->procEmi = '0';
        $ide->verProc = 'controleonline-1.0';
        $ide->UFIni = $uf;
        $ide->UFFim = $uf;
        $ide->dhIniViagem = date('Y-m-d\TH:i:sP');
        $make->tagide($ide);

        $emit = new \stdClass();
        $emit->CNPJ = str_pad($cnpj !== '' ? $cnpj : '00000000000000', 14, '0', STR_PAD_LEFT);
        $emit->IE = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-state-registration'] ?? '')) ?: 'ISENTO';
        $emit->xNome = (string) ($fiscal['certificateName'] ?? $fiscal['razaosocial'] ?? $this->peopleName($company));
        $make->tagemit($emit);

        $ender = new \stdClass();
        $ender->xLgr = 'NAO INFORMADO';
        $ender->nro = 'S/N';
        $ender->xBairro = 'CENTRO';
        $ender->cMun = $ibge;
        $ender->xMun = $uf;
        $ender->CEP = '00000000';
        $ender->UF = $uf;
        $ender->fone = '0000000000';
        $make->tagenderEmit($ender);

        $infModal = new \stdClass();
        $infModal->versaoModal = '3.00';
        $make->taginfModal($infModal);

        $infANTT = new \stdClass();
        $infANTT->RNTRC = preg_replace('/\D+/', '', (string) ($fiscal['receita-federal-cte-rntrc'] ?? '')) ?: '00000000';
        $make->taginfANTT($infANTT);

        $veic = new \stdClass();
        $veic->cInt = (string) ($vehicle->getId() ?? '1');
        $veic->placa = $this->plate($vehicle);
        $veic->tara = (string) $this->vehicleInt($vehicle, ['getTara', 'getTare'], 0);
        $veic->capKG = (string) $this->vehicleInt($vehicle, ['getCapacity', 'getCapacidade', 'getCapKG'], 0);
        $veic->tpRod = (string) ($this->vehicleValue($vehicle, ['getWheelType', 'getTpRod']) ?? '01');
        $veic->tpCar = (string) ($this->vehicleValue($vehicle, ['getBodyType', 'getTpCar']) ?? '00');
        $veic->UF = $uf;
        $make->tagveicTracao($veic);

        $condutor = new \stdClass();
        $condutor->xNome = $this->peopleName($driver);
        $condutor->CPF = str_pad($this->peopleDocument($driver, 11), 11, '0', STR_PAD_LEFT);
        $make->tagcondutor($condutor);

        $item = 0;
        $peso = 0.0;
        $valor = 0.0;
        foreach ($documents as $doc) {
            if (!$doc instanceof InvoiceTax) {
                continue;
            }
            $item++;
            $chave = preg_replace('/\D+/', '', (string) ($doc->getInvoiceKey() ?? ''));
            $valor += (float) ($doc->getInvoiceTotal() ?? 0);
            $inf = new \stdClass();
            $inf->chNFe = str_pad($chave !== '' ? substr($chave, 0, 44) : (string) $doc->getId(), 44, '0', STR_PAD_LEFT);
            if (method_exists($make, 'taginfNFe')) {
                $make->taginfNFe($inf);
            }
        }

        $tot = new \stdClass();
        $tot->qNFe = (string) max($item, 1);
        $tot->vCarga = number_format(max($valor, 0.01), 2, '.', '');
        $tot->cUnid = '01';
        $tot->qCarga = number_format(max($peso, 0.01), 4, '.', '');
        $make->tagtot($tot);

        if (method_exists($make, 'montaMDFe')) {
            $make->montaMDFe();
        } elseif (method_exists($make, 'monta')) {
            $make->monta();
        }

        if (method_exists($make, 'getXML')) {
            $xml = (string) $make->getXML();
        } else {
            throw new \RuntimeException('NFePHP\\MDFe\\Make não expôs getXML().');
        }
        if (trim($xml) === '') {
            throw new \RuntimeException('XML MDF-e gerado vazio.');
        }

        return $xml;
    }

    public function extractKey(string $xml): ?string
    {
        if (preg_match('/Id="MDFe(\d{44})"/', $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/<chMDFe>(\d{44})<\/chMDFe>/', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    public function extractNumber(string $xml): ?int
    {
        if (preg_match('/<nMDF>(\d+)<\/nMDF>/', $xml, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function ufCode(string $uf): string
    {
        $map = [
            'AC' => '12', 'AL' => '27', 'AM' => '13', 'AP' => '16', 'BA' => '29',
            'CE' => '23', 'DF' => '53', 'ES' => '32', 'GO' => '52', 'MA' => '21',
            'MG' => '31', 'MS' => '50', 'MT' => '51', 'PA' => '15', 'PB' => '25',
            'PE' => '26', 'PI' => '22', 'PR' => '41', 'RJ' => '33', 'RN' => '24',
            'RO' => '11', 'RR' => '14', 'RS' => '43', 'SC' => '42', 'SE' => '28',
            'SP' => '35', 'TO' => '17',
        ];

        return $map[strtoupper($uf)] ?? '35';
    }

    private function plate(object $vehicle): string
    {
        foreach (['getPlate', 'getLicensePlate', 'getPlaca'] as $method) {
            if (method_exists($vehicle, $method)) {
                $value = preg_replace('/[^A-Z0-9]/i', '', (string) $vehicle->{$method}());
                if ($value !== '') {
                    return strtoupper($value);
                }
            }
        }

        return 'AAA0000';
    }

    private function vehicleInt(object $vehicle, array $methods, int $default): int
    {
        $value = $this->vehicleValue($vehicle, $methods);

        return $value === null ? $default : (int) $value;
    }

    private function vehicleValue(object $vehicle, array $methods): mixed
    {
        foreach ($methods as $method) {
            if (method_exists($vehicle, $method)) {
                return $vehicle->{$method}();
            }
        }

        return null;
    }

    private function peopleName(People $people): string
    {
        foreach (['getName', 'getAlias', 'getPeople'] as $method) {
            if (method_exists($people, $method)) {
                $value = trim((string) $people->{$method}());
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return 'NAO INFORMADO';
    }

    private function peopleDocument(People $people, int $len): string
    {
        foreach (['getDocument', 'getCpf', 'getCnpj'] as $method) {
            if (method_exists($people, $method)) {
                $digits = $this->digits((string) $people->{$method}());
                if ($digits !== '') {
                    return substr($digits, 0, $len);
                }
            }
        }

        return str_repeat('0', $len);
    }
}
