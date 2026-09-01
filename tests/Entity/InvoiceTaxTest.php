<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\InvoiceTax;
use PHPUnit\Framework\TestCase;

final class InvoiceTaxTest extends TestCase
{
    public function testFiscalDocumentReadsCteFieldsFromXml(): void
    {
        $invoiceTax = new class extends InvoiceTax {
            public function getInvoice()
            {
                return <<<XML
<cteProc xmlns="http://www.portalfiscal.inf.br/cte">
  <CTe>
    <infCte Id="CTe35260936244529000440570090000000451878046196">
      <ide>
        <CFOP>6353</CFOP>
        <serie>9</serie>
        <nCT>45</nCT>
        <dhEmi>2026-09-01T09:25:00-03:00</dhEmi>
      </ide>
      <vPrest>
        <vTPrest>1.00</vTPrest>
      </vPrest>
    </infCte>
  </CTe>
  <protCTe>
    <infProt>
      <chCTe>35260936244529000440570090000000451878046196</chCTe>
      <cStat>100</cStat>
      <xMotivo>Autorizado o uso do CT-e</xMotivo>
      <nProt>135260000000000</nProt>
      <dhRecbto>2026-09-01T09:26:00-03:00</dhRecbto>
    </infProt>
  </protCTe>
</cteProc>
XML;
            }
        };
        $invoiceTax->setInvoiceModel(57);

        self::assertSame([
            'type' => 'CTE',
            'model' => 57,
            'series' => '9',
            'number' => '45',
            'key' => '35260936244529000440570090000000451878046196',
            'issuedAt' => '2026-09-01T09:25:00-03:00',
            'cfop' => '6353',
            'protocol' => '135260000000000',
            'authorizationStatus' => '100',
            'authorizationMessage' => 'Autorizado o uso do CT-e',
            'authorizedAt' => '2026-09-01T09:26:00-03:00',
            'total' => '1.00',
        ], $invoiceTax->getFiscalDocument());

        $invoiceTax->syncFiscalDocumentFieldsFromXml();

        self::assertSame('CTE', $invoiceTax->getFiscalType());
        self::assertSame('9', $invoiceTax->getFiscalSeries());
        self::assertSame('45', $invoiceTax->getFiscalNumber());
        self::assertSame('135260000000000', $invoiceTax->getFiscalProtocol());
        self::assertSame('100', $invoiceTax->getFiscalAuthorizationStatus());
    }

    public function testFiscalDocumentReadsNfeNumberFromXml(): void
    {
        $invoiceTax = new class extends InvoiceTax {
            public function getInvoice()
            {
                return '<nfeProc><NFe><infNFe><ide><serie>0</serie><nNF>26173701</nNF></ide><total><ICMSTot><vNF>1268.08</vNF></ICMSTot></total></infNFe></NFe></nfeProc>';
            }
        };
        $invoiceTax->setInvoiceModel(55);
        $invoiceTax->syncFiscalDocumentFieldsFromXml();

        $fiscal = $invoiceTax->getFiscalDocument();

        self::assertSame('NFE', $fiscal['type']);
        self::assertSame('0', $fiscal['series']);
        self::assertSame('26173701', $fiscal['number']);
        self::assertSame('1268.08', $fiscal['total']);
    }
}
