<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Service\Mdfe\MdfeEmissionProcessor;
use ControleOnline\Service\MdfeEmissionService;
use PHPUnit\Framework\TestCase;

class MdfeEmissionServiceTest extends TestCase
{
    public function testIntegrateDelegatesToProcessor(): void
    {
        $integration = $this->createMock(Integration::class);
        $mdfe = $this->createMock(Mdfe::class);
        $processor = $this->createMock(MdfeEmissionProcessor::class);
        $processor->expects($this->once())
            ->method('processMessengerIntegration')
            ->with($integration)
            ->willReturn($mdfe);

        $service = new MdfeEmissionService($processor);
        $this->assertSame($mdfe, $service->integrate($integration));
    }
}
