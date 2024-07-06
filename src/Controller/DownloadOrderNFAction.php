<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\DownloadNFService;
use Symfony\Component\HttpFoundation\Request;

class DownloadOrderNFAction
{
    public function __invoke(InvoiceTax $data, DownloadNFService $downloadNFService, Request $request)
    {
        $format = $request->query->get('format', 'pdf');
        return $downloadNFService->downloadNf($data, $format);
    }
}
