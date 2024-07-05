<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Order;
use ControleOnline\Library\NFePHP;

class NFeService extends NFePHP
{
    public function createNfe(Order $order, $model, $version =  '4.0')
    {

        $this->model = $model;
        $this->version = $version;
        try {
            switch ($this->model) {
                case '65':
                    $this->make = new \NFePHP\NFe\Make();
                    $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate());
                    $this->cupomFiscal($order);
                    break;
                case '55':
                    $this->make = new \NFePHP\NFe\Make();
                    $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate());
                    $this->nfe($order);
                    break;
                case '57':
                    $this->make = new \NFePHP\CTe\MakeCTe();
                    $this->tools = new \NFePHP\CTe\Tools($this->getSignData($order), $this->getCertificate());
                    $this->cte($order);
                    break;
                default:
                    return;
                    break;
            }

            return $this->sign($order);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }



    protected function nfe(Order $order)
    {
    }

    protected function cte(Order $order)
    {
    }

    protected function cupomFiscal(Order $order)
    {
        $this->makeInfRespTec();
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makeProds($order);
        $this->makeTransp($order);
        $this->makePag($order);
        $this->makedetPag($order);
    }
}
