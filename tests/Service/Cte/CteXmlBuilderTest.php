<?php

namespace ControleOnline\Tests\Service\Cte;

use ControleOnline\Entity\InvoiceTax;
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
                'nextNumber' => 11,
            ],
            '5353'
        );

        self::assertStringContainsString('<mod>57</mod>', $xml);
        self::assertStringContainsString('<nCT>11</nCT>', $xml);
        self::assertStringContainsString('<serie>2</serie>', $xml);
        self::assertStringContainsString('<CFOP>5353</CFOP>', $xml);
        self::assertStringContainsString('<chave>35240112345678000190550010000001231000001234</chave>', $xml);
        self::assertStringContainsString('<chave>35240112345678000190550010000001241000001245</chave>', $xml);
        self::assertStringContainsString('<enderReme>', $xml);
        self::assertStringContainsString('<vTPrest>30.50</vTPrest>', $xml);
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

    private function invoice(string $key, float $total): InvoiceTax
    {
        $invoice = new InvoiceTax();
        $invoice->setInvoiceKey($key);
        $invoice->setInvoiceNumber(1);
        $invoice->setInvoiceModel(55);
        $invoice->setInvoiceTotal(number_format($total, 2, '.', ''));

        return $invoice;
    }
}
