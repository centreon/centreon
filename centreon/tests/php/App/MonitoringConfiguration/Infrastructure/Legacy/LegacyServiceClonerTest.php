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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyServiceCloner;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;

/**
 * The orchestration is unit-tested here: the reuse-or-rebuild session decision and the save/restore of
 * the legacy globals, even on failure. The three legacy-runtime seams (opening the connections,
 * rebuilding the session, loading and calling multipleServiceInDB) are driven through test doubles; the
 * real procedural clone is covered end to end on the CDE.
 */
final class LegacyServiceClonerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['centreon'], $GLOBALS['pearDB'], $GLOBALS['pearDBO'], $GLOBALS['centreon']);
    }

    public function testItDoesNothingForAnEmptyServiceList(): void
    {
        $pearDbBefore = $GLOBALS['pearDB'] ?? null;
        $centreonBefore = $GLOBALS['centreon'] ?? null;

        // An empty list returns before any connection is opened, session resolved or legacy file loaded:
        // the seams must never be reached.
        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->failingConnectionInstaller(),
            sessionRebuilder: $this->failingSessionRebuilder(),
            cloneInvoker: $this->failingCloneInvoker(),
        );

        $cloner->cloneServices([], new HostId(9), new UserId(42));

        self::assertSame($pearDbBefore, $GLOBALS['pearDB'] ?? null);
        self::assertSame($centreonBefore, $GLOBALS['centreon'] ?? null);
    }

    public function testItReusesTheSessionCarriedByTheRequest(): void
    {
        $session = $this->stubCentreon();
        $_SESSION['centreon'] = $session;

        $usedSession = null;
        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->noopConnectionInstaller(),
            // The request already carries a session, so the rebuilder must never run.
            sessionRebuilder: $this->failingSessionRebuilder(),
            cloneInvoker: function (\Centreon $centreon) use (&$usedSession): void {
                $usedSession = $centreon;
            },
        );

        $cloner->cloneServices([7], new HostId(9), new UserId(42));

        self::assertSame($session, $usedSession, 'the session carried by the request is reused as-is');
    }

    public function testItRebuildsTheSessionFromTheActingContactWhenTheRequestCarriesNone(): void
    {
        unset($_SESSION['centreon']);

        $rebuilt = $this->stubCentreon();
        $rebuiltFor = null;
        $sessionDuringClone = null;
        $globalDuringClone = null;

        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->noopConnectionInstaller(),
            sessionRebuilder: function (int $duplicatedBy) use ($rebuilt, &$rebuiltFor): \Centreon {
                $rebuiltFor = $duplicatedBy;

                return $rebuilt;
            },
            cloneInvoker: function (\Centreon $centreon) use (&$sessionDuringClone, &$globalDuringClone): void {
                $sessionDuringClone = $centreon;
                $globalDuringClone = $GLOBALS['centreon'] ?? null;
            },
        );

        $cloner->cloneServices([7], new HostId(9), new UserId(42));

        self::assertSame(42, $rebuiltFor, 'the session is rebuilt from the acting contact');
        self::assertSame($rebuilt, $sessionDuringClone, 'the rebuilt session is handed to the clone step');
        self::assertSame($rebuilt, $globalDuringClone, 'the rebuilt session is published as $GLOBALS[\'centreon\'] during the clone');
    }

    public function testItPassesTheServicesToTheLegacyFunctionAsMaps(): void
    {
        $services = null;
        $hostCounts = null;
        $newHostId = null;

        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->noopConnectionInstaller(),
            sessionRebuilder: $this->stubSessionRebuilder(),
            cloneInvoker: function (\Centreon $centreon, array $serviceMap, array $countMap, int $hostId) use (&$services, &$hostCounts, &$newHostId): void {
                $services = $serviceMap;
                $hostCounts = $countMap;
                $newHostId = $hostId;
            },
        );

        $cloner->cloneServices([7, 9], new HostId(42), new UserId(1));

        // The legacy function wants the services as an id-keyed map and a parallel map of per-service
        // host counts, always 1 for services exclusive to the source.
        self::assertSame([7 => 7, 9 => 9], $services);
        self::assertSame([7 => 1, 9 => 1], $hostCounts);
        self::assertSame(42, $newHostId);
    }

    public function testItRestoresTheGlobalsAfterTheClone(): void
    {
        $previousPearDB = new \stdClass();
        $previousPearDBO = new \stdClass();
        $previousCentreon = $this->stubCentreon();
        $GLOBALS['pearDB'] = $previousPearDB;
        $GLOBALS['pearDBO'] = $previousPearDBO;
        $GLOBALS['centreon'] = $previousCentreon;

        $cloner = new LegacyServiceCloner(
            // The installer swaps the connections in, as the real one does; they must be reverted after.
            connectionInstaller: static function (): void {
                $GLOBALS['pearDB'] = new \stdClass();
                $GLOBALS['pearDBO'] = new \stdClass();
            },
            sessionRebuilder: $this->stubSessionRebuilder(),
            cloneInvoker: $this->noopCloneInvoker(),
        );

        $cloner->cloneServices([7], new HostId(9), new UserId(42));

        self::assertSame($previousPearDB, $GLOBALS['pearDB']);
        self::assertSame($previousPearDBO, $GLOBALS['pearDBO']);
        self::assertSame($previousCentreon, $GLOBALS['centreon']);
    }

    public function testItRestoresTheGlobalsEvenWhenTheCloneFails(): void
    {
        $previousPearDB = new \stdClass();
        $previousCentreon = $this->stubCentreon();
        $GLOBALS['pearDB'] = $previousPearDB;
        $GLOBALS['centreon'] = $previousCentreon;

        $cloner = new LegacyServiceCloner(
            connectionInstaller: static function (): void {
                $GLOBALS['pearDB'] = new \stdClass();
            },
            sessionRebuilder: $this->stubSessionRebuilder(),
            cloneInvoker: static function (): never {
                throw new \RuntimeException('legacy clone blew up');
            },
        );

        try {
            $cloner->cloneServices([7], new HostId(9), new UserId(42));
            self::fail('the clone failure must propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('legacy clone blew up', $exception->getMessage());
        }

        self::assertSame($previousPearDB, $GLOBALS['pearDB'], 'the globals are restored even when the clone throws');
        self::assertSame($previousCentreon, $GLOBALS['centreon']);
    }

    public function testItUnwindsEveryErrorHandlerLeftByTheClone(): void
    {
        $baseline = $this->currentErrorHandler();

        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->noopConnectionInstaller(),
            sessionRebuilder: $this->stubSessionRebuilder(),
            // The legacy clone machinery registers error handlers and does not clean them up.
            cloneInvoker: static function (): void {
                set_error_handler(static fn (): bool => false);
                set_error_handler(static fn (): bool => false);
                set_error_handler(static fn (): bool => false);
            },
        );

        $cloner->cloneServices([7], new HostId(9), new UserId(42));

        self::assertSame(
            $baseline,
            $this->currentErrorHandler(),
            'every error handler the clone left is unwound back to the one in place before the call',
        );
    }

    public function testItRestoresTheErrorHandlerEvenWhenTheCloneFails(): void
    {
        $baseline = $this->currentErrorHandler();

        $cloner = new LegacyServiceCloner(
            connectionInstaller: $this->noopConnectionInstaller(),
            sessionRebuilder: $this->stubSessionRebuilder(),
            cloneInvoker: static function (): never {
                set_error_handler(static fn (): bool => false);

                throw new \RuntimeException('legacy clone blew up');
            },
        );

        try {
            $cloner->cloneServices([7], new HostId(9), new UserId(42));
            self::fail('the clone failure must propagate');
        } catch (\RuntimeException) {
        }

        self::assertSame(
            $baseline,
            $this->currentErrorHandler(),
            'the error handler is restored even when the clone throws',
        );
    }

    public function testTheLegacyFileIsSkippedWhenTheFunctionIsAlreadyLoaded(): void
    {
        self::assertNull(
            new LegacyServiceCloner()->legacyServiceFunctionsFile(alreadyLoaded: true, basePath: null),
            'no file is required when multipleServiceInDB is already declared',
        );
    }

    public function testItFailsWhenTheLegacyPathIsUndefined(): void
    {
        $this->expectException(ServiceDuplicationFailedException::class);
        $this->expectExceptionMessage('_CENTREON_PATH_ is not defined');

        new LegacyServiceCloner()->legacyServiceFunctionsFile(alreadyLoaded: false, basePath: null);
    }

    public function testItFailsWhenTheLegacyFileIsMissing(): void
    {
        // A base path that exists but does not contain the legacy functions file.
        $basePath = sys_get_temp_dir() . '/';

        $this->expectException(ServiceDuplicationFailedException::class);
        $this->expectExceptionMessage('does not exist');

        new LegacyServiceCloner()->legacyServiceFunctionsFile(alreadyLoaded: false, basePath: $basePath);
    }

    public function testItResolvesTheLegacyFileUnderTheGivenPath(): void
    {
        $basePath = sys_get_temp_dir() . '/legacy-cloner-' . uniqid('', true) . '/';
        $relativeFile = 'www/include/configuration/configObject/service/DB-Func.php';
        $file = $basePath . $relativeFile;
        mkdir(dirname($file), 0o777, true);
        touch($file);

        try {
            self::assertSame(
                $file,
                new LegacyServiceCloner()->legacyServiceFunctionsFile(alreadyLoaded: false, basePath: $basePath),
            );
        } finally {
            unlink($file);
        }
    }

    private function stubCentreon(): \Centreon
    {
        return new \ReflectionClass(\Centreon::class)->newInstanceWithoutConstructor();
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
     * @return \Closure(): void
     */
    private function noopConnectionInstaller(): \Closure
    {
        return static function (): void {};
    }

    /**
     * @return \Closure(): void
     */
    private function failingConnectionInstaller(): \Closure
    {
        return static function (): never {
            throw new \LogicException('the connection installer must not be reached');
        };
    }

    /**
     * @return \Closure(int): \Centreon
     */
    private function stubSessionRebuilder(): \Closure
    {
        $session = $this->stubCentreon();

        return static fn (int $duplicatedBy): \Centreon => $session;
    }

    /**
     * @return \Closure(int): \Centreon
     */
    private function failingSessionRebuilder(): \Closure
    {
        return static function (int $duplicatedBy): \Centreon {
            throw new \LogicException('the session rebuilder must not be reached');
        };
    }

    /**
     * @return \Closure(\Centreon, array<int, int>, array<int, int>, int): void
     */
    private function noopCloneInvoker(): \Closure
    {
        return static function (\Centreon $centreon, array $services, array $hostCounts, int $newHostId): void {};
    }

    /**
     * @return \Closure(\Centreon, array<int, int>, array<int, int>, int): void
     */
    private function failingCloneInvoker(): \Closure
    {
        return static function (\Centreon $centreon, array $services, array $hostCounts, int $newHostId): never {
            throw new \LogicException('the clone invoker must not be reached');
        };
    }
}
