<?php

/*
 * Contract imported from AGENTS.md
 * ## Escopo
 * - Modulo fiscal e contabil do backend.
 * - Cobre impostos de fatura, impostos de pedido, NFe e download de notas.
 *
 * ## Quando usar
 * - Prompts sobre NFe, tributacao, `InvoiceTax`, `OrderInvoiceTax`, `ServiceInvoiceTax` e servicos fiscais.
 *
 * ## Limites
 * - Regras de cobranca, wallet e invoice pertencem primeiro a `financial`.
 * - Regras de pedido pertencem primeiro a `orders`.
 * - `accounting` deve cuidar da camada fiscal, nao do fluxo operacional de pagamento.
 */


namespace ControleOnline\Service;

use ControleOnline\Entity\Order;
use ControleOnline\Entity\SalesInvoiceTax;
use ControleOnline\Entity\OrderInvoiceTax;
use ControleOnline\Library\NFePHP;

class NFeService extends NFePHP
{
    public function createNfe(Order|array $input, $model, $version =  '4.00')
    {
        $orders = $input instanceof Order ? [$input] : array_values(array_filter(
            $input,
            static fn (mixed $order): bool => $order instanceof Order
        ));
        if ($orders === []) {
            throw new \InvalidArgumentException('Nenhum pedido válido foi informado para emissão.');
        }
        $order = $orders[0];

        $this->model = (string) $model;
        $this->version = $version;

        switch ($this->model) {
            case '65':
                $this->make = new \NFePHP\NFe\Make();
                $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->cupomFiscal($order, $orders);
                break;
            case '55':
                $this->make = new \NFePHP\NFe\Make();
                $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->nfe($order, $orders);
                break;
            case '57':
                $this->make = new \NFePHP\CTe\MakeCTe();
                $this->tools = new \NFePHP\CTe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->cte($order);
                break;
            default:
                return;
                break;
        }

        $xml = $this->sign($order);
        //$this->persist($order, $xml);

        return $xml;
    }

    protected function nfe(Order $order, array $orders = [])
    {
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makeProds($orders ?: [$order]);
        $this->makeTransp($order);
        $this->makePag($orders ?: [$order]);
        $this->makedetPag($orders ?: [$order]);
    }

    protected function cte(Order $order)
    {
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makePag($orders ?: [$order]);
        $this->makedetPag($orders ?: [$order]);
        $this->makeTomador($order);
    }

    protected function cupomFiscal(Order $order, array $orders = [])
    {
        $this->makeInfRespTec();
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makeProds($orders ?: [$order]);
        $this->makeTransp($order);
        $this->makePag($order);
        $this->makedetPag($order);
    }
}
