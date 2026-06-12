<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   ap_id: int|string,
 *   ap_type: string,
 *   ap_name: string,
 *   ap_description: string|null,
 *   ap_is_default: int|string,
 *   ap_configuration: string,
 * }
 */
final readonly class DbalAiProviderRepository extends DbalRepository implements AiProviderRepository
{
    public const TABLE_NAME = 'ai_provider_configuration';

    /**
     * @param TransformerInterface<RowTypeAlias, AiProvider> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalAiProviderTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getById(AiProviderId $id): AiProvider
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'ap')
            ->where('ap.id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if (! $row) {
            throw new AiProviderNotFoundException(['id' => $id->value], 'AI provider configuration not found');
        }

        return $this->transformer->transform($row);
    }

    public function findOneByName(AiProviderName $name): ?AiProvider
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'ap')
            ->where('ap.name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        return $row ? $this->transformer->transform($row) : null;
    }

    public function findDefault(): ?AiProvider
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'ap')
            ->where('ap.is_default = 1')
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        return $row ? $this->transformer->transform($row) : null;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?AiProviderCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'ap');

        if ($criteria instanceof AiProviderCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(
                array_map(fn (array $row): AiProvider => $this->transformer->transform($row), $rows),
                AiProvider::class
            );
        }

        $count = $this->countOnQueryBuilder($qb); // must be done before fetching all rows

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(
                array_map(fn (array $row): AiProvider => $this->transformer->transform($row), $rows),
                AiProvider::class
            ),
            totalItems: $count,
            currentPage: $criteria->getPage(),
            itemsPerPage: $criteria->getItemsPerPage(),
        );
    }

    public function countAll(): int
    {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE_NAME, 'ap')
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }

    public function add(AiProvider $aiProvider): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert(self::TABLE_NAME)
            ->values([
                'type' => ':type',
                'name' => ':name',
                'description' => ':description',
                'is_default' => ':is_default',
                'configuration' => ':configuration',
                'created_at' => ':created_at',
                'updated_at' => ':updated_at',
            ])
            ->setParameter('type', $aiProvider->type->value)
            ->setParameter('name', $aiProvider->name->value)
            ->setParameter('description', $aiProvider->description?->value)
            ->setParameter('is_default', $aiProvider->isDefault ? 1 : 0)
            ->setParameter('configuration', $this->encodeConfiguration($aiProvider))
            ->setParameter('created_at', time())
            ->setParameter('updated_at', time());

        $qb->executeStatement();

        $this->setId($aiProvider, new AiProviderId((int) $this->connection->lastInsertId()));
    }

    public function update(AiProvider ...$aiProviders): void
    {
        if ($aiProviders === []) {
            return;
        }

        $this->connection->transactional(function () use ($aiProviders): void {
            foreach ($aiProviders as $aiProvider) {
                $aiProviderId = $aiProvider->id();
                Assert::isInstanceOf($aiProviderId, AiProviderId::class);

                $qb = $this->connection->createQueryBuilder();

                $qb->update(self::TABLE_NAME)
                    ->set('name', ':name')
                    ->set('description', ':description')
                    ->set('is_default', ':is_default')
                    ->set('configuration', ':configuration')
                    ->set('updated_at', ':updated_at')
                    ->where('id = :id')
                    ->setParameter('id', $aiProviderId->value)
                    ->setParameter('name', $aiProvider->name->value)
                    ->setParameter('description', $aiProvider->description?->value)
                    ->setParameter('is_default', $aiProvider->isDefault ? 1 : 0)
                    ->setParameter('configuration', $this->encodeConfiguration($aiProvider))
                    ->setParameter('updated_at', time());

                $qb->executeStatement();
            }
        });
    }

    public function delete(AiProvider $aiProvider): void
    {
        $aiProviderId = $aiProvider->id();
        Assert::isInstanceOf($aiProviderId, AiProviderId::class);

        $qb = $this->connection->createQueryBuilder();

        $qb->delete(self::TABLE_NAME)
            ->where('id = :id')
            ->setParameter('id', $aiProviderId->value);

        $qb->executeStatement();
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'ap'): array
    {
        return [
            "{$alias}.id AS ap_id",
            "{$alias}.type AS ap_type",
            "{$alias}.name AS ap_name",
            "{$alias}.description AS ap_description",
            "{$alias}.is_default AS ap_is_default",
            "{$alias}.configuration AS ap_configuration",
        ];
    }

    private function filterByCriteria(QueryBuilder $qb, AiProviderCriteria $criteria): void
    {
        foreach ($criteria->getNames() as $operator => $names) {
            if ($operator === AiProviderCriteria::OPERATOR_LIKE) {
                $likeExpressions = [];
                foreach ($names as $index => $name) {
                    $parameter = "name_like_{$index}";
                    $likeExpressions[] = $qb->expr()->like('ap.name', ":{$parameter}");
                    $qb->setParameter($parameter, '%' . $name . '%');
                }
                $qb->andWhere($qb->expr()->or(...$likeExpressions));

                continue;
            }

            $qb->andWhere($qb->expr()->in('ap.name', ':names'))
                ->setParameter('names', $names, ArrayParameterType::STRING);
        }

        if ($criteria->getTypes() !== []) {
            $qb->andWhere($qb->expr()->in('ap.type', ':types'))
                ->setParameter(
                    'types',
                    array_map(static fn (AiProviderTypeEnum $type): string => $type->value, $criteria->getTypes()),
                    ArrayParameterType::STRING
                );
        }

        if ($criteria->getIsDefault() !== null) {
            $qb->andWhere('ap.is_default = :is_default')
                ->setParameter('is_default', $criteria->getIsDefault() ? 1 : 0);
        }

        $this->sort($qb, 'ap', $criteria);
    }

    private function countOnQueryBuilder(QueryBuilder $qb): int
    {
        $qb = clone $qb; // avoid modifying the initial query builder

        $count = $qb
            ->select('COUNT(DISTINCT ap.id)')
            ->setFirstResult(0) // reset any pagination
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::integer($count);

        return $count;
    }

    private function encodeConfiguration(AiProvider $aiProvider): string
    {
        return json_encode([
            'endpoint_url' => $aiProvider->endpointUrl->value,
            'api_token' => $aiProvider->apiToken->value,
            'default_model' => $aiProvider->defaultModel->value,
            'fast_model' => $aiProvider->fastModel?->value,
        ], \JSON_THROW_ON_ERROR);
    }
}
