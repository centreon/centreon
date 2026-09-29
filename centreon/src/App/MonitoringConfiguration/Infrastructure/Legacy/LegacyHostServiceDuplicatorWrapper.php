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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;
use App\MonitoringConfiguration\Domain\Service\HostServiceDuplicator;

/**
 * The only class allowed to know that host-service duplication is still a legacy use case.
 *
 * There is no API Platform, Core or legacy-REST endpoint for it — only the procedural
 * `multipleServiceInDB` (www/include/.../service/DB-Func.php), which reads `$pearDB` and `$centreon`
 * as globals. They are installed for the call and restored in a `finally`:
 *  - `$pearDB`   → the shared legacy connection (`CentreonDBInstance`),
 *  - `$centreon` → the current legacy session, from which the cloned services take their author and
 *                  ACL. It only exists on a session-authenticated request, so a token-authenticated
 *                  call cannot duplicate services and fails loudly (the caller swallows and logs it).
 *
 * The event carrying this is delivered after the command commits, so the copy is visible to the
 * legacy connection (see {@see HostServicesDuplicationRequested}).
 */
final class LegacyHostServiceDuplicatorWrapper implements HostServiceDuplicator
{
    private const LEGACY_SERVICE_FUNCTIONS = 'www/include/configuration/configObject/service/DB-Func.php';

    public function duplicate(HostId $sourceHostId, HostId $newHostId): void
    {
        $legacySession = $_SESSION['centreon'] ?? null;
        if (! $legacySession instanceof \Centreon) {
            throw new ServiceDuplicationFailedException(
                'Host services can only be duplicated within a legacy session; none is available '
                . '(for instance on a token-authenticated request).'
            );
        }

        $this->requireLegacyServiceFunctions();

        $connection = \CentreonDBInstance::getDbCentreonInstance();

        $previousPearDB = $GLOBALS['pearDB'] ?? null;
        $previousCentreon = $GLOBALS['centreon'] ?? null;
        $GLOBALS['pearDB'] = $connection;
        $GLOBALS['centreon'] = $legacySession;

        try {
            $this->cloneOrRelinkServices($connection, $sourceHostId, $newHostId);
        } finally {
            $GLOBALS['pearDB'] = $previousPearDB;
            $GLOBALS['centreon'] = $previousCentreon;
        }
    }

    private function cloneOrRelinkServices(\CentreonDB $connection, HostId $sourceHostId, HostId $newHostId): void
    {
        /** @var list<array{service_id: int|string, host_count: int|string}> $rows */
        $rows = $connection->fetchAllAssociative(
            <<<'SQL'
                SELECT hsr.service_service_id AS service_id,
                    (SELECT COUNT(*) FROM host_service_relation shared
                     WHERE shared.service_service_id = hsr.service_service_id) AS host_count
                FROM host_service_relation hsr
                WHERE hsr.host_host_id = :sourceHostId
                GROUP BY hsr.service_service_id
                SQL,
            QueryParameters::create([QueryParameter::int('sourceHostId', $sourceHostId->value)]),
        );

        $servicesToClone = [];
        $serviceCounts = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row['service_id'];
            // A service linked to more than one host keeps its identity: the copy only gets a new
            // relation to it. A service exclusive to the source is cloned. Mirrors multipleHostInDB.
            if ((int) $row['host_count'] > 1) {
                $connection->executeStatement(
                    'INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES (:newHostId, :serviceId)',
                    QueryParameters::create([
                        QueryParameter::int('newHostId', $newHostId->value),
                        QueryParameter::int('serviceId', $serviceId),
                    ]),
                );

                continue;
            }

            $servicesToClone[$serviceId] = $serviceId;
            $serviceCounts[$serviceId] = 1;
        }

        if ($servicesToClone === []) {
            return;
        }

        // The name is resolved behind a typed accessor so static analysis does not try to resolve the
        // legacy global function: it lives in www/include (required above), outside the analysed autoload.
        /** @var callable-string $duplicateServices */
        $duplicateServices = $this->legacyDuplicateFunctionName();
        $duplicateServices($servicesToClone, $serviceCounts, $newHostId->value, 0);
    }

    private function legacyDuplicateFunctionName(): string
    {
        return 'multipleServiceInDB';
    }

    private function requireLegacyServiceFunctions(): void
    {
        if (function_exists('multipleServiceInDB')) {
            return;
        }
        if (! defined('_CENTREON_PATH_')) {
            throw new ServiceDuplicationFailedException(
                'Cannot locate the legacy service functions: _CENTREON_PATH_ is not defined.'
            );
        }

        require_once $this->legacyBasePath() . self::LEGACY_SERVICE_FUNCTIONS;
    }

    private function legacyBasePath(): string
    {
        return (string) constant('_CENTREON_PATH_');
    }
}
