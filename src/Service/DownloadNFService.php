<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\NFe\Danfce;
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
                'filename' => $this->getDownloadFilename($invoiceTax, 'pdf'),
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
                $this->getDownloadFilename($invoiceTax, $format)
            )
        );

        return $response;
    }

    public function getDownloadFilename(InvoiceTax $invoiceTax, string $format): string
    {
        $format = strtolower($format);
        if ($format !== 'pdf') {
            return $this->filenames[$format] ?? 'nota_fiscal.bin';
        }

        $xml = $this->getXml($invoiceTax);
        $model = (int) ($invoiceTax->getInvoiceModel() ?: ($xml !== null ? $this->detectModel($xml) : 0));
        if ($xml === null) {
            return $this->filenames['pdf'];
        }

        $series = $this->extractXmlValue($xml, 'serie');
        $numberTag = $model === 57 ? 'nCT' : 'nNF';
        $number = $this->extractXmlValue($xml, $numberTag) ?: (string) $invoiceTax->getInvoiceNumber();
        if ($series === '' || $number === '') {
            return $this->filenames['pdf'];
        }

        $prefix = $model === 57 ? 'CTE' : ($model === 65 ? 'NFCE' : 'NFE');

        return sprintf('%s-%s-%s.pdf', $prefix, $this->sanitizeFilenamePart($series), $this->sanitizeFilenamePart($number));
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

            if ($model === 65) {
                return (new Danfce($xml))->render($logo);
            }

            $danfe = new Danfe($xml);
            return $danfe->render($logo);
        } catch (\Throwable $legacy) {
            if ($model === 57) {
                $dacte = new Dacte($xml, 'P', 'A4', $logo, 'I', '');
                return $dacte->render($logo);
            }

            if ($model === 65) {
                return (new Danfce($xml))->render($logo);
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

        $model = (int) $this->extractXmlValue($xml, 'mod');
        if ($model === 65) {
            return 65;
        }

        if (stripos($xml, '<CompNfse') !== false) {
            return 0;
        }

        return 55;
    }

    private function extractXmlValue(string $xml, string $tagName): string
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return '';
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[local-name()="%s"]', $tagName));
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)->textContent);
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '', trim($value)) ?? '';

        return $value;
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
