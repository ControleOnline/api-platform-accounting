<?php

namespace ControleOnline\Tests\Service\Cte;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\Cte\CteXmlBuilder;
use PHPUnit\Framework\TestCase;

final class CteXmlBuilderTest extends TestCase
{
    public function testBuildIncludesLinkedNfKeysAndModel57(): void
    {
        $nfA = $this->invoice('35240112345678000190550010000001231000001234', 10.5);
        $nfB = $this->invoice('35240112345678000190550010000001241000001245', 20);

        $xml = (new CteXmlBuilder())->build(
            [$nfA, $nfB],
            [
                'receita-federal-cte-serie' => '2',
                'receita-federal-environment' => '2',
                'receita-federal-ibge-code' => '3550308',
                'receita-federal-cte-rntrc' => '12345678',
                'receita-federal-state-registration' => '407302089116',
                'nextNumber' => 11,
            ],
            '5932',
            $this->cteValues()
        );

        self::assertStringContainsString('<mod>57</mod>', $xml);
        self::assertStringContainsString('<nCT>11</nCT>', $xml);
        self::assertStringContainsString('<serie>2</serie>', $xml);
        self::assertStringContainsString('<CFOP>5932</CFOP>', $xml);
        self::assertStringContainsString('<chave>35240112345678000190550010000001231000001234</chave>', $xml);
        self::assertStringContainsString('<chave>35240112345678000190550010000001241000001245</chave>', $xml);
        self::assertStringContainsString('<enderReme>', $xml);
        self::assertStringContainsString('<vTPrest>10.50</vTPrest>', $xml);
        self::assertMatchesRegularExpression('/Id="CTe[0-9]{44}"/', $xml);

        $builder = new CteXmlBuilder();
        self::assertSame(11, $builder->extractNumber($xml));
        self::assertSame(44, strlen((string) $builder->extractKey($xml)));
    }

    public function testCheckDigitIsStable(): void
    {
        $builder = new CteXmlBuilder();
        $base = '352401123456780001905700200000001111234567';
        $dv = $builder->checkDigit($base);
        self::assertMatchesRegularExpression('/^[0-9]$/', $dv);
        self::assertSame($dv, $builder->checkDigit($base));
    }

    public function testBuildUsesCertificateIssuerAsEmitter(): void
    {
        $xml = (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            [
                'certificateDocument' => '11222333000181',
                'certificateName' => 'TRANSPORTADORA CERTIFICADA LTDA',
                'receita-federal-cte-serie' => '2',
                'receita-federal-environment' => '2',
                'receita-federal-ibge-code' => '3550308',
                'receita-federal-cte-rntrc' => '12345678',
                'receita-federal-state-registration' => '407302089116',
                'nextNumber' => 11,
            ],
            '5932',
            $this->cteValues()
        );

        self::assertStringContainsString('<emit><CNPJ>11222333000181</CNPJ>', $xml);
        self::assertStringContainsString('<xNome>TRANSPORTADORA CERTIFICADA LTDA</xNome>', $xml);
        self::assertMatchesRegularExpression('/Id="CTe\\d{6}1122233300018157/', $xml);
    }

    public function testBuildMarksTomadorAsNonContributorWhenStateRegistrationIsMissing(): void
    {
        $xml = (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '5932',
            $this->cteValues()
        );

        self::assertStringContainsString('<indIEToma>9</indIEToma>', $xml);
        self::assertStringNotContainsString('<IE>ISENTO</IE>', $xml);
    }

    public function testBuildUsesTomadorStateRegistrationWhenAvailable(): void
    {
        $invoice = $this->invoice('35240112345678000190550010000001231000001234', 10.5);
        $client = new People();
        $client->setName('CLIENTE CONTRIBUINTE');
        $client->addOtherInformations('stateRegistration', '110042490114');
        $invoice->setClient($client);

        $xml = (new CteXmlBuilder())->build(
            [$invoice],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '5932',
            $this->cteValues()
        );

        self::assertStringContainsString('<indIEToma>1</indIEToma>', $xml);
        self::assertStringContainsString('<IE>110042490114</IE>', $xml);
    }

    public function testBuildMarksTomadorAsExemptWhenStateRegistrationIsExplicitlyExempt(): void
    {
        $invoice = $this->invoice('35240112345678000190550010000001231000001234', 10.5);
        $client = new People();
        $client->setName('CLIENTE ISENTO');
        $client->addOtherInformations('stateRegistration', 'ISENTO');
        $invoice->setClient($client);

        $xml = (new CteXmlBuilder())->build(
            [$invoice],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '5932',
            $this->cteValues()
        );

        self::assertStringContainsString('<indIEToma>2</indIEToma>', $xml);
        self::assertStringContainsString('<IE>ISENTO</IE>', $xml);
    }

    public function testBuildKeepsValidCteCfop(): void
    {
        $xml = (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '6932',
            $this->cteValues()
        );

        self::assertStringContainsString('<CFOP>6932</CFOP>', $xml);
    }

    public function testBuildInfersCteCfopFromNfeItemCfop(): void
    {
        $xml = (new CteXmlBuilder())->build(
            [$this->invoiceWithXml('<NFe><infNFe><det><prod><CFOP>6152</CFOP></prod></det></infNFe></NFe>')],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '',
            $this->cteValues()
        );

        self::assertStringContainsString('<CFOP>6353</CFOP>', $xml);
    }

    public function testBuildInfersTomadorFromNfeFreightMode(): void
    {
        $xml = (new CteXmlBuilder())->build(
            [$this->invoiceWithXml('<NFe><infNFe><transp><modFrete>0</modFrete></transp></infNFe></NFe>')],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '5932',
            [
                'modal' => '01',
                'tipoServico' => '0',
                'tipoCte' => '0',
                'tomador' => '3',
                'valorFrete' => '10.50',
                'valorReceber' => '10.50',
            ]
        );

        self::assertStringContainsString('<toma>0</toma>', $xml);
    }

    public function testBuildRequiresFreightValuesInsteadOfUsingCargoValueFallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Valor do frete');

        (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678', 'receita-federal-state-registration' => '407302089116'],
            '5932',
            [
                'modal' => '01',
                'tipoServico' => '0',
                'tipoCte' => '0',
                'tomador' => '3',
            ]
        );
    }

    public function testBuildRequiresRntrcInsteadOfUsingFallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IE do emitente');

        (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            ['receita-federal-ibge-code' => '3550308'],
            '5932',
            [
                'modal' => '01',
                'tipoServico' => '0',
                'tipoCte' => '0',
                'tomador' => '3',
                'valorFrete' => '10.50',
                'valorReceber' => '10.50',
            ]
        );
    }

    public function testBuildRequiresEmitterStateRegistrationFromFiscalConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IE do emitente');

        (new CteXmlBuilder())->build(
            [$this->invoice('35240112345678000190550010000001231000001234', 10.5)],
            ['receita-federal-ibge-code' => '3550308', 'receita-federal-cte-rntrc' => '12345678'],
            '5932',
            $this->cteValues()
        );
    }

    private function invoice(string $key, float $total): InvoiceTax
    {
        $invoice = new InvoiceTax();
        $invoice->setInvoiceKey($key);
        $invoice->setInvoiceNumber(1);
        $invoice->setInvoiceModel(55);
        $invoice->setInvoiceTotal(number_format($total, 2, '.', ''));

        return $invoice;
    }

    private function invoiceWithXml(string $xml): InvoiceTax
    {
        return new class ($xml) extends InvoiceTax {
            public function __construct(private string $xml)
            {
                parent::__construct();
                $this->setInvoiceKey('35240112345678000190550010000001231000001234');
                $this->setInvoiceNumber(1);
                $this->setInvoiceModel(55);
                $this->setInvoiceTotal('10.50');
            }

            public function getInvoice()
            {
                return $this->xml;
            }
        };
    }

    /**
     * @return array{modal: string, tipoServico: string, tipoCte: string, tomador: string, valorFrete: string, valorReceber: string}
     */
    private function cteValues(): array
    {
        return [
            'modal' => '01',
            'tipoServico' => '0',
            'tipoCte' => '0',
            'tomador' => '3',
            'valorFrete' => '10.50',
            'valorReceber' => '10.50',
        ];
    }
}
