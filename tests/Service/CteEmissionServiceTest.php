<?php

namespace ControleOnline\Tests\Service {

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Service\Cte\CteEmissionProcessor;
use ControleOnline\Service\CteEmissionService;
use PHPUnit\Framework\TestCase;

final class CteEmissionServiceTest extends TestCase
{
    public function testIntegrateDelegatesTheIntegrationToTheProcessor(): void
    {
        $processor = $this->createMock(CteEmissionProcessor::class);
        $integration = $this->createMock(Integration::class);
        $expected = new InvoiceTax();
        $processor->expects(self::once())
            ->method('processMessengerIntegration')
            ->with($integration)
            ->willReturn($expected);

        self::assertSame($expected, (new CteEmissionService($processor))->integrate($integration));
    }
}
}
