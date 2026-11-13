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

namespace App\Upgrade\Application\Service;

use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationInterruptedException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\PlatformVersionNotRecordedException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;
use App\Upgrade\Domain\Service\UpgradeLoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs sets as upgrade steps without locking: its caller holds the MigrationSetGuard lock, outside any transaction.
 */
final readonly class MigrationApplier
{
    /** @var \WeakMap<\Throwable, true> */
    private \WeakMap $loggedFailures;

    public function __construct(
        private MigrationRunnerInterface $migrationRunner,
        private TargetVersionRecorder $targetVersionRecorder,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
        $this->loggedFailures = new \WeakMap();
    }

    /**
     * Whether a step already logged this failure with its trace.
     */
    public function hasLogged(\Throwable $exception): bool
    {
        return isset($this->loggedFailures[$exception]);
    }

    /**
     * Migrates the Centreon Web sets in execution order, then records the platform version they reach.
     *
     * @throws LegacyUpdatesPendingException
     * @throws PlatformVersionUnknownException
     * @throws MigrationSetRefusedException
     * @throws MigrationFailedException
     * @throws MigrationInterruptedException
     * @throws PlatformVersionNotRecordedException
     * @throws \LogicException
     */
    public function applyWebSets(): MigrationOutcome
    {
        $legacyVersion = $this->targetVersionRecorder->legacyVersion();

        $executed = [];
        $warnings = [];
        $migrated = [];
        foreach (MigrationSet::webSets() as $webSet) {
            try {
                $outcome = $this->runStep($webSet);
                $executed = [...$executed, ...$outcome->executed];
                $warnings = [...$warnings, ...$outcome->warnings];
            } catch (\Throwable $exception) {
                if ($migrated === []) {
                    throw $exception;
                }

                $interrupted = new MigrationInterruptedException($migrated, $webSet, $exception, $warnings);
                if ($this->hasLogged($exception)) {
                    $this->loggedFailures[$interrupted] = true;
                }

                throw $interrupted;
            }
            $migrated[] = $webSet->label();
        }

        try {
            $warning = $this->targetVersionRecorder->record($legacyVersion);
        } catch (\Throwable $exception) {
            throw new PlatformVersionNotRecordedException($exception, $warnings);
        }

        return new MigrationOutcome($executed, $warning === null ? $warnings : [...$warnings, $warning]);
    }

    /**
     * A single set, without recording the platform version.
     *
     * @throws LegacyUpdatesPendingException for a Centreon Web set
     * @throws PlatformVersionUnknownException for a Centreon Web set
     * @throws MigrationSetRefusedException
     * @throws MigrationFailedException
     * @throws \LogicException
     */
    public function applySet(MigrationSet $set): MigrationOutcome
    {
        if ($set->isModule()) {
            return $this->runStep($set);
        }

        $legacyVersion = $this->targetVersionRecorder->legacyVersion();
        $outcome = $this->runStep($set);
        if ($this->targetVersionRecorder->resolve($legacyVersion) === $legacyVersion) {
            return $outcome;
        }

        $warning = sprintf('Kept the platform version %s: the new version is only recorded by centreon:migrations:migrate without --set, once both Centreon Web sets ran.', $legacyVersion);
        $this->upgradeLogger->warning($this->platformVersion, $warning);

        return new MigrationOutcome($outcome->executed, [...$outcome->warnings, $warning]);
    }

    /**
     * Reverts the migrations MigrationRunnerInterface::planRollback() listed, refusing if they changed meanwhile.
     *
     * @param list<string> $expected the versions the rollback plan listed, as confirmed
     *
     * @throws MigrationSetRefusedException
     * @throws RollbackPlanChangedException
     * @throws MigrationFailedException
     * @throws RollbackTargetNotFoundException
     * @throws \LogicException
     *
     * @return MigrationOutcome the reverted versions, and the warnings of their migrations
     */
    public function revertSet(MigrationSet $set, string $versionOrAlias, array $expected): MigrationOutcome
    {
        return $this->step(
            $set->rollbackStep(),
            'reverted',
            fn (): MigrationOutcome => $this->migrationRunner->rollback($set, $versionOrAlias, $expected),
        );
    }

    private function runStep(MigrationSet $set): MigrationOutcome
    {
        return $this->step($set->migrationStep(), 'executed', fn (): MigrationOutcome => $this->migrationRunner->migrate($set));
    }

    /**
     * @param callable(): MigrationOutcome $run
     */
    private function step(string $step, string $outcome, callable $run): MigrationOutcome
    {
        $this->upgradeLogger->step($this->platformVersion, $step, "Starting step '{$step}'");
        $startedAt = hrtime(true);

        try {
            $result = $run();
        } catch (\Throwable $exception) {
            $this->upgradeLogger->stepFailure($this->platformVersion, $step, "Step '{$step}' failed: {$exception->getMessage()}", $exception);
            $this->loggedFailures[$exception] = true;

            throw $exception;
        }

        $durationMs = intdiv(hrtime(true) - $startedAt, 1_000_000);
        $this->upgradeLogger->stepCompleted($this->platformVersion, $step, $durationMs, sprintf("Step '%s' completed in %dms: %d migration(s) %s", $step, $durationMs, count($result->executed), $outcome));

        return $result;
    }
}
