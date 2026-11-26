<?php

namespace ControleOnline\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Service\PeopleService;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

final class MdfeSecurityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly PeopleService $peopleService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->applySecurityFilter($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->applySecurityFilter($queryBuilder, $resourceClass);
    }

    private function applySecurityFilter(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if ($resourceClass !== Mdfe::class) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $requestedProvider = $request?->query->all()['provider'] ?? $request?->query->all()['company'] ?? null;
        $requestedIds = is_array($requestedProvider) ? $requestedProvider : [$requestedProvider];
        $requestedIds = array_values(array_filter(array_map(
            static fn ($value): int => (int) preg_replace('/\D+/', '', (string) $value),
            $requestedIds
        ), static fn (int $id): bool => $id > 0));

        $allowedIds = array_map(
            static fn ($company): int => (int) $company->getId(),
            $this->peopleService->getMyCompanies()
        );
        $providerIds = $requestedIds === []
            ? array_values($allowedIds)
            : array_values(array_intersect($requestedIds, $allowedIds));
        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;

        if (!$rootAlias || $providerIds === []) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('IDENTITY(%s.company) IN(:mdfeCompanies)', $rootAlias))
            ->setParameter('mdfeCompanies', $providerIds);
    }
}
