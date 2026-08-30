<?php

namespace ControleOnline\Service\Cte;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\File;
use ControleOnline\Entity\People;
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

    public function __construct(private EntityManagerInterface $manager)
    {
    }

    public function load(?People $company): array
    {
        $values = array_fill_keys(self::KEYS, null);
        if ($company === null || !class_exists(Config::class)) {
            return $values;
        }

        $configs = $this->manager->getRepository(Config::class)->findBy(['people' => $company]);
        foreach ($configs as $config) {
            $key = $this->configKey($config);
            if ($key === '' || !array_key_exists($key, $values)) {
                continue;
            }
            $values[$key] = $this->configValue($config);
        }

        $values['nextNumber'] = ((int) ($values['receita-federal-cte-last-number'] ?? 0)) + 1;
        $values['certificateBinary'] = $this->resolveCertificate($values['receita-federal-certificate-file'] ?? null);

        return $values;
    }

    public function incrementLastNumber(?People $company, int $authorizedNumber): void
    {
        if ($company === null || !class_exists(Config::class)) {
            return;
        }

        $repo = $this->manager->getRepository(Config::class);
        $config = $repo->findOneBy([
            'people' => $company,
            'configKey' => 'receita-federal-cte-last-number',
        ]);
        if ($config === null) {
            foreach ($repo->findBy(['people' => $company]) as $candidate) {
                if ($this->configKey($candidate) === 'receita-federal-cte-last-number') {
                    $config = $candidate;
                    break;
                }
            }
        }
        if ($config === null) {
            $config = new Config();
            if (method_exists($config, 'setPeople')) {
                $config->setPeople($company);
            }
            if (method_exists($config, 'setConfigKey')) {
                $config->setConfigKey('receita-federal-cte-last-number');
            }
            $this->manager->persist($config);
        }
        if (method_exists($config, 'setValue')) {
            $config->setValue((string) $authorizedNumber);
        } elseif (method_exists($config, 'setConfigValue')) {
            $config->setConfigValue((string) $authorizedNumber);
        }
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

    private function configKey(object $config): string
    {
        foreach (['getConfigKey', 'getKey', 'getName'] as $method) {
            if (method_exists($config, $method)) {
                return (string) $config->{$method}();
            }
        }

        return '';
    }

    private function configValue(object $config): mixed
    {
        foreach (['getValue', 'getConfigValue', 'getContent'] as $method) {
            if (method_exists($config, $method)) {
                return $config->{$method}();
            }
        }

        return null;
    }
}
