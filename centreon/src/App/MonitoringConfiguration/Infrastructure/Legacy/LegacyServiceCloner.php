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
use App\Security\Domain\Aggregate\UserId;

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
 *
 * The three legacy-runtime touchpoints — opening the connections, rebuilding the session and loading
 * then calling `multipleServiceInDB` — are isolated behind constructor seams that default to the real
 * legacy behaviour. The orchestration itself (the reuse-or-rebuild session decision and the
 * save/restore of the globals, even on failure) stays here and is unit-tested; the seams and the
 * procedural clone are exercised end to end on the CDE.
 */
final readonly class LegacyServiceCloner implements ServiceCloner
{
    private const LEGACY_SERVICE_FUNCTION = 'multipleServiceInDB';
    private const LEGACY_SERVICE_FUNCTIONS_FILE = 'www/include/configuration/configObject/service/DB-Func.php';

    /** `descKey` argument of multipleServiceInDB: 0 = host duplication, keep the service description. */
    private const LEGACY_KEEP_DESCRIPTION = 0;

    /**
     * @param (\Closure(): void)|null $connectionInstaller installs `$pearDB`/`$pearDBO`; defaults to the
     *                                                     legacy `CentreonDBInstance` singletons, overridden in tests to avoid a real connection
     * @param (\Closure(int): \Centreon)|null $sessionRebuilder rebuilds a legacy session from the acting
     *                                                          contact when the request carries none; defaults to a minimal `CentreonUser`-backed session
     * @param (\Closure(\Centreon, array<int, int>, array<int, int>, int): void)|null $cloneInvoker loads
     *                                                                                              the legacy functions file and calls `multipleServiceInDB`; defaults to the real legacy call
     */
    public function __construct(
        private ?\Closure $connectionInstaller = null,
        private ?\Closure $sessionRebuilder = null,
        private ?\Closure $cloneInvoker = null,
    ) {
    }

    /**
     * @param list<int> $serviceIds
     *
     * @throws ServiceDuplicationFailedException
     */
    public function cloneServices(array $serviceIds, HostId $newHostId, UserId $duplicatedBy): void
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
        $previousErrorHandler = $this->currentErrorHandler();

        try {
            // Installed inside the try so the finally always restores the globals, even if opening the
            // storage connection throws. Rebuilding the session below constructs a CentreonUser, whose
            // constructor reads $pearDB; the legacy action log writes through $pearDBO.
            ($this->connectionInstaller ?? $this->defaultConnectionInstaller())();

            // Published as the global the legacy function reads, and passed to the clone invoker so it
            // stays a local at the `require` there: DB-Func.php's top-level guard
            // `if (! isset($centreon)) exit();` would otherwise kill the whole request at include time.
            $centreon = $this->resolveLegacySession($duplicatedBy);
            $GLOBALS['centreon'] = $centreon;

            ($this->cloneInvoker ?? $this->defaultCloneInvoker())(
                $centreon,
                $services,
                $hostCounts,
                $newHostId->value,
            );
        } finally {
            // The legacy clone machinery (CentreonDB/CentreonUser, multipleServiceInDB) may register an
            // error handler without restoring it; unwind any it left on the stack so the request keeps
            // the handler it started with.
            $this->restoreErrorHandlerTo($previousErrorHandler);
            $GLOBALS['pearDB'] = $previousPearDB;
            $GLOBALS['pearDBO'] = $previousPearDBO;
            $GLOBALS['centreon'] = $previousCentreon;
        }
    }

    /**
     * Resolves the legacy functions file to require: the reuse-or-fail guard a broken deployment would
     * trip. The `_CENTREON_PATH_` lookup and the `function_exists` check are lifted to parameters so it
     * is testable without the legacy constant in place (it still touches the filesystem via is_file).
     *
     * @param bool $alreadyLoaded whether `multipleServiceInDB` is already declared
     * @param string|null $basePath the legacy install path (`_CENTREON_PATH_`), or null when undefined
     *
     * @throws ServiceDuplicationFailedException
     *
     * @return string|null the legacy functions file to require, or null when already loaded
     */
    public function legacyServiceFunctionsFile(bool $alreadyLoaded, ?string $basePath): ?string
    {
        if ($alreadyLoaded) {
            return null;
        }
        if ($basePath === null) {
            throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
                'Cannot locate the legacy service functions: _CENTREON_PATH_ is not defined.'
            );
        }

        $file = $basePath . self::LEGACY_SERVICE_FUNCTIONS_FILE;
        // Guard the require: a missing file would raise an uncatchable E_COMPILE_ERROR (not a Throwable),
        // which would escape the event handler's catch and 5xx the already-committed copy.
        if (! is_file($file)) {
            throw ServiceDuplicationFailedException::legacyFunctionsUnavailable(
                sprintf('Cannot locate the legacy service functions: "%s" does not exist.', $file)
            );
        }

        return $file;
    }

    /**
     * The error handler currently on top of the stack, read without disturbing it.
     */
    private function currentErrorHandler(): ?callable
    {
        $handler = set_error_handler(static fn (int $errno, string $errstr): bool => false);
        restore_error_handler();

        return $handler;
    }

    /**
     * Pops any error handlers left above the given one, back to the state captured before the call.
     * Bounded so a mismatch never spins forever.
     */
    private function restoreErrorHandlerTo(?callable $handler): void
    {
        for ($attempt = 0; $attempt < 50 && $this->currentErrorHandler() !== $handler; $attempt++) {
            restore_error_handler();
        }
    }

    /**
     * The interactive request's own legacy session when it has one, otherwise a minimal one rebuilt
     * from the acting contact. multipleServiceInDB only reaches `$centreon->CentreonLogAction` (the
     * action-log author) and `$centreon->user->access` (the ACL used to scope the cloned service), so
     * nothing else of the session needs to exist.
     */
    private function resolveLegacySession(UserId $duplicatedBy): \Centreon
    {
        $session = $_SESSION['centreon'] ?? null;
        if ($session instanceof \Centreon) {
            return $session;
        }

        // Unwrapped to the raw contact id at the legacy seam: the rebuilt CentreonUser is keyed on a
        // plain `contact_id`, so the session rebuilder stays in legacy terms.
        return ($this->sessionRebuilder ?? $this->defaultSessionRebuilder())($duplicatedBy->value);
    }

    /**
     * @return \Closure(): void
     */
    private function defaultConnectionInstaller(): \Closure
    {
        return static function (): void {
            $GLOBALS['pearDB'] = \CentreonDBInstance::getDbCentreonInstance();
            $GLOBALS['pearDBO'] = \CentreonDBInstance::getDbCentreonStorageInstance();
        };
    }

    /**
     * new CentreonUser() builds its own CentreonACL (which resolves contact_admin from the database
     * when not given) and CentreonUserLog, reading the $pearDB installed by the caller. The `\Centreon`
     * wrapper is created without its heavy constructor: the clone step reads only `user` and
     * `CentreonLogAction`.
     *
     * @return \Closure(int): \Centreon
     */
    private function defaultSessionRebuilder(): \Closure
    {
        return static function (int $duplicatedBy): \Centreon {
            $user = new \CentreonUser(['contact_id' => $duplicatedBy]);

            /** @var \Centreon $centreon */
            $centreon = (new \ReflectionClass(\Centreon::class))->newInstanceWithoutConstructor();
            $centreon->user = $user;
            $centreon->CentreonLogAction = new \CentreonLogAction($user);

            return $centreon;
        };
    }

    /**
     * @return \Closure(\Centreon, array<int, int>, array<int, int>, int): void
     */
    private function defaultCloneInvoker(): \Closure
    {
        return function (\Centreon $centreon, array $services, array $hostCounts, int $newHostId): void {
            $file = $this->legacyServiceFunctionsFile(
                function_exists(self::LEGACY_SERVICE_FUNCTION),
                defined('_CENTREON_PATH_') ? constant('_CENTREON_PATH_') : null,
            );
            if ($file !== null) {
                // $centreon is a local here, so DB-Func.php's `if (! isset($centreon)) exit();` passes.
                require_once $file;
            }

            // Resolved behind a typed accessor so static analysis does not try to resolve the legacy
            // global function: it lives in www/include (required above), outside the analysed autoload.
            /** @var callable-string $duplicateServices */
            $duplicateServices = $this->legacyServiceFunctionName();
            $duplicateServices($services, $hostCounts, $newHostId, self::LEGACY_KEEP_DESCRIPTION);
        };
    }

    private function legacyServiceFunctionName(): string
    {
        return self::LEGACY_SERVICE_FUNCTION;
    }
}
