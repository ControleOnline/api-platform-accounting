<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\DeliveryCourierVehicle;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Entity\People;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmitMdfeService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private TokenStorageInterface $tokenStorage,
        private IntegrationService $integrationService,
    ) {
    }

    public function emit(array $payload): Mdfe
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            $payload['invoiceTaxIds'] ?? $payload['selected'] ?? []
        ))));
        $vehicleId = (int) ($payload['vehicleId'] ?? 0);
        $driverId = (int) ($payload['driverId'] ?? 0);
        $insurerId = (int) ($payload['insurerId'] ?? 0);

        if ($ids === []) {
            throw new BadRequestHttpException('Selecione ao menos um CT-e/NF-e para o MDF-e.');
        }
        if ($vehicleId < 1 || $driverId < 1) {
            throw new BadRequestHttpException('Veículo e motorista são obrigatórios.');
        }

        $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
        if (count($invoices) !== count($ids)) {
            throw new BadRequestHttpException('Um ou mais documentos não foram encontrados.');
        }

        $this->assertTenantCanEmit($invoices);

        $companyIds = [];
        foreach ($invoices as $invoice) {
            $companyIds[] = (int) ($invoice->getCompany()?->getId() ?? $invoice->getProvider()?->getId() ?? 0);
        }
        $companyIds = array_values(array_unique(array_filter($companyIds)));
        if (count($companyIds) !== 1) {
            throw new BadRequestHttpException('Todos os documentos devem pertencer à mesma empresa.');
        }

        $company = $this->manager->getRepository(People::class)->find($companyIds[0]);
        $vehicle = $this->manager->getRepository(DeliveryCourierVehicle::class)->find($vehicleId);
        $driver = $this->manager->getRepository(People::class)->find($driverId);
        if (!$company instanceof People || !$vehicle instanceof DeliveryCourierVehicle || !$driver instanceof People) {
            throw new BadRequestHttpException('Empresa, veículo ou motorista inválido.');
        }

        $mdfe = (new Mdfe())
            ->setCompany($company)
            ->setVehicle($vehicle)
            ->setDriver($driver)
            ->setStatus('pending');
        if ($insurerId > 0) {
            $insurer = $this->manager->getRepository(People::class)->find($insurerId);
            if ($insurer instanceof People) {
                $mdfe->setInsurer($insurer);
            }
        }
        foreach ($invoices as $invoice) {
            $mdfe->addDocument($invoice);
        }

        $this->manager->persist($mdfe);
        $this->manager->flush();

        $user = $this->tokenStorage->getToken()?->getUser();
        $this->integrationService->addIntegration(
            json_encode(['mdfeId' => $mdfe->getId()], JSON_UNESCAPED_UNICODE),
            'MdfeEmission',
            null,
            $user
        );

        return $mdfe;
    }

    private function assertTenantCanEmit(array $invoices): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!is_object($user)) {
            throw new AccessDeniedHttpException('Authentication required.');
        }
        $roles = method_exists($user, 'getRoles') ? (array) $user->getRoles() : [];
        if (in_array('ROLE_SUPER', $roles, true)) {
            return;
        }

        $people = method_exists($user, 'getPeople') ? $user->getPeople() : null;
        $peopleId = $people instanceof People ? (int) $people->getId() : 0;
        $allowed = $peopleId > 0 ? [$peopleId] : [];
        if ($peopleId > 0) {
            try {
                $linked = $this->manager->getConnection()->fetchFirstColumn(
                    'SELECT company_id FROM people_link WHERE people_id = ? AND (enabled = 1 OR enabled IS NULL)',
                    [$peopleId],
                    [ParameterType::INTEGER]
                );
                foreach ($linked ?: [] as $companyId) {
                    $allowed[] = (int) $companyId;
                }
            } catch (\Throwable) {
            }
        }
        $allowed = array_values(array_unique(array_filter($allowed)));
        if ($allowed === []) {
            throw new AccessDeniedHttpException('Sem permissão para emitir MDF-e.');
        }

        foreach ($invoices as $invoice) {
            $companyId = (int) ($invoice->getCompany()?->getId() ?? 0);
            $providerId = (int) ($invoice->getProvider()?->getId() ?? 0);
            $issuerId = (int) ($invoice->getIssuer()?->getId() ?? 0);
            if (
                !in_array($companyId, $allowed, true)
                && !in_array($providerId, $allowed, true)
                && !in_array($issuerId, $allowed, true)
            ) {
                throw new AccessDeniedHttpException('Documento fora do escopo da empresa autenticada.');
            }
        }
    }
}
