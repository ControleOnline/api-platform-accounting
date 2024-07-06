<?php

namespace ControleOnline\Library;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\SalesInvoiceTax;
use ControleOnline\Entity\OrderInvoiceTax;
use Doctrine\ORM\EntityManagerInterface;
use NFePHP\Common\Certificate;
use Symfony\Component\Security\Core\Security;
use NFePHP\CTe\Common\Standardize;
use Symfony\Component\HttpKernel\KernelInterface;

class NFePHP
{
    protected $make;
    protected $model;
    protected $tools;
    protected $version;

    public function __construct(
        protected EntityManagerInterface $manager,
        protected Security $security,
        protected    KernelInterface $appKernel
    ) {
    }

    //ide OBRIGATÓRIA
    protected function makeIde(Order $order)
    {
        $provider = $order->getProvider();
        $document = $provider->getOneDocument();
        //$dhEmi = date("Y-m-d\TH:i:s-03:00"); Para obter a data com diferença de fuso usar 'P'
        $dhEmi = date("Y-m-d\TH:i:sP");

        $numeroCTE = $this->getLastDacte();

        // CUIDADO: Observe que mesmo os parâmetros fixados abaixo devem ser preenchidos conforme os dados do CT-e, estude a composição da CHAVE para saber o que vai em cada campo
        $chave = $this->montaChave(
            '43',
            date('y', strtotime($dhEmi)),
            date('m', strtotime($dhEmi)),
            $document->getDOcument(),
            $this->tools->model(),
            '1',
            $numeroCTE,
            '1',
            '10'
        );

        $cDV = substr($chave, -1);      //Digito Verificador

        /**
         * @todo
         */
        $ide = new \stdClass();
        $ide->cUF = '43'; // Codigo da UF da tabela do IBGE
        $ide->cCT = '99999999'; // Codigo numerico que compoe a chave de acesso
        $ide->CFOP = '6932'; // Codigo fiscal de operacoes e prestacoes
        $ide->natOp = 'PRESTACAO DE SERVICO DE TRANSPORTE A ESTABELECIMENTO FORA DO ESTADO DE ORIGEM'; // Natureza da operacao

        /**
         * @todo
         */

        //$ide->forPag = '';              // 0-Pago; 1-A pagar; 2-Outros
        $ide->mod = '57'; // Modelo do documento fiscal: 57 para identificação do CT-e
        $ide->serie = '1'; // Serie do CTe
        $ide->nCT = $numeroCTE; // Numero do CTe
        $ide->dhEmi = $dhEmi; // Data e hora de emissão do CT-e: Formato AAAA-MM-DDTHH:MM:DD
        $ide->tpImp = '1'; // Formato de impressao do DACTE: 1-Retrato; 2-Paisagem.
        $ide->tpEmis = '1'; // Forma de emissao do CTe: 1-Normal; 4-EPEC pela SVC; 5-Contingência
        $ide->cDV = $cDV; // Codigo verificador
        $ide->tpAmb = '2'; // 1- Producao; 2-homologacao
        $ide->tpCTe = '0'; // 0- CT-e Normal; 1 - CT-e de Complemento de Valores;
        // 2 -CT-e de Anulação; 3 - CT-e Substituto
        $ide->procEmi = '0'; // Descricao no comentario acima
        $ide->verProc = '3.0'; // versao do aplicativo emissor
        $ide->indGlobalizado = '';
        //$ide->refCTE = '';             // Chave de acesso do CT-e referenciado            
        $ide->xMunEnv = 'FOZ DO IGUACU'; // Informar PAIS/Municipio para as operações com o exterior.
        $ide->UFEnv = 'RS'; // Informar 'EX' para operações com o exterior.
        $ide->modal = '01'; // Preencher com:01-Rodoviário; 02-Aéreo; 03-Aquaviário;04-
        $ide->tpServ = '0'; // 0- Normal; 1- Subcontratação; 2- Redespacho;
        $ide->cMunEnv = $this->getCodMunicipio($ide->xMunEnv, $ide->UFEnv); // Código do município (utilizar a tabela do IBGE)


        /**
         * @todo
         */
        // 3- Redespacho Intermediário; 4- Serviço Vinculado a Multimodal            
        $ide->xMunIni = 'FOZ DO IGUACU'; // Informar 'EXTERIOR' para operações com o exterior.
        $ide->UFIni = 'RS'; // Informar 'EX' para operações com o exterior.
        $ide->cMunFim = '3523909'; // Utilizar a tabela do IBGE. Informar 9999999 para operações com o exterior.
        $ide->cMunFim = $this->getCodMunicipio($ide->xMunIni, $ide->UFIni); // Código do município (utilizar a tabela do IBGE)


        /**
         * @todo
         */
        $ide->xMunFim = 'ITU'; // Informar 'EXTERIOR' para operações com o exterior.
        $ide->UFFim = 'SP'; // Informar 'EX' para operações com o exterior.
        $ide->cMunIni = $this->getCodMunicipio($ide->xMunFim, $ide->UFFim); // Código do município (utilizar a tabela do IBGE)

        $ide->retira = '1'; // Indicador se o Recebedor retira no Aeroporto; Filial,
        // Porto ou Estação de Destino? 0-sim; 1-não
        $ide->xDetRetira = ''; // Detalhes do retira
        $ide->indIEToma = '1';
        $ide->dhCont = ''; // Data e Hora da entrada em contingência; no formato AAAAMM-DDTHH:MM:SS
        $ide->xJust = '';                 // Justificativa da entrada em contingência

        $this->make->tagide($ide);
    }


    //toma OBRIGATÓRIA
    protected function makeTomador(Order $order)
    {



        // Indica o "papel" do tomador: 0-Remetente; 1-Expedidor; 2-Recebedor; 3-Destinatário
        $toma3 = new \stdClass();
        $toma3->toma = '3';
        $this->make->tagtoma3($toma3);
        //
        //$toma4 = new stdClass();
        //$toma4->toma = '4'; // 4-Outros; informar os dados cadastrais do tomador quando ele for outros
        //$toma4->CNPJ = '11509962000197'; // CNPJ
        //$toma4->CPF = ''; // CPF
        //$toma4->IE = 'ISENTO'; // Iscricao estadual
        //$toma4->xNome = 'RAZAO SOCIAL'; // Razao social ou Nome
        //$toma4->xFant = 'NOME FANTASIA'; // Nome fantasia
        //$toma4->fone = '5532128202'; // Telefone
        //$toma4->email = 'email@gmail.com';   // email
        //$cte->tagtoma4($toma4);

        //endertoma OBRIGATÓRIA
        $this->makeTomadorAddress($order);
    }

    protected function makeTomadorAddress(Order $order)
    {

        /**
         * @todo
         */
        $enderToma = new \stdClass();
        $enderToma->xLgr = 'Avenida Independência'; // Logradouro
        $enderToma->nro = '482'; // Numero
        $enderToma->xCpl = ''; // COmplemento
        $enderToma->xBairro = 'Centro'; // Bairro
        $enderToma->cMun = '4308607'; // Codigo do municipio do IBEGE Informar 9999999 para operações com o exterior
        $enderToma->xMun = 'Garibaldi'; // Nome do município (Informar EXTERIOR para operações com o exterior.
        $enderToma->CEP = '95720000'; // CEP
        $enderToma->UF = 'SP'; //$arr['siglaUF']; // Sigla UF (Informar EX para operações com o exterior.)
        $enderToma->cPais = '1058'; // Codigo do país ( Utilizar a tabela do BACEN )
        $enderToma->xPais = 'Brasil';                   // Nome do pais
        $this->make->tagenderToma($enderToma);
    }

    //emit OBRIGATÓRIA
    protected function makeEmit(Order $order)
    {
        $provider = $order->getProvider();
        $document = $provider->getOneDocument();

        $std = new \stdClass();
        $std->IE = '111111111';
        $std->IEST = null;
        //$std->IM = '95095870';
        $std->CNAE = '4642701';
        $std->CRT = 1;
        $std->CNPJ = '99999999999999';
        //$std->CPF = '12345678901'; //NÃO PASSE TAGS QUE NÃO EXISTEM NO CASO

        $emit = new \stdClass();
        $emit->CNPJ = $document->getDOcument(); // CNPJ do emitente
        //$emit->IE = '0100072968'; // Inscricao estadual
        //$emit->IEST = ""; // Inscricao estadual
        $emit->xNome = $provider->getName(); // Razao social
        $emit->xFant = $provider->getAlias(); // Nome fantasia

        $this->make->tagemit($std);

        //enderEmit OBRIGATÓRIA
        $this->makeEmitAddress($order);
    }

    protected function makeEmitAddress(Order $order)
    {
        $provider = $order->getProvider();
        /**
         * @var \ControleOnline\Entity\Address $providerAddress
         */
        $providerAddress = $provider->getAddress()[0];

        $enderEmit = new \stdClass();
        $enderEmit->xLgr = $providerAddress->getStreet()->getStreet(); // Logradouro
        $enderEmit->nro = $providerAddress->getNumber(); // Numero
        $enderEmit->xCpl = $providerAddress->getComplement(); // Complemento
        $enderEmit->xBairro = $providerAddress->getStreet()->getDistrict()->getDistrict(); // Bairro
        $enderEmit->xMun = $providerAddress->getStreet()->getDistrict()->getCity()->getCity(); // Nome do municipio            
        $enderEmit->CEP = $providerAddress->getStreet()->getCep()->getCep(); // CEP
        $enderEmit->UF =  $providerAddress->getStreet()->getDistrict()->getCity()->getState()->getUf(); // Sigla UF
        $enderEmit->cMun = $this->getCodMunicipio($enderEmit->xMun, $enderEmit->UF); // Código do município (utilizar a tabela do IBGE)
        $enderEmit->fone = $provider->getPhone()[0]->getDdd() . $provider->getPhone()[0]->getPhone(); // Fone
        $this->make->tagenderemit($enderEmit);
    }
    //dest OPCIONAL
    protected function makeDest(Order $order)
    {
        $std = new \stdClass();
        $std->xNome = 'Eu Ltda';
        $std->CNPJ = '01234123456789';
        //$std->CPF = '12345678901';
        //$std->idEstrangeiro = 'AB1234';
        $std->indIEDest = 9;
        //$std->IE = '';
        //$std->ISUF = '12345679';
        //$std->IM = 'XYZ6543212';
        $std->email = 'seila@seila.com.br';
        $dest = $this->make->tagdest($std);


        $this->makeDestAddress($order);
    }
    //enderDest OPCIONAL
    protected function makeDestAddress(Order $order)
    {

        $std = new \stdClass();
        $std->xLgr = 'Avenida Sebastião Diniz';
        $std->nro = '458';
        $std->xCpl = null;
        $std->xBairro = 'CENTRO';
        $std->cMun = 1400100;
        $std->xMun = 'Boa Vista';
        $std->UF = 'RR';
        $std->CEP = '69301088';
        $std->cPais = 1058;
        $std->xPais = 'Brasil';
        $std->fone = '1111111111';
        $this->make->tagenderdest($std);
    }

    //prod OBRIGATÓRIA
    protected function makeProds(Order $order)
    {
        $orderProducts  = $order->getOrderProducts();
        $item = 1;
        foreach ($orderProducts as $orderProducts) {
            $product = $orderProducts->getProduct();

            $std = new \stdClass();
            $std->item = $item;
            $std->cProd = '00341';
            $std->cEAN = 'SEM GTIN';
            $std->cEANTrib = 'SEM GTIN';
            $std->xProd = 'Produto com serviço';
            $std->NCM = '96081000';
            $std->CFOP = '5933';
            $std->uCom = 'JG';
            $std->uTrib = 'JG';
            $std->cBarra = NULL;
            $std->cBarraTrib = NULL;
            $std->qCom = '1';
            $std->qTrib = '1';
            $std->vUnCom = '200';
            $std->vUnTrib = '200';
            $std->vProd = '200';
            $std->vDesc = NULL;
            $std->vOutro = NULL;
            $std->vSeg = NULL;
            $std->vFrete = NULL;
            $std->cBenef = NULL;
            $std->xPed = NULL;
            $std->nItemPed = NULL;
            $std->indTot = 1;
            $this->make->tagprod($std);
            $this->makeImpostos($product, $item);
        }
    }
    protected function makeImpostos(Product $product, $item)
    {
        $this->makePIS($product, $item);
        $this->makeCOFINS($product, $item);
        $this->makeISSQN($product, $item);

        //Imposto
        $std = new \stdClass();
        $std->item = $item; //item da NFe
        $std->vTotTrib = 0;
        $this->make->tagimposto($std);



        $std = new \stdClass();
        $this->make->tagICMSTot($std);

        $std = new \stdClass();
        $std->dCompet = '2010-09-12';
        $std->cRegTrib = 6;
        $this->make->tagISSQNTot($std);
        $this->make->tagISSQNTot($std);
    }

    protected function makeInfNFe($version)
    {
        //infNFe OBRIGATÓRIA
        $std = new \stdClass();
        $std->Id = '';
        $std->versao =  $version;
        $this->make->taginfNFe($std);
    }

    protected function makeISSQN(Product $product, $item)
    {
        // Monta a tag de impostos mas não adiciona no xml
        $std = new \stdClass();
        $std->item = $item; //item da NFe
        $std->vBC = 2.0;
        $std->vAliq = 8.0;
        $std->vISSQN = 0.16;
        $std->cMunFG = 1300029;
        $std->cMun = 1300029;
        $std->cPais = '1058';
        $std->cListServ = '01.01';
        $std->indISS = 1;
        $std->indIncentivo = 2;
        // Adiciona a tag de imposto ISSQN no xml
        $this->make->tagISSQN($std);
    }

    protected function makePIS(Product $product, $item)
    {
        //PIS
        $std = new \stdClass();
        $std->item = $item; //item da NFe
        $std->CST = '99';
        $std->vBC = 200;
        $std->pPIS = 0.65;
        $std->vPIS = 13;
        $this->make->tagPIS($std);
    }
    protected function makeCOFINS(Product $product, $item)
    {
        //COFINS
        $std = new \stdClass();
        $std->item = $item; //item da NFe
        $std->CST = '99';
        $std->vBC = 200;
        $std->pCOFINS = 3;
        $std->vCOFINS = 60;
        $this->make->tagCOFINS($std);
    }

    protected function sign(Order $order)
    {
        $this->tools->model($this->model);
        //$tools->disableCertValidation(true); //tem que desabilitar
        return $this->tools->signNFe($this->make->getXML());
    }

    protected function getCertificate(Order $order)
    {
        $provider = $order->getProvider();

        $dacteKey = $this->manager->getRepository(Config::class)->findOneBy([
            'people'  => $provider,
            'config_key' => 'cert-path'
        ]);

        $dacteKeyPass = $this->manager->getRepository(Config::class)->findOneBy([
            'people'  => $provider,
            'config_key' => 'cert-pass'
        ]);
        if (!$dacteKey || !$dacteKeyPass)
            throw new \Exception("DACTE key cert is required", 1);

        $certPath = $this->appKernel->getProjectDir() . $dacteKey->getConfigValue();
        if (!is_file($certPath))
            throw new \Exception("DACTE key cert path is invalid", 1);
        return Certificate::readPfx($this->getSignData($order), $dacteKeyPass->getConfigValue());
    }

    protected function getSignData(Order $order)
    {

        $provider = $order->getProvider();
        $document = $provider->getOneDocument();

        /**
         * @var \ControleOnline\Entity\Address $providerAddress
         */
        $providerAddress = $provider->getAddress()[0];

        $arr = [
            "atualizacao" => date('Y-m-d H:m:i'),
            "tpAmb" => 2, //2 - Homologação / 1 - Produção
            "razaosocial" => $provider->getName(),
            "cnpj" => $document->getDocument(),
            //"cpf" => "00000000000",
            "siglaUF" => $providerAddress->getStreet()->getDistrict()->getCity()->getState()->getUf(),
            "schemes" => "PL_CTe_300",
            "versao" => '3.00',
            "proxyConf" => [
                "proxyIp" => "",
                "proxyPort" => "",
                "proxyUser" => "",
                "proxyPass" => ""
            ]
        ];

        return json_encode($arr);
    }

    protected function makeTransp(Order $order)
    {
        //transp OBRIGATÓRIA
        $std = new \stdClass();
        $this->make->tagtransp($std);
    }


    protected function makePag(Order $order)
    {
        //pag OBRIGATÓRIA
        $std = new \stdClass();
        $std->vTroco = 0;
        $this->make->tagpag($std);
    }


    protected function makedetPag(Order $order)
    {
        //detPag OBRIGATÓRIA
        $std = new \stdClass();
        $std->indPag = '0';
        $std->xPag = NULL;
        $std->tPag = '01';
        $std->vPag = 2.01;
        $this->make->tagdetpag($std);
    }
    protected function makeInfRespTec()
    {

        $std = new \stdClass();
        $std->CNPJ = '99999999999999'; //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
        $std->xContato = 'Fulano de Tal'; //Nome da pessoa a ser contatada
        $std->email = 'fulano@soft.com.br'; //E-mail da pessoa jurídica a ser contatada
        $std->fone = '1155551122'; //Telefone da pessoa jurídica/física a ser contatada
        //$std->CSRT = 'G8063VRTNDMO886SFNK5LDUDEI24XJ22YIPO'; //Código de Segurança do Responsável Técnico
        //$std->idCSRT = '01'; //Identificador do CSRT
        $this->make->taginfRespTec($std);
    }


    protected function getCodMunicipio($mun, $uf)
    {

        /**
         * @todo
         */
        $cod['sp'] = [
            'Guarulhos' => '4108304',
            'São Paulo' => '4108304'
        ];

        return $cod[$uf][$mun];
    }
    protected function getLastDacte()
    {
        return '127'; //@todo
    }

    protected function montaChave($cUF, $ano, $mes, $cnpj, $mod, $serie, $numero, $tpEmis, $codigo = '')
    {
        if ($codigo == '') {
            $codigo = $numero;
        }
        $forma = "%02d%02d%02d%s%02d%03d%09d%01d%08d";
        $chave = sprintf(
            $forma,
            $cUF,
            $ano,
            $mes,
            $cnpj,
            $mod,
            $serie,
            $numero,
            $tpEmis,
            $codigo
        );
        return $chave . $this->calculaDV($chave);
    }


    protected function sendData($xml)
    {

        //Envia lote e autoriza
        $axmls[] = $xml;
        $lote = substr(str_replace(',', '', number_format(microtime(true) * 1000000, 0)), 0, 15);
        $res = $this->tools->sefazEnviaLote($axmls, $lote);

        //Converte resposta
        $stdCl = new Standardize($res);
        //Output array
        $arr = $stdCl->toArray();
        //print_r($arr);
        //Output object
        $std = $stdCl->toStd();

        if ($std->cStat != 103) { //103 - Lote recebido com Sucesso
            //processa erros
            print_r($arr);
        }

        //Consulta Recibo
        $res = $this->tools->sefazConsultaRecibo($std->infRec->nRec);
        $stdCl = new Standardize($res);
        $arr = $stdCl->toArray();
        $std = $stdCl->toStd();
        if ($std->protCTe->infProt->cStat == 100) { //Autorizado o uso do CT-e
            //adicionar protocolo
        }
        echo '<pre>';
        print_r($arr);
    }

    protected function calculaDV($chave43)
    {
        $multiplicadores = array(2, 3, 4, 5, 6, 7, 8, 9);
        $iCount = 42;
        $somaPonderada = 0;
        while ($iCount >= 0) {
            for ($mCount = 0; $mCount < count($multiplicadores) && $iCount >= 0; $mCount++) {
                $num = (int) substr($chave43, $iCount, 1);
                $peso = (int) $multiplicadores[$mCount];
                $somaPonderada += $num * $peso;
                $iCount--;
            }
        }
        $resto = $somaPonderada % 11;
        if ($resto == '0' || $resto == '1') {
            $cDV = 0;
        } else {
            $cDV = 11 - $resto;
        }
        return (string) $cDV;
    }

    public function  getNfNumber($xml)
    {
        return 1;
    }

    protected function persist(Order $order, $xml)
    {
        $provider = $order->getProvider();
        $invoiceTax = new SalesInvoiceTax();
        $invoiceTax->setInvoice($xml);
        $invoiceTax->setInvoiceNumber($this->getNfNumber($xml));

        $this->manager->persist($invoiceTax);
        $this->manager->flush();


        $orderInvoiceTax = new OrderInvoiceTax();
        $orderInvoiceTax->setOrder($order);
        $orderInvoiceTax->setInvoiceType(57);
        $orderInvoiceTax->setInvoiceTax($invoiceTax);
        $orderInvoiceTax->setIssuer($provider);

        $this->manager->persist($orderInvoiceTax);
        $this->manager->flush();
    }
}
