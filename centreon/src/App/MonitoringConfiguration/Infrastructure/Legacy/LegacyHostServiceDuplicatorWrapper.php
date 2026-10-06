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

namespace App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Service\HostServiceDuplicator;
use App\MonitoringConfiguration\Domain\Service\ServiceCloner;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Duplicates a host's services onto the freshly duplicated host.
 *
 * The relation bookkeeping runs on the configuration connection: a service shared with other hosts
 * keeps its identity and the copy only gets a new relation to it. Re-linking needs no legacy session,
 * so it always runs. A service exclusive to the source has to be cloned, which has no new-architecture
 * equivalent yet and is delegated to the legacy procedural step (see {@see LegacyServiceCloner}); that
 * step needs a legacy session, which {@see LegacyServiceCloner} rebuilds from $duplicatedBy when the
 * request carries none (e.g. token-authenticated), so the exclusive services are cloned either way.
 *
 * The event carrying this is delivered after the command commits, so the copy is visible to the
 * connection (see {@see \App\MonitoringConfiguration\Domain\Event\HostServicesDuplicationRequested}).
 */
final readonly class LegacyHostServiceDuplicatorWrapper implements HostServiceDuplicator
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private ServiceCloner $serviceCloner,
    ) {
    }

    public function duplicate(HostId $sourceHostId, HostId $newHostId, int $duplicatedBy): void
    {
        /** @var list<array{service_id: int|string, host_count: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT hsr.service_service_id AS service_id,
                    (SELECT COUNT(*) FROM host_service_relation shared
                     WHERE shared.service_service_id = hsr.service_service_id) AS host_count
                FROM host_service_relation hsr
                WHERE hsr.host_host_id = :sourceHostId
                GROUP BY hsr.service_service_id
                SQL,
            ['sourceHostId' => $sourceHostId->value],
        );

        $servicesToClone = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row['service_id'];
            // A service linked to more than one host keeps its identity: the copy only gets a new
            // relation to it. A service exclusive to the source is cloned. Mirrors multipleHostInDB.
            if ((int) $row['host_count'] > 1) {
                $this->connection->executeStatement(
                    'INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES (:newHostId, :serviceId)',
                    ['newHostId' => $newHostId->value, 'serviceId' => $serviceId],
                );

                continue;
            }

            $servicesToClone[] = $serviceId;
        }

        $this->serviceCloner->cloneServices($servicesToClone, $newHostId, $duplicatedBy);
    }
}
