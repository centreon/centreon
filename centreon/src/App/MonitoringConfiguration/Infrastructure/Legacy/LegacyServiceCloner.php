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

use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;

/**
 * The only class allowed to name the legacy service-duplication machinery.
 *
 * Cloning an exclusive service has no API Platform, Core or legacy-REST equivalent — only the
 * procedural `multipleServiceInDB` (www/include/.../service/DB-Func.php), which reads `$pearDB` and
 * `$centreon` as globals. They are installed for the call and restored in a `finally`:
 *  - `$pearDB`   → the shared legacy connection,
 *  - `$centreon` → the current legacy session, from which the cloned services take their author and
 *                  ACL. It only exists on a session-authenticated request, so a token-authenticated
 *                  call cannot clone services and fails loudly (the caller swallows and logs it).
 */
final class LegacyServiceCloner
{
    private const LEGACY_SERVICE_FUNCTIONS = 'www/include/configuration/configObject/service/DB-Func.php';

    /**
     * Fails before any work when the copy cannot carry services, so the caller skips them entirely
     * rather than leaving the host with only some (e.g. a token-authenticated request has no session).
     *
     * @throws ServiceDuplicationFailedException
     */
    public function assertSessionAvailable(): void
    {
        $session = $_SESSION['centreon'] ?? null;
        if (! $session instanceof \Centreon) {
            throw ServiceDuplicationFailedException::missingLegacySession();
        }
    }

    /**
     * Clones the given services onto the new host through the legacy procedural function.
     *
     * @param array<int, int> $servicesToClone service id => service id
     * @param array<int, int> $serviceCounts service id => host count (1 for an exclusive service)
     *
     * @throws ServiceDuplicationFailedException
     */
    public function cloneServices(array $servicesToClone, array $serviceCounts, int $newHostId): void
    {
        $session = $_SESSION['centreon'] ?? null;
        if (! $session instanceof \Centreon) {
            throw ServiceDuplicationFailedException::missingLegacySession();
        }

        $this->requireLegacyServiceFunctions();

        $previousPearDB = $GLOBALS['pearDB'] ?? null;
        $previousCentreon = $GLOBALS['centreon'] ?? null;
        $GLOBALS['pearDB'] = \CentreonDBInstance::getDbCentreonInstance();
        $GLOBALS['centreon'] = $session;

        try {
            // Resolved behind a typed accessor so static analysis does not try to resolve the legacy
            // global function: it lives in www/include (required above), outside the analysed autoload.
            /** @var callable-string $duplicateServices */
            $duplicateServices = $this->legacyDuplicateFunctionName();
            $duplicateServices($servicesToClone, $serviceCounts, $newHostId, 0);
        } finally {
            $GLOBALS['pearDB'] = $previousPearDB;
            $GLOBALS['centreon'] = $previousCentreon;
        }
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
            throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
                'Cannot locate the legacy service functions: _CENTREON_PATH_ is not defined.'
            );
        }

        require_once $this->legacyBasePath() . self::LEGACY_SERVICE_FUNCTIONS;
    }

    private function legacyBasePath(): string
    {
        return constant('_CENTREON_PATH_');
    }
}
