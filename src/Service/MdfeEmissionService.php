<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Service\Mdfe\MdfeEmissionProcessor;

/**
 * Handler do Messenger / IntegrationService quando queueName = "MdfeEmission".
 * Fluxo real: XML sped-mdfe → assinatura → SEFAZ.
 */
class MdfeEmissionService
{
    public function __construct(
        private MdfeEmissionProcessor $processor,
    ) {
    }

    public function integrate(Integration $integration): Mdfe
    {
        return $this->processor->processMessengerIntegration($integration);
    }
}
