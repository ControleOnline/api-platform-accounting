<?php

namespace ControleOnline\Library;

use ControleOnline\Entity\Order;
use ControleOnline\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

use NFePHP\NFe\Tools;
use NFePHP\NFe\Make;
use NFePHP\Common\Certificate;

class NFePHP
{
    /**
     * @var  Make $make
     */
    protected $make;
    protected $model;
    protected $version;

    public function __construct(
        protected EntityManagerInterface $manager,
        protected Security $security,
    ) {
        $this->make = new Make();
    }

    //ide OBRIGATÓRIA
    protected function makeIde(Order $order)
    {

        $std = new \stdClass();
        $std->cUF = 14;
        $std->cNF = '03701267';
        $std->natOp = 'VENDA CONSUMIDOR';
        $std->mod = 65;
        $std->serie = 1;
        $std->nNF = 100;
        $std->dhEmi = (new \DateTime())->format('Y-m-d\TH:i:sP');
        $std->dhSaiEnt = null;
        $std->tpNF = 1;
        $std->idDest = 1;
        $std->cMunFG = 1400100;
        $std->tpImp = 1;
        $std->tpEmis = 1;
        $std->cDV = 2;
        $std->tpAmb = 2;
        $std->finNFe = 1;
        $std->indFinal = 1;
        $std->indPres = 1;
        $std->procEmi = 3;
        $std->verProc = '4.13';
        $std->dhCont = null;
        $std->xJust = null;
        $this->make->tagIde($std);
    }

    //emit OBRIGATÓRIA
    protected function makeEmit(Order $order)
    {

        $std = new \stdClass();
        $std->xNome = 'SUA RAZAO SOCIAL LTDA';
        $std->xFant = 'RAZAO';
        $std->IE = '111111111';
        $std->IEST = null;
        //$std->IM = '95095870';
        $std->CNAE = '4642701';
        $std->CRT = 1;
        $std->CNPJ = '99999999999999';
        //$std->CPF = '12345678901'; //NÃO PASSE TAGS QUE NÃO EXISTEM NO CASO
        $this->make->tagemit($std);

        //enderEmit OBRIGATÓRIA
        $this->makeEmitAddress($order);
    }

    protected function makeEmitAddress(Order $order)
    {
        $std = new \stdClass();
        $std->xLgr = 'Avenida Getúlio Vargas';
        $std->nro = '5022';
        $std->xCpl = 'LOJA 42';
        $std->xBairro = 'CENTRO';
        $std->cMun = 1400100;
        $std->xMun = 'BOA VISTA';
        $std->UF = 'RR';
        $std->CEP = '69301030';
        $std->cPais = 1058;
        $std->xPais = 'Brasil';
        $std->fone = '55555555';
        $this->make->tagenderemit($std);
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
        $arr = [
            "atualizacao" => "2017-02-20 09:11:21",
            "tpAmb"       => 2,
            "razaosocial" => "SUA RAZAO SOCIAL LTDA",
            "cnpj"        => "99999999999999",
            "siglaUF"     => "SP",
            "schemes"     => "PL_009_V4",
            "versao"      => '4.00',
            "tokenIBPT"   => "AAAAAAA",
            "CSC"         => "GPB0JBWLUR6HWFTVEAS6RJ69GPCROFPBBB8G",
            "CSCid"       => "000001",
            "proxyConf"   => [
                "proxyIp"   => "",
                "proxyPort" => "",
                "proxyUser" => "",
                "proxyPass" => ""
            ]
        ];
        $configJson = json_encode($arr);
        $pfxcontent = file_get_contents('fixtures/expired_certificate.pfx');

        $tools = new Tools($configJson, Certificate::readPfx($pfxcontent, 'associacao'));
        $tools->model($this->model);
        //$tools->disableCertValidation(true); //tem que desabilitar
        return   $tools->signNFe($this->make->getXML());
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
}
