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
 * only the procedural `multipleServiceInDB` (www/include/.../service/DB-Func.php), which reads three
 * globals, installed for the call and restored in a `finally`:
 *  - `$pearDB`   → the configuration connection (SELECTs, CentreonUserLog),
 *  - `$pearDBO`  → the real-time/storage connection the action log (`insertLog`) writes through,
 *  - `$centreon` → the legacy session the cloned services take their author and ACL from. An
 *                  interactive request already carries one; a session-less (e.g. token-authenticated)
 *                  request has none, so a minimal one is rebuilt from the acting contact so the clone
 *                  runs either way.
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
     * @param int $duplicatedBy the acting contact, used to rebuild the legacy session when the request
     *                          carries none
     *
     * @throws ServiceDuplicationFailedException
     */
    public function cloneServices(array $serviceIds, HostId $newHostId, int $duplicatedBy): void
    {
        if ($serviceIds === []) {
            return;
        }

        // The legacy function wants the services as an id-keyed map and a parallel map of per-service
        // host counts (always 1 here: these are the services exclusive to the source).
        $services = array_combine($serviceIds, $serviceIds);
        $hostCounts = array_fill_keys($serviceIds, 1);

        $previousPearDB = $GLOBALS['pearDB'] ?? null;
        $previousPearDBO = $GLOBALS['pearDBO'] ?? null;
        $previousCentreon = $GLOBALS['centreon'] ?? null;
        // Installed first: rebuilding the session below constructs a CentreonUser, whose constructor
        // reads $pearDB; and the legacy action log writes through $pearDBO.
        $GLOBALS['pearDB'] = \CentreonDBInstance::getDbCentreonInstance();
        $GLOBALS['pearDBO'] = \CentreonDBInstance::getDbCentreonStorageInstance();

        try {
            // Held in a local $centreon kept in scope at the require below: DB-Func.php's top-level
            // guard `if (! isset($centreon)) exit();` would otherwise kill the whole request at include
            // time. Also published as the global the legacy function reads.
            $centreon = $this->resolveLegacySession($duplicatedBy);
            $GLOBALS['centreon'] = $centreon;

            $file = $this->resolveLegacyServiceFunctionsFile();
            if ($file !== null) {
                require_once $file;
            }

            // Resolved behind a typed accessor so static analysis does not try to resolve the legacy
            // global function: it lives in www/include (required above), outside the analysed autoload.
            /** @var callable-string $duplicateServices */
            $duplicateServices = $this->legacyServiceFunctionName();
            $duplicateServices($services, $hostCounts, $newHostId->value, self::LEGACY_KEEP_DESCRIPTION);
        } finally {
            $GLOBALS['pearDB'] = $previousPearDB;
            $GLOBALS['pearDBO'] = $previousPearDBO;
            $GLOBALS['centreon'] = $previousCentreon;
        }
    }

    private function legacyServiceFunctionName(): string
    {
        return self::LEGACY_SERVICE_FUNCTION;
    }

    /**
     * The interactive request's own legacy session when it has one, otherwise a minimal one rebuilt
     * from the acting contact. multipleServiceInDB only reaches `$centreon->CentreonLogAction` (the
     * action-log author) and `$centreon->user->access` (the ACL used to scope the cloned service), so
     * nothing else of the session needs to exist.
     */
    private function resolveLegacySession(int $duplicatedBy): \Centreon
    {
        $session = $_SESSION['centreon'] ?? null;
        if ($session instanceof \Centreon) {
            return $session;
        }

        // new CentreonUser() builds its own CentreonACL (which resolves contact_admin from the database
        // when not given) and CentreonUserLog, reading the $pearDB installed by the caller. The
        // `\Centreon` wrapper is created without its heavy constructor: the clone step reads only `user`
        // and `CentreonLogAction`.
        $user = new \CentreonUser(['contact_id' => $duplicatedBy]);

        /** @var \Centreon $centreon */
        $centreon = (new \ReflectionClass(\Centreon::class))->newInstanceWithoutConstructor();
        $centreon->user = $user;
        $centreon->CentreonLogAction = new \CentreonLogAction($user);

        return $centreon;
    }

    /**
     * @return string|null the legacy functions file to require, or null when already loaded
     */
    private function resolveLegacyServiceFunctionsFile(): ?string
    {
        if (function_exists(self::LEGACY_SERVICE_FUNCTION)) {
            return null;
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

        return $file;
    }

    private function legacyBasePath(): string
    {
        return constant('_CENTREON_PATH_');
    }
}
