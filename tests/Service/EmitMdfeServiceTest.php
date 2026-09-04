<?php
namespace ControleOnline\Tests\Service;
use ControleOnline\Service\EmitMdfeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ControleOnline\Service\IntegrationService;
final class EmitMdfeServiceTest extends TestCase {
    public function testRequiresAtLeastOneInvoice(): void {
        $service = new EmitMdfeService($this->createMock(EntityManagerInterface::class), $this->createMock(IntegrationService::class), $this->createMock(TokenStorageInterface::class));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $service->create([], 1, 1, null);
    }
    public function testRequiresVehicleAndDriver(): void {
        $service = new EmitMdfeService($this->createMock(EntityManagerInterface::class), $this->createMock(IntegrationService::class), $this->createMock(TokenStorageInterface::class));
        $this->expectExceptionMessage('Veículo e motorista são obrigatórios.');
        $service->create([1], 0, 0, null);
    }
}
