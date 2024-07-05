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
                    $this->cupomFiscal($order);
                    break;
                default:
                    return;
                    break;
            }

            return $this->assign($order);

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
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
