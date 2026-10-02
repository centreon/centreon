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
use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;

/**
 * Intended as the sole seam to the legacy service-cloning machinery.
 *
 * Cloning a service exclusive to the source has no API Platform, Core or legacy-REST equivalent —
 * only the procedural `multipleServiceInDB` (www/include/.../service/DB-Func.php), which reads
 * `$pearDB` and `$centreon` as globals. They are installed for the call and restored in a `finally`:
 *  - `$pearDB`   → the shared legacy connection,
 *  - `$centreon` → the current legacy session, from which the cloned services take their author and
 *                  ACL. It only exists on a session-authenticated request, so a token-authenticated
 *                  call cannot clone services: it throws (DuplicateHostServicesEventHandler logs it,
 *                  at info for this expected case) rather than returning silently.
 */
final readonly class LegacyServiceCloner
{
    private const LEGACY_SERVICE_FUNCTION = 'multipleServiceInDB';
    private const LEGACY_SERVICE_FUNCTIONS_FILE = 'www/include/configuration/configObject/service/DB-Func.php';

    /** `descKey` argument of multipleServiceInDB: 0 = host duplication, keep the service description. */
    private const LEGACY_KEEP_DESCRIPTION = 0;

    /**
     * Clones the given services onto the new host through the legacy procedural function.
     *
     * @param list<int> $serviceIds services exclusive to the source, to be cloned onto the copy
     *
     * @throws ServiceDuplicationFailedException
     */
    public function cloneServices(array $serviceIds, HostId $newHostId): void
    {
        if ($serviceIds === []) {
            return;
        }

        $session = $this->requireSession();
        $this->requireLegacyServiceFunctions();

        // The legacy function wants the services as an id-keyed map and a parallel map of per-service
        // host counts (always 1 here: these are the services exclusive to the source).
        $services = array_combine($serviceIds, $serviceIds);
        $hostCounts = array_fill_keys($serviceIds, 1);

        $previousPearDB = $GLOBALS['pearDB'] ?? null;
        $previousCentreon = $GLOBALS['centreon'] ?? null;
        $GLOBALS['pearDB'] = \CentreonDBInstance::getDbCentreonInstance();
        $GLOBALS['centreon'] = $session;

        try {
            // Resolved behind a typed accessor so static analysis does not try to resolve the legacy
            // global function: it lives in www/include (required above), outside the analysed autoload.
            /** @var callable-string $duplicateServices */
            $duplicateServices = $this->legacyServiceFunctionName();
            $duplicateServices($services, $hostCounts, $newHostId->value, self::LEGACY_KEEP_DESCRIPTION);
        } finally {
            $GLOBALS['pearDB'] = $previousPearDB;
            $GLOBALS['centreon'] = $previousCentreon;
        }
    }

    private function legacyServiceFunctionName(): string
    {
        return self::LEGACY_SERVICE_FUNCTION;
    }

    private function requireSession(): \Centreon
    {
        $session = $_SESSION['centreon'] ?? null;
        if (! $session instanceof \Centreon) {
            throw ServiceDuplicationFailedException::missingLegacySession();
        }

        return $session;
    }

    private function requireLegacyServiceFunctions(): void
    {
        if (function_exists(self::LEGACY_SERVICE_FUNCTION)) {
            return;
        }
        if (! defined('_CENTREON_PATH_')) {
            throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
                'Cannot locate the legacy service functions: _CENTREON_PATH_ is not defined.'
            );
        }

        $file = $this->legacyBasePath() . self::LEGACY_SERVICE_FUNCTIONS_FILE;
        // Guard the require: a missing file would raise an uncatchable E_COMPILE_ERROR (not a Throwable),
        // which would escape the event handler's catch and 5xx the already-committed copy.
        if (! is_file($file)) {
            throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
                sprintf('Cannot locate the legacy service functions: "%s" does not exist.', $file)
            );
        }

        require_once $file;

        throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
            sprintf('"%s" did not define %s().', $file, self::LEGACY_SERVICE_FUNCTION)
        );
    }

    private function legacyBasePath(): string
    {
        return constant('_CENTREON_PATH_');
    }
}
