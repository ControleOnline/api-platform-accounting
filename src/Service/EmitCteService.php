<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\IntegrationService;
use ControleOnline\Service\StatusService;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmitCteService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private StatusService $statusService,
        private TokenStorageInterface $tokenStorage,
        private IntegrationService $integrationService,
    ) {
    }

    public function emit(array $invoiceTaxIds, string $cfop, array $extra = []): \ControleOnline\Entity\Integration
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $invoiceTaxIds))));
        if (count($ids) < 1) {
            throw new BadRequestHttpException('Selecione ao menos uma NF para emitir o CT-e.');
        }
        if (trim($cfop) === '') {
            throw new BadRequestHttpException('CFOP é obrigatório.');
        }

        $invoices = $this->manager->getRepository(InvoiceTax::class)->findBy(['id' => $ids]);
        if (count($invoices) !== count($ids)) {
            throw new BadRequestHttpException('Uma ou mais NFs não foram encontradas.');
        }

        $this->assertTenantCanEmit($invoices);

        $busy = $this->manager->createQueryBuilder()
            ->select('it.id')
            ->from(InvoiceTax::class, 'it')
            ->where('it.id IN (:ids)')
            ->andWhere('it.integration IS NOT NULL')
            ->setParameter('ids', $ids)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($busy) {
            throw new BadRequestHttpException('Uma ou mais NFs já estão em emissão ou emitidas.');
        }

        foreach ($invoices as $invoice) {
            if ($invoice->getCte() instanceof InvoiceTax) {
                throw new BadRequestHttpException(sprintf(
                    'NF %s já possui CT-e vinculado.',
                    $invoice->getInvoiceNumber()
                ));
            }
        }

        // Build payload and create integration
        $payload = json_encode([
            'invoiceTaxIds' => $ids,
            'cfop' => $cfop,
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE);

        $user = $this->tokenStorage->getToken()?->getUser();
        $integration = $this->integrationService->addIntegration($payload, 'CteEmission', null, $user);

        // Bind NFs to the task immediately so /invoice_taxes/without-cte excludes them
        // while emission is still open/pending/processing (cte_id may still be null).
        foreach ($invoices as $invoice) {
            $invoice->setIntegration($integration);
            $this->manager->persist($invoice);
        }
        $this->manager->flush();

        return $integration;
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
            throw new AccessDeniedHttpException('Sem permissão para emitir CT-e.');
        }

        foreach ($invoices as $invoice) {
            $companyId = (int) ($invoice->getCompany()?->getId() ?? 0);
            $issuerId = (int) ($invoice->getIssuer()?->getId() ?? 0);
            if (!in_array($companyId, $allowed, true) && !in_array($issuerId, $allowed, true)) {
                throw new AccessDeniedHttpException('NF fora do escopo da empresa autenticada.');
            }
        }
    }
}
