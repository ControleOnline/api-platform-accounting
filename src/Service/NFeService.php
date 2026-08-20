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
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\OrderInvoiceTax;
use ControleOnline\Library\NFePHP;

class NFeService extends NFePHP
{
    /**
     * Build, sign and persist a fiscal document for the order.
     * Model 65 = NFC-e (cupom), 55 = NF-e, 57 = CT-e.
     *
     * @return InvoiceTax
     */
    public function createNfe(Order $order, $model = '65', $version = '4.00'): InvoiceTax
    {
        $this->model = (string) $model;
        $this->version = $version;

        switch ($this->model) {
            case '65':
                $this->make = new \NFePHP\NFe\Make();
                $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->cupomFiscal($order);
                break;
            case '55':
                $this->make = new \NFePHP\NFe\Make();
                $this->tools = new \NFePHP\NFe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->nfe($order);
                break;
            case '57':
                $this->make = new \NFePHP\CTe\MakeCTe();
                $this->tools = new \NFePHP\CTe\Tools($this->getSignData($order), $this->getCertificate($order));
                $this->cte($order);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported NF model: %s', $this->model));
        }

        $xml = $this->sign($order);

        return $this->persistInvoiceTax($order, $xml, (int) $this->model);
    }

    /**
     * Persist InvoiceTax + OrderInvoiceTax link for the order.
     */
    protected function persistInvoiceTax(Order $order, string $xml, int $invoiceType): InvoiceTax
    {
        $provider = $order->getProvider();
        $invoiceTax = new InvoiceTax();
        $invoiceTax->setInvoice($xml);
        $invoiceTax->setInvoiceNumber($this->getNfNumber($xml));

        $this->manager->persist($invoiceTax);
        $this->manager->flush();

        $orderInvoiceTax = new OrderInvoiceTax();
        $orderInvoiceTax->setOrder($order);
        $orderInvoiceTax->setInvoiceType($invoiceType);
        $orderInvoiceTax->setInvoiceTax($invoiceTax);
        $orderInvoiceTax->setIssuer($provider);

        $this->manager->persist($orderInvoiceTax);
        $this->manager->flush();

        return $invoiceTax;
    }

    protected function nfe(Order $order)
    {
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makeProds($order);
        $this->makeTransp($order);
        $this->makePag($order);
        $this->makedetPag($order);
    }

    protected function cte(Order $order)
    {
        $this->makeInfNFe($this->version);
        $this->makeIde($order);
        $this->makeEmit($order);
        $this->makeDest($order);
        $this->makePag($order);
        $this->makedetPag($order);
        $this->makeTomador($order);
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
