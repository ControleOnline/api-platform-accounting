<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\File;
use ControleOnline\Entity\People;
use ControleOnline\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;

class CteFiscalConfig
{
    public const KEYS = [
        'receita-federal-certificate-file',
        'receita-federal-certificate-password',
        'receita-federal-environment',
        'receita-federal-tax-regime',
        'receita-federal-ibge-code',
        'receita-federal-cte-enabled',
        'receita-federal-cte-serie',
        'receita-federal-cte-last-number',
        'receita-federal-cte-rntrc',
    ];

    public function __construct(
        private EntityManagerInterface $manager,
        private ConfigService $configService
    ) {
    }

    public function load(?People $company): array
    {
        $values = array_fill_keys(self::KEYS, null);
        if ($company === null) {
            return $values;
        }

        foreach (self::KEYS as $key) {
            $values[$key] = $this->configService->getConfig($company, $key);
        }

        $values['nextNumber'] = ((int) ($values['receita-federal-cte-last-number'] ?? 0)) + 1;
        $values['certificateBinary'] = $this->resolveCertificate($values['receita-federal-certificate-file'] ?? null);

        return $values;
    }

    public function incrementLastNumber(?People $company, int $authorizedNumber): void
    {
        if ($company === null) {
            return;
        }

        $module = $this->configService->discoveryModule('config');
        $this->configService->addConfig($company, 'receita-federal-cte-last-number', (string) $authorizedNumber, $module, 'private');
    }

    private function resolveCertificate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw) && str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            return isset($parts[1]) ? (string) base64_decode($parts[1], true) : null;
        }
        $id = (int) preg_replace('/\D+/', '', (string) $raw);
        if ($id <= 0 || !class_exists(File::class)) {
            return is_string($raw) && strlen($raw) > 40 ? $raw : null;
        }
        $file = $this->manager->getRepository(File::class)->find($id);
        if (!$file instanceof File) {
            return null;
        }

        return $file->getContent(true);
    }


}
