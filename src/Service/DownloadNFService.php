<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\NFe\Danfe;
use NFePHP\POS\DanfcePos;
use NFePHP\POS\PrintConnectors\Base64PrintConnector;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;

class DownloadNFService
{
    private array $mimetypes = [
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'base64' => 'application/json',
    ];

    private array $filenames = [
        'xml' => 'nota_fiscal.xml',
        'pdf' => 'nota_fiscal.pdf',
    ];

    public function __construct(private KernelInterface $kernel)
    {
    }

    public function downloadNf(InvoiceTax $invoiceTax, $format = 'pdf')
    {
        $format = strtolower((string) $format);
        if ($format === 'base64') {
            $pdf = $this->getPdf($invoiceTax);
            if ($pdf === null || $pdf === '') {
                throw new BadRequestHttpException('Não foi possível montar o PDF da NF.');
            }

            return new JsonResponse([
                'mime' => 'application/pdf',
                'pdf' => base64_encode($pdf),
                'invoiceNumber' => $invoiceTax->getInvoiceNumber(),
            ]);
        }

        $method = 'get' . ucfirst($format);
        if (method_exists($this, $method) === false) {
            throw new BadRequestHttpException(sprintf('Format "%s" is not available', $format));
        }

        $content = $this->$method($invoiceTax);
        if ($content === null || $content === '') {
            throw new BadRequestHttpException('File content is empty');
        }

        $response = new StreamedResponse(static function () use ($content) {
            fputs(fopen('php://output', 'wb'), $content);
        });
        $response->headers->set('Content-Type', $this->mimetypes[$format] ?? 'application/octet-stream');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                $format === 'pdf' ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $this->filenames[$format] ?? 'nota_fiscal.bin'
            )
        );

        return $response;
    }

    private function getXml(InvoiceTax $invoiceTax): ?string
    {
        $xml = $invoiceTax->getInvoice();
        return is_string($xml) && trim($xml) !== '' ? $xml : null;
    }

    private function getPdf(InvoiceTax $invoiceTax): ?string
    {
        $xml = $this->getXml($invoiceTax);
        if ($xml === null) {
            return null;
        }

        $model = (int) ($invoiceTax->getInvoiceModel() ?: $this->detectModel($xml));
        $logo = $this->getPeopleFilePath($invoiceTax->getIssuer() ?: $invoiceTax->getCompany());

        try {
            if ($model === 57) {
                $dacte = new Dacte($xml);
                return $dacte->render($logo);
            }

            $danfe = new Danfe($xml);
            return $danfe->render($logo);
        } catch (\Throwable $legacy) {
            if ($model === 57) {
                $dacte = new Dacte($xml, 'P', 'A4', $logo, 'I', '');
                return $dacte->render($logo);
            }

            $danfe = new Danfe($xml, 'P', 'A4', $logo, 'I', '');
            return $danfe->render($logo);
        }
    }

    private function detectModel(string $xml): int
    {
        if (stripos($xml, '<CTe') !== false || stripos($xml, '<cteProc') !== false) {
            return 57;
        }

        return 55;
    }

    private function getPng(InvoiceTax $invoiceTax): ?string
    {
        $xml = $this->getXml($invoiceTax);
        if ($xml === null) {
            return null;
        }

        $connector = new Base64PrintConnector();
        $danfcepos = new DanfcePos($connector);
        $danfcepos->loadNFCe($xml);
        $danfcepos->imprimir();

        return $connector->getBase64Data();
    }

    private function getPeopleFilePath(?People $people): string
    {
        $root = $this->kernel->getProjectDir();
        $pixel = sprintf('%s/data/files/users/white-pixel.jpg', $root);
        if ($people === null || !method_exists($people, 'getFile') || $people->getFile() === null) {
            return $pixel;
        }

        $file = $people->getFile();
        $path = $root . '/' . $file->getPath();
        if (strpos((string) $file->getPath(), 'data/') !== false) {
            $path = $root . '/' . str_replace('data/', 'public/', $file->getPath());
        }

        $parts = pathinfo($path);
        if (($parts['extension'] ?? '') !== 'jpg') {
            return $pixel;
        }

        return $path;
    }
}
