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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalServiceTransformer
 * @phpstan-import-type MacroRowTypeAlias from DbalServiceTransformer
 */
final readonly class DbalServiceRepository extends DbalRepository implements ServiceRepository
{
    private const TABLE_NAME = 'service';

    /**
     * @param TransformerInterface<RowTypeAlias, Service> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalServiceTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('s.service_id', 's.service_description')
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->innerJoin('hsr', 'host', 'h', 'h.host_id = hsr.host_host_id')
            // A service reachable through more than one relation row (another host, or a shared
            // hostgroup) is not exclusively this host's, and is left alone — mirrors legacy
            // `findServiceIdsExclusivelyLinkedToHostId()`.
            ->innerJoin(
                's',
                '(SELECT service_service_id FROM host_service_relation GROUP BY service_service_id HAVING COUNT(*) = 1)',
                'uniq',
                'uniq.service_service_id = s.service_id',
            )
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            ->andWhere("h.host_register = '1'");

        /** @var list<array{service_id: int|string, service_description: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        if ($rows === []) {
            return new Collection([], Service::class);
        }

        $serviceIds = array_map(static fn (array $row): int => (int) $row['service_id'], $rows);
        $macrosByServiceId = $this->findMacroRowsByServiceIds($serviceIds);

        $services = array_map(
            function (array $row) use ($hostId, $macrosByServiceId): Service {
                /** @var RowTypeAlias $withMacros */
                $withMacros = [
                    'service_id' => $row['service_id'],
                    'service_description' => $row['service_description'],
                    'host_id' => $hostId->value,
                    'macros' => $macrosByServiceId[(int) $row['service_id']] ?? [],
                ];

                return $this->transformer->transform($withMacros);
            },
            $rows,
        );

        return new Collection($services, Service::class);
    }

    public function remove(Service $service): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->where($qb->expr()->eq('service_id', $qb->createNamedParameter($service->id()->value, ParameterType::INTEGER)))
            ->executeStatement();
    }

    /**
     * @param list<int> $serviceIds
     *
     * @return array<int, list<MacroRowTypeAlias>>
     */
    private function findMacroRowsByServiceIds(array $serviceIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('svc_svc_id', 'svc_macro_name AS name', 'svc_macro_value AS value', 'is_password', 'description')
            ->from('on_demand_macro_service')
            ->where($qb->expr()->in('svc_svc_id', $qb->createNamedParameter($serviceIds, ArrayParameterType::INTEGER)))
            ->orderBy('macro_order');

        /** @var list<array{svc_svc_id: int|string, name: string, value: string, is_password: string|int, description: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['svc_svc_id']][] = [
                'name' => $row['name'],
                'value' => $row['value'],
                'is_password' => $row['is_password'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
