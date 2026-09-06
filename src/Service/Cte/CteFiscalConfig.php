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
        'receita-federal-certificate-document',
        'receita-federal-environment',
        'receita-federal-tax-regime',
        'receita-federal-ibge-code',
        'receita-federal-state-registration',
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
            $values['nextNumber'] = 1;
            $values['certificateBinary'] = null;
            $values['certificateDocument'] = null;
            $values['certificateName'] = null;
            return $values;
        }

        foreach (self::KEYS as $key) {
            $values[$key] = $this->configService->getConfig($company, $key);
        }

        $values['nextNumber'] = ((int) ($values['receita-federal-cte-last-number'] ?? 0)) + 1;
        $values['certificateBinary'] = $this->resolveCertificate($values['receita-federal-certificate-file'] ?? null);
        $certificateIssuer = $this->certificateIssuer(
            $values['certificateBinary'],
            $values['receita-federal-certificate-password'] ?? null
        );
        $configuredDocument = preg_replace('/\D+/', '', (string) ($values['receita-federal-certificate-document'] ?? ''));
        $values['certificateDocument'] = $configuredDocument !== ''
            && substr($configuredDocument, 0, 8) === substr((string) $certificateIssuer['document'], 0, 8)
                ? $configuredDocument
                : $certificateIssuer['document'];
        $values['certificateName'] = $certificateIssuer['name'];

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

    /**
     * @return array{document: ?string, name: ?string}
     */
    private function certificateIssuer(?string $binary, mixed $password): array
    {
        $issuer = ['document' => null, 'name' => null];
        if ($binary === null || $binary === '' || $password === null || $password === '') {
            return $issuer;
        }

        $certificates = [];
        if (!@openssl_pkcs12_read($binary, $certificates, (string) $password) || empty($certificates['cert'])) {
            return $issuer;
        }

        $parsed = openssl_x509_parse($certificates['cert']);
        if (!is_array($parsed)) {
            return $issuer;
        }

        $commonName = (string) ($parsed['subject']['CN'] ?? '');
        if (preg_match('/(\d{14})/', $commonName, $match)) {
            $issuer['document'] = $match[1];
        }

        $name = trim((string) preg_replace('/[:\\s]*\d{14}.*/', '', $commonName));
        if ($name !== '') {
            $issuer['name'] = $name;
        }

        return $issuer;
    }
}
