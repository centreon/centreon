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

namespace App\ActivityLogging\Infrastructure\Dbal;

use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\ReadModel\ServiceParentLink;
use App\ActivityLogging\Domain\Repository\ServiceParentRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Port of the legacy CentreonLogAction::getHostId() / getHostName() / getHostGroupName(), batched per page.
 *
 * The parents are read from the service's last logged changes (realtime database), their names from the
 * configuration (centreon database), falling back on the logs and the realtime hosts for deleted ones.
 * Both databases may live on different servers, hence no cross-database join.
 */
final readonly class DbalServiceParentRepository implements ServiceParentRepository
{
    private const HOST_FIELDS = ['service_hPars', 'hostId'];
    private const HOST_GROUP_FIELDS = ['service_hgPars'];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realtimeConnection,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $configurationConnection,
    ) {
    }

    public function findByServiceIds(int ...$serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $parentIds = $this->findParentIds(array_values(array_unique($serviceIds)));

        $hostIds = [];
        $hostGroupIds = [];
        foreach ($parentIds as ['type' => $type, 'ids' => $ids]) {
            if ($type === TargetTypeEnum::Host) {
                $hostIds = [...$hostIds, ...$ids];
            } else {
                $hostGroupIds = [...$hostGroupIds, ...$ids];
            }
        }
        $hostNames = $this->findHostNames(array_values(array_unique($hostIds)));
        $hostGroupNames = $this->findHostGroupNames(array_values(array_unique($hostGroupIds)));

        $links = [];
        foreach ($parentIds as $serviceId => ['type' => $type, 'ids' => $ids]) {
            $names = $type === TargetTypeEnum::Host ? $hostNames : $hostGroupNames;
            $links[$serviceId] = new ServiceParentLink(
                $type,
                array_combine($ids, array_map(static fn (int $id): ?string => $names[$id] ?? null, $ids)),
            );
        }

        return $links;
    }

    /**
     * The last non-empty hosts recorded for each service, else its last non-empty host groups.
     *
     * @param list<int> $serviceIds
     *
     * @return array<int, array{type: TargetTypeEnum, ids: non-empty-list<int>}>
     */
    private function findParentIds(array $serviceIds): array
    {
        $qb = $this->realtimeConnection->createQueryBuilder();
        $qb->select('l.object_id', 'm.field_name', 'm.field_value')
            ->from('log_action', 'l')
            ->innerJoin('l', 'log_action_modification', 'm', 'm.action_log_id = l.action_log_id')
            ->where('l.object_type = :serviceType')
            ->andWhere('l.object_id IN (:serviceIds)')
            ->andWhere('m.field_name IN (:fieldNames)')
            ->andWhere("m.field_value <> ''")
            ->orderBy('l.action_log_date', 'DESC')
            ->addOrderBy('l.action_log_id', 'DESC')
            ->setParameter('serviceType', LogActionTokenMapper::targetTypeToToken(TargetTypeEnum::Service))
            ->setParameter('serviceIds', $serviceIds, ArrayParameterType::INTEGER)
            ->setParameter('fieldNames', [...self::HOST_FIELDS, ...self::HOST_GROUP_FIELDS], ArrayParameterType::STRING);

        /** @var array<int, non-empty-list<int>> $lastHostIds */
        $lastHostIds = [];
        /** @var array<int, non-empty-list<int>> $lastHostGroupIds */
        $lastHostGroupIds = [];

        /** @var array{object_id: int|string, field_name: string, field_value: string} $row */
        foreach ($qb->executeQuery()->iterateAssociative() as $row) {
            $serviceId = (int) $row['object_id'];
            $ids = $this->parseIds($row['field_value']);
            if ($ids === []) {
                continue;
            }

            if (in_array($row['field_name'], self::HOST_FIELDS, true)) {
                $lastHostIds[$serviceId] ??= $ids;
            } else {
                $lastHostGroupIds[$serviceId] ??= $ids;
            }
        }

        $parentIds = [];
        foreach ($serviceIds as $serviceId) {
            if (isset($lastHostIds[$serviceId])) {
                $parentIds[$serviceId] = ['type' => TargetTypeEnum::Host, 'ids' => $lastHostIds[$serviceId]];
            } elseif (isset($lastHostGroupIds[$serviceId])) {
                $parentIds[$serviceId] = ['type' => TargetTypeEnum::HostGroup, 'ids' => $lastHostGroupIds[$serviceId]];
            }
        }

        return $parentIds;
    }

    /**
     * @param list<int> $hostIds
     *
     * @return array<int, string>
     */
    private function findHostNames(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        $qb = $this->configurationConnection->createQueryBuilder();
        $qb->select('h.host_id AS id', 'h.host_name AS name')
            ->from('host', 'h')
            ->where("h.host_register = '1'")
            ->andWhere('h.host_id IN (:ids)')
            ->setParameter('ids', $hostIds, ArrayParameterType::INTEGER);
        $names = $this->fetchNames($qb->executeQuery()->iterateAssociative());

        // deleted hosts: the name they were logged with, else the one known by the realtime
        $names += $this->findLoggedNames(TargetTypeEnum::Host, array_values(array_diff($hostIds, array_keys($names))));

        $missingIds = array_values(array_diff($hostIds, array_keys($names)));
        if ($missingIds !== []) {
            $qb = $this->realtimeConnection->createQueryBuilder();
            $qb->select('h.host_id AS id', 'h.name')
                ->from('hosts', 'h')
                ->where('h.host_id IN (:ids)')
                ->setParameter('ids', $missingIds, ArrayParameterType::INTEGER);
            $names += $this->fetchNames($qb->executeQuery()->iterateAssociative());
        }

        return $names;
    }

    /**
     * @param list<int> $hostGroupIds
     *
     * @return array<int, string>
     */
    private function findHostGroupNames(array $hostGroupIds): array
    {
        if ($hostGroupIds === []) {
            return [];
        }

        $qb = $this->configurationConnection->createQueryBuilder();
        $qb->select('hg.hg_id AS id', 'hg.hg_name AS name')
            ->from('hostgroup', 'hg')
            ->where('hg.hg_id IN (:ids)')
            ->setParameter('ids', $hostGroupIds, ArrayParameterType::INTEGER);
        $names = $this->fetchNames($qb->executeQuery()->iterateAssociative());

        // deleted host groups: the name they were logged with
        return $names + $this->findLoggedNames(TargetTypeEnum::HostGroup, array_values(array_diff($hostGroupIds, array_keys($names))));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    private function findLoggedNames(TargetTypeEnum $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->realtimeConnection->createQueryBuilder();
        $qb->select('l.object_id AS id', 'l.object_name AS name')
            ->from('log_action', 'l')
            ->where('l.object_type IN (:types)')
            ->andWhere('l.object_id IN (:ids)')
            ->orderBy('l.action_log_date', 'DESC')
            ->addOrderBy('l.action_log_id', 'DESC')
            ->setParameter('types', LogActionTokenMapper::targetTypeToTokens($type), ArrayParameterType::STRING)
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        $names = [];
        foreach ($this->fetchNames($qb->executeQuery()->iterateAssociative(), keepFirst: true) as $id => $name) {
            $names[$id] = DbalActivityLogReadRepository::decodeName($name);
        }

        return $names;
    }

    /**
     * @param \Traversable<int, array<string, mixed>> $rows rows with "id" and "name" columns
     *
     * @return array<int, string>
     */
    private function fetchNames(\Traversable $rows, bool $keepFirst = false): array
    {
        $names = [];
        foreach ($rows as $row) {
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;
            $name = $row['name'] ?? null;
            if ($id === null || ! is_string($name) || $name === '') {
                continue;
            }
            if ($keepFirst && isset($names[$id])) {
                continue;
            }
            $names[$id] = $name;
        }

        return $names;
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $commaSeparatedIds): array
    {
        $ids = [];
        foreach (explode(',', $commaSeparatedIds) as $id) {
            $id = trim($id);
            if (ctype_digit($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
