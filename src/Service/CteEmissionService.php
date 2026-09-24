<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\Cte\CteEmissionProcessor;

/**
 * Handler do Messenger / IntegrationService quando queueName = "CteEmission".
 * Fluxo real: XML → assinatura → SEFAZ.
 * Nunca inventa chave/número nem status Closed sem autorização.
 */
class CteEmissionService
{
    public function __construct(
        private CteEmissionProcessor $processor,
    ) {
    }

    public function integrate(Integration $integration): InvoiceTax
    {
        return $this->processor->processMessengerIntegration($integration);
    }
}
