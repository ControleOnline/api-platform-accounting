<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\PurchasingInvoiceTax as InvoiceTax;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use ApiPlatform\Core\Exception\InvalidValueException;
use ControleOnline\Entity\People;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\CTe\Dacte;
use NFePHP\POS\PrintConnectors\Base64PrintConnector;
use NFePHP\POS\DanfcePos;

class DownloadNFService
{
    /**
     * Mimetypes
     *
     * @var array
     */
    private $mimetypes = [
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
    ];

    /**
     * File output default names
     *
     * @var array
     */
    private $filenames = [
        'xml' => 'nota_fiscal.xml',
        'pdf' => 'nota_fiscal.pdf',
    ];



    public function __construct(private Request $request, private KernelInterface $kernel)
    {
    }

    public function downloadNf(InvoiceTax $invoiceTax, $format = 'pdf')
    {
        $method = 'get' . ucfirst(strtolower($format));
        if (method_exists($this, $method) === false)
            throw new InvalidValueException(
                sprintf('Format "%s" is not available', $format)
            );

        if (($content = $this->$method($invoiceTax)) === null)
            throw new InvalidValueException('File content is empty');

        $response = new StreamedResponse(function () use ($content) {
            fputs(fopen('php://output', 'wb'), $content);
        });

        $response->headers->set('Content-Type', $this->mimetypes[$format]);

        $disposition = HeaderUtils::makeDisposition(
            $format == 'pdf' ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->filenames[$format]
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function getXml(InvoiceTax $invoiceTax): ?string
    {
        return $invoiceTax->getInvoice();
    }

    private function getPdf(InvoiceTax $invoiceTax): ?string
    {
        if ($invoiceTax->getOrder()[0]->getInvoiceType() == 55) {
            $file = $this->getPeopleFilePath($invoiceTax->getOrder()[0]->getOrder()->getClient());
            $danfe = new Danfe($invoiceTax->getInvoice(), 'P', 'A4', $file, 'I', '');
            return $danfe->render($file);
        }

        if ($invoiceTax->getOrder()[0]->getInvoiceType() == 57) {
            $file = $this->getPeopleFilePath($invoiceTax->getOrder()[0]->getOrder()->getProvider());
            $danfe = new Dacte($invoiceTax->getInvoice(), 'P', 'A4', $file, 'I', '');
            return $danfe->render($file);
        }
        
        return null;
    }

    private function getPng(InvoiceTax $invoiceTax): ?string
    {
        if ($invoiceTax->getOrder()[0]->getInvoiceType() == 65) {
            $connector = new Base64PrintConnector();
            $danfcepos = new DanfcePos($connector);
            $logopath = '../../fixtures/logo.png'; // Impressa no início da DANFCe
            $danfcepos->logo($logopath);
            $danfcepos->loadNFCe($invoiceTax->getInvoice());
            $danfcepos->imprimir();
            return $connector->getBase64Data();
        }
    }

    private function getPeopleFilePath(?People $people): string
    {
        $root  = $this->kernel->getProjectDir();
        $pixel = sprintf('%s/data/files/users/white-pixel.jpg', $root);
        $path  = $pixel;

        if ($people === null)
            return $pixel;

        if (($file = $people->getFile()) !== null) {
            $path  = $root . '/' . $file->getPath();

            if (strpos($file->getPath(), 'data/') !== false)
                $path = $root . '/' . str_replace('data/', 'public/', $file->getPath());

            $parts = pathinfo($path);
            if ($parts['extension'] != 'jpg')
                return $pixel;
        }

        return $path;
    }
}
