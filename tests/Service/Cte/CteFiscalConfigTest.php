<?php

namespace ControleOnline\Tests\Service\Cte;

use ControleOnline\Service\Cte\CteFiscalConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CteFiscalConfigTest extends TestCase
{
    public function testKeysCoverCertificateEnvironmentAndSeries(): void
    {
        self::assertContains('receita-federal-certificate-file', CteFiscalConfig::KEYS);
        self::assertContains('receita-federal-certificate-password', CteFiscalConfig::KEYS);
        self::assertContains('receita-federal-environment', CteFiscalConfig::KEYS);
        self::assertContains('receita-federal-cte-serie', CteFiscalConfig::KEYS);
        self::assertContains('receita-federal-cte-last-number', CteFiscalConfig::KEYS);
    }

    public function testLoadWithoutCompanyReturnsEmptyKeysAndNextNumberOne(): void
    {
        $reflection = new ReflectionClass(CteFiscalConfig::class);
        /** @var CteFiscalConfig $service */
        $service = $reflection->newInstanceWithoutConstructor();

        $manager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $prop = $reflection->getProperty('manager');
        $prop->setAccessible(true);
        $prop->setValue($service, $manager);

        $values = $service->load(null);

        self::assertNull($values['receita-federal-certificate-file']);
        self::assertSame(1, $values['nextNumber']);
        self::assertArrayHasKey('certificateBinary', $values);
    }
}
