<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Service\EmitMdfeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmitMdfeServiceTest extends TestCase
{
    public function testRejectsEmptyDocumentList(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->service()->emit(['invoiceTaxIds' => [], 'vehicleId' => 1, 'driverId' => 1]);
    }

    public function testRejectsMissingVehicleOrDriver(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->service()->emit(['invoiceTaxIds' => [10]]);
    }

    private function service(): EmitMdfeService
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $tokenStorage = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class);
        $integration = $this->getMockBuilder(\ControleOnline\Service\IntegrationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new EmitMdfeService($em, $tokenStorage, $integration);
    }
}
