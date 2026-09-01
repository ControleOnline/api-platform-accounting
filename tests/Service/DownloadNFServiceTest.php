<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\DownloadNFService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DownloadNFServiceTest extends TestCase
{
    public function testCtePdfFilenameUsesSeriesAndNumberFromXml(): void
    {
        $service = new DownloadNFService($this->createKernel());
        $invoiceTax = new class extends InvoiceTax {
            public function getInvoice()
            {
                return '<cteProc xmlns="http://www.portalfiscal.inf.br/cte"><CTe><infCte><ide><serie>9</serie><nCT>45</nCT></ide></infCte></CTe></cteProc>';
            }
        };
        $invoiceTax->setInvoiceModel(57);
        $invoiceTax->setInvoiceNumber(999);

        self::assertSame('CTE-9-45.pdf', $service->getDownloadFilename($invoiceTax, 'pdf'));
    }

    public function testNfePdfFilenameUsesSeriesAndNumberFromXml(): void
    {
        $service = new DownloadNFService($this->createKernel());
        $invoiceTax = new class extends InvoiceTax {
            public function getInvoice()
            {
                return '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe"><NFe><infNFe><ide><serie>9</serie><nNF>45</nNF></ide></infNFe></NFe></nfeProc>';
            }
        };
        $invoiceTax->setInvoiceModel(55);
        $invoiceTax->setInvoiceNumber(45);

        self::assertSame('NFE-9-45.pdf', $service->getDownloadFilename($invoiceTax, 'pdf'));
    }

    private function createKernel(): KernelInterface
    {
        return $this->createStub(KernelInterface::class);
    }
}
