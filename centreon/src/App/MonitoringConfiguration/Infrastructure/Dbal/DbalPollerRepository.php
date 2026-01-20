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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalGlobalMacroRepository as GlobalMacroRowTypeAlias
 *
 * @phpstan-type RowTypeAlias = array{
 *   poller_id: int,
 *   poller_name: string,
 * }
 * @phpstan-type JoinRowTypeAlias = array{
 *   poller_id: int,
 *   poller_name: string,
 *   gm_resource_id: int,
 *   gm_resource_name: string,
 *   gm_resource_line: string,
 *   gm_resource_comment: string|null,
 *   gm_resource_activate: '0'|'1',
 *   gm_is_password: 0|1,
 * }
 */
final readonly class DbalPollerRepository extends DbalRepository implements PollerRepository
{
    public const TABLE_NAME = 'nagios_server';
    public const GLOBAL_MACRO_JOIN_TABLE_NAME = 'cfg_resource_instance_relations';

    /**
     * @param TransformerInterface<RowTypeAlias, Poller> $pollerTransformer
     * @param TransformerInterface<GlobalMacroRowTypeAlias, GlobalMacro> $globalMacroTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalPollerTransformer::class)]
        private TransformerInterface $pollerTransformer,

        #[Autowire(service: DbalGlobalMacroTransformer::class)]
        private TransformerInterface $globalMacroTransformer,
    ) {
    }

    public function findAllByGlobalMacro(GlobalMacro $globalMacro): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns(), ...DbalGlobalMacroRepository::getSelectColumns())
            ->from(self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg1')
            ->innerJoin('pg1', self::TABLE_NAME, 'p', 'p.id = pg1.instance_id')
            ->leftJoin('p', self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg2', 'pg2.instance_id = p.id')
            ->leftJoin('pg2', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg2.resource_id') // ensures still filter on relevant pollers
            ->where('pg1.resource_id = :resource_id')
            ->setParameter('resource_id', $globalMacro->id()->value);

        /**
         * @var array<JoinRowTypeAlias> $rows
         */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $pollerRows = [];
        $globalMacroRows = [];

        foreach ($rows as $row) {
            $pollerId = $row['poller_id'];
            $globalMacroId = $row['gm_resource_id'];

            $pollerRows[$pollerId] ??= $row;

            if ($globalMacroId !== null) {
                /** @var GlobalMacroRowTypeAlias $globalMacroRow */
                $globalMacroRow = [
                    'gm_resource_id' => $row['gm_resource_id'],
                    'gm_resource_name' => $row['gm_resource_name'],
                    'gm_resource_line' => $row['gm_resource_line'],
                    'gm_resource_comment' => $row['gm_resource_comment'],
                    'gm_resource_activate' => $row['gm_resource_activate'],
                    'gm_is_password' => $row['gm_is_password'],
                ];
                $globalMacroRows[$pollerId][$globalMacroId] = $globalMacroRow;
            }
        }

        return $this->createPollers($pollerRows, $globalMacroRows);
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'p'): array
    {
        return [
            "{$alias}.id AS poller_id",
            "{$alias}.name AS poller_name",
        ];
    }

    /**
     * @param array<RowTypeAlias> $rows
     * @param array<array<GlobalMacroRowTypeAlias>>|null $globalMacroRowsByPollerId
     *
     * @return Collection<Poller>
     */
    private function createPollers(array $rows, ?array $globalMacroRowsByPollerId = null): Collection
    {
        // fetch all global macros of given pollers
        if ($globalMacroRowsByPollerId !== null) {
            $globalMacroQb = $this->connection->createQueryBuilder();
            $globalMacroQb->select('p.id AS poller_id', ...DbalGlobalMacroRepository::getSelectColumns())
                ->from(self::TABLE_NAME, 'p')
                ->innerJoin('p', self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg', 'p.id = pg.instance_id')
                ->innerJoin('pg', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg.resource_id')
                ->where($globalMacroQb->expr()->in('p.id', array_map('strval', array_column($rows, 'poller_id'))));

            /**
             * @var array<JoinRowTypeAlias> $globalMacroRows
             */
            $globalMacroRows = $globalMacroQb->executeQuery()->fetchAllAssociative();

            /**
             * @var array<array<GlobalMacroRowTypeAlias>> $globalMacroRowsByPollerId
             */
            $globalMacroRowsByPollerId = [];
            foreach ($globalMacroRows as $globalMacrosRow) {
                $globalMacroRowsByPollerId['poller_id'][] = $globalMacrosRow;
            }
        }

        $pollers = [];
        foreach ($rows as $row) {
            $pollerId = $row['poller_id'];
            $pollers[$pollerId] ??= $this->createPoller(
                $row,
                $globalMacroRowsByPollerId[$pollerId] ?? null,
            );
        }

        return new Collection(array_values($pollers), Poller::class);
    }

    /**
     * @param RowTypeAlias $row
     * @param array<GlobalMacroRowTypeAlias>|null $globalMacroRows
     */
    private function createPoller(array $row, ?array $globalMacroRows = null): Poller
    {
        // fetch all global macros of a given poller
        if ($globalMacroRows === null) {
            $globalMacroQb = $this->connection->createQueryBuilder();
            $globalMacroQb->select(...DbalGlobalMacroRepository::getSelectColumns())
                ->from(self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg')
                ->innerJoin('pg', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg.resource_id')
                ->where('pg.instance_id = :poller_id')
                ->setParameter('poller_id', $row['poller_id']);

            /**
             * @var array<GlobalMacroRowTypeAlias> $globalMacroRows
             */
            $globalMacroRows = $globalMacroQb->executeQuery()->fetchAllAssociative();
        }

        $poller = $this->pollerTransformer->transform($row);

        $this->hydrateToManyRelation(
            primaryEntity: $poller,
            relatedRows: $globalMacroRows,
            relatedIdKey: 'gm_resource_id',
            relatedFactoryCallback: $this->globalMacroTransformer->transform(...),
            relationCallback: static function (Poller $poller, GlobalMacro $globalMacro): void {
                $poller->addGlobalMacro($globalMacro);
            },
        );

        return $poller;
    }
}
