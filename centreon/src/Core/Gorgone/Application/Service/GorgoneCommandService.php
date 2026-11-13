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

namespace Core\Gorgone\Application\Service;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\ReadGorgoneConfigurationRepositoryInterface;
use Core\Gorgone\Application\Repository\ReadGorgoneJobRepositoryInterface;
use Core\Gorgone\Application\Repository\WriteGorgoneJobRepositoryInterface;
use Core\Gorgone\Domain\Model\JobOutcomes;
use Core\Gorgone\Domain\Model\TarArchive;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;

/**
 * Turns the commands Centreon used to write in the centcore files ("CMD:poller[:param]")
 * into calls to the Gorgone REST API, and tells how the submitted jobs ended.
 *
 * @phpstan-type _Command array{name: string, nodeId: int, param: string}
 * @phpstan-type _Report array{messages: list<string>, failures: list<string>}
 */
final class GorgoneCommandService
{
    use LoggerTrait;
    public const FEATURE_FLAG = 'gorgone_api_commands';
    public const TARGET_ENGINE = 'engine';
    public const TARGET_BROKER = 'broker';
    public const TARGET_VMWARE = 'vmware';
    public const TARGET_CENTREONTRAPD = 'centreontrapd';

    /** Longest time spent waiting for the outcome of jobs, whatever the Gorgone command timeout. */
    public const MAX_WAIT_SECONDS = 30;
    private const COMMAND_EXTERNAL = 'EXTERNALCMD';
    private const COMMAND_SEND_CONFIGURATION = 'SENDCFGFILE';
    private const COMMAND_SYNC_TRAP = 'SYNCTRAP';

    /** @var array<string, array{string, string}> service and operation by legacy command */
    private const SERVICE_CONTROLS = [
        'RESTART' => ['engine', 'restart'],
        'ENGINERESTART' => ['engine', 'restart'],
        'RELOAD' => ['engine', 'reload'],
        'ENGINERELOAD' => ['engine', 'reload'],
        'START' => ['engine', 'start'],
        'STOP' => ['engine', 'stop'],
        'RELOADBROKER' => ['broker', 'reload'],
        'RELOADCENTREONTRAPD' => ['centreontrapd', 'reload'],
        'RESTARTCENTREONTRAPD' => ['centreontrapd', 'restart'],
        'VMWARERESTART' => ['vmware', 'restart'],
    ];

    /**
     * @var array<string, list<string>> configuration targets a service depends on: it is not started,
     *                                  restarted nor reloaded on top of a failed push of one of them.
     *                                  Not vmware for the engine: a poller without vmware refuses that push.
     */
    private const REQUIRED_CONFIGURATIONS = [
        'engine' => [self::TARGET_ENGINE, self::TARGET_BROKER],
        'broker' => [self::TARGET_BROKER],
        'vmware' => [self::TARGET_VMWARE],
        'centreontrapd' => [self::TARGET_CENTREONTRAPD],
    ];
    private const OPERATION_STOP = 'stop';
    private const OPTION_ILLEGAL_CHARACTERS = 'gorgone_illegal_characters';
    private const OPTION_COMMAND_TIMEOUT = 'gorgone_cmd_timeout';
    private const DEFAULT_COMMAND_TIMEOUT = 2;

    // The Gorgone API limits a job submission to 1 MiB.
    private const EXTERNAL_COMMANDS_MAX_COUNT = 200;
    private const EXTERNAL_COMMANDS_MAX_BYTES = 262144;

    /** @var array<string, int|null> */
    private array $nodeIds = [];

    /** @var list<string>|null */
    private ?array $illegalCharacters = null;

    /** @var array<string, true> jobs of external commands: sent and forgotten, never waited for */
    private array $unwaitedJobIds = [];

    /**
     * @param FeatureFlags $featureFlags
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param ReadGorgoneConfigurationRepositoryInterface $readConfigurationRepository
     * @param WriteGorgoneJobRepositoryInterface $writeJobRepository
     * @param ReadGorgoneJobRepositoryInterface $readJobRepository
     * @param OptionServiceInterface $optionService
     * @param (\Closure(): float)|null $clock current time in seconds, the system monotonic clock by default
     */
    public function __construct(
        private readonly FeatureFlags $featureFlags,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadGorgoneConfigurationRepositoryInterface $readConfigurationRepository,
        private readonly WriteGorgoneJobRepositoryInterface $writeJobRepository,
        private readonly ReadGorgoneJobRepositoryInterface $readJobRepository,
        private readonly OptionServiceInterface $optionService,
        private readonly ?\Closure $clock = null,
    ) {
    }

    /**
     * Indicates whether the commands must go through the Gorgone API instead of the centcore files.
     */
    public function isEnabled(): bool
    {
        return $this->featureFlags->isEnabled(self::FEATURE_FLAG);
    }

    /**
     * Send commands written in the centcore vocabulary ("CMD:poller[:param]", the poller being
     * identified by its id or its uid). Order is preserved; consecutive external commands of a
     * poller are sent in one call. Nothing is sent when one line is invalid; external commands
     * for an unknown poller are reported once the other commands are sent.
     *
     * @param string|list<string> $lines one command per line
     *
     * @throws GorgoneCommandException
     *
     * @return list<string> ids of the submitted Gorgone jobs
     */
    public function sendLegacyCommands(string|array $lines): array
    {
        if (! $this->isEnabled()) {
            throw GorgoneCommandException::featureDisabled(self::FEATURE_FLAG);
        }

        // As in a centcore file, a line break always ends a command.
        $lines = explode("\n", is_array($lines) ? implode("\n", $lines) : $lines);

        try {
            [$commands, $unknownPollers] = $this->parse($lines);
            $jobIds = $this->send($commands);
            if ($unknownPollers !== []) {
                throw GorgoneCommandException::unknownPoller(implode(', ', $unknownPollers));
            }

            return $jobIds;
        } catch (\Throwable $ex) {
            $this->error(
                'Unable to send commands through the Gorgone API: ' . $ex->getMessage(),
                ['commands' => array_map(self::describe(...), $lines), 'exception' => $ex]
            );

            throw $ex instanceof GorgoneCommandException
                ? $ex
                : new GorgoneCommandException($ex->getMessage(), previous: $ex);
        }
    }

    /**
     * Wait for jobs to finish, for at most the Gorgone command timeout capped to MAX_WAIT_SECONDS
     * for the whole set. A job still running when the time is up is reported as pending: it is
     * not an error. The jobs of external commands are never waited for nor reported.
     *
     * @param list<string> $jobIds
     *
     * @throws GorgoneCommandException
     *
     * @return JobOutcomes
     */
    public function waitForJobs(array $jobIds): JobOutcomes
    {
        if (! $this->isEnabled()) {
            throw GorgoneCommandException::featureDisabled(self::FEATURE_FLAG);
        }

        $jobIds = array_values(array_unique(array_filter(
            $jobIds,
            fn (string $jobId): bool => ! isset($this->unwaitedJobIds[$jobId])
        )));

        try {
            $outcomes = [];
            if ($jobIds !== []) {
                $deadline = $this->now() + min($this->getCommandTimeout(), self::MAX_WAIT_SECONDS);
                foreach ($jobIds as $jobId) {
                    // Once the time is up, the state of the remaining jobs is read without waiting.
                    $outcome = $this->readJobRepository->findOutcome(
                        $jobId,
                        max(0, (int) floor($deadline - $this->now()))
                    );
                    if ($outcome->isFailed()) {
                        $this->error(
                            'Gorgone job failed: ' . $outcome->getFailureMessage(),
                            ['job' => $jobId, 'state' => $outcome->getState()]
                        );
                    }
                    $outcomes[] = $outcome;
                }
            }

            return new JobOutcomes($outcomes);
        } catch (\Throwable $ex) {
            $this->error(
                'Unable to get the outcome of the Gorgone jobs: ' . $ex->getMessage(),
                ['jobs' => $jobIds, 'exception' => $ex]
            );

            throw $ex instanceof GorgoneCommandException
                ? $ex
                : new GorgoneCommandException($ex->getMessage(), previous: $ex);
        }
    }

    /**
     * Wait for the jobs of several operations (see waitForJobs(): the time is shared by all of
     * them) and describe the outcome of each one for the user, in plain text.
     *
     * @param array<string, list<string>> $jobIdsByLabel job ids by name of the operation (e.g. the poller name)
     *
     * @throws GorgoneCommandException
     *
     * @return _Report one message per operation, and those of the failed operations
     */
    public function reportOutcomes(array $jobIdsByLabel): array
    {
        $outcomes = $this->waitForJobs(array_merge(...array_values($jobIdsByLabel)));

        $report = ['messages' => [], 'failures' => []];
        foreach ($jobIdsByLabel as $label => $jobIds) {
            $outcome = $outcomes->of($jobIds);
            if ($outcome->isFailed()) {
                $message = sprintf(_('%s: failed (%s).'), $label, $outcome->getFailureMessage());
                $report['failures'][] = $message;
            } elseif ($outcome->isPending()) {
                $message = sprintf(_('%s: still in progress.'), $label);
            } else {
                $message = sprintf(_('%s: done.'), $label);
            }
            $report['messages'][] = $message;
        }

        return $report;
    }

    /**
     * @param list<string> $lines
     *
     * @throws \Throwable
     *
     * @return array{list<_Command>, list<string>} commands to send, unknown pollers of the skipped external commands
     */
    private function parse(array $lines): array
    {
        $commands = [];
        $unknownPollers = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if (trim($line) === '') {
                continue;
            }

            $parts = explode(':', $line, 3);
            $name = trim($parts[0]);
            $poller = trim($parts[1] ?? '');
            $param = $parts[2] ?? '';
            if ($poller === '') {
                throw GorgoneCommandException::invalidCommandLine(self::describe($line));
            }

            if ($name === self::COMMAND_EXTERNAL) {
                $param = trim(str_replace($this->getIllegalCharacters(), '', $param));
                if ($param === '') {
                    throw GorgoneCommandException::invalidCommandLine(self::describe($line));
                }
                if ($this->findNodeId($poller) === null) {
                    $unknownPollers[$poller] = $poller;

                    continue;
                }
            } elseif (
                ! isset(self::SERVICE_CONTROLS[$name])
                && $name !== self::COMMAND_SEND_CONFIGURATION
                && $name !== self::COMMAND_SYNC_TRAP
            ) {
                throw GorgoneCommandException::unsupportedCommand($name);
            }

            $commands[] = ['name' => $name, 'nodeId' => $this->resolveNodeId($poller), 'param' => $param];
        }

        return [$commands, array_values($unknownPollers)];
    }

    /**
     * @param list<_Command> $commands
     *
     * @throws \Throwable
     *
     * @return list<string>
     */
    private function send(array $commands): array
    {
        $jobIds = [];
        /** @var array<int, list<string>> $externalCommands */
        $externalCommands = [];

        foreach ($commands as $command) {
            $nodeId = $command['nodeId'];
            if ($command['name'] === self::COMMAND_EXTERNAL) {
                $externalCommands[$nodeId][] = $command['param'];

                continue;
            }

            // Keep the order between the external commands and the other ones.
            array_push($jobIds, ...$this->sendExternalCommands($externalCommands));
            $externalCommands = [];

            if ($command['name'] === self::COMMAND_SEND_CONFIGURATION) {
                array_push($jobIds, ...$this->sendConfiguration($nodeId));
            } elseif ($command['name'] === self::COMMAND_SYNC_TRAP) {
                $jobIds[] = $this->writeJobRepository->pushConfiguration(
                    $nodeId,
                    self::TARGET_CENTREONTRAPD,
                    $this->buildArchive($nodeId, self::TARGET_CENTREONTRAPD, true)
                );
            } else {
                [$service, $operation] = self::SERVICE_CONTROLS[$command['name']];
                $jobIds[] = $this->writeJobRepository->submitServiceControl(
                    $nodeId,
                    $service,
                    $operation,
                    $operation === self::OPERATION_STOP ? [] : self::REQUIRED_CONFIGURATIONS[$service]
                );
            }
        }

        array_push($jobIds, ...$this->sendExternalCommands($externalCommands));

        $this->debug('Commands sent through the Gorgone API', ['jobs' => $jobIds]);

        return $jobIds;
    }

    /**
     * @param array<int, list<string>> $externalCommands
     *
     * @throws GorgoneCommandException
     *
     * @return list<string>
     */
    private function sendExternalCommands(array $externalCommands): array
    {
        $jobIds = [];
        foreach ($externalCommands as $nodeId => $commands) {
            $batch = [];
            $batchSize = 0;
            foreach ($commands as $command) {
                if (
                    $batch !== []
                    && (
                        count($batch) >= self::EXTERNAL_COMMANDS_MAX_COUNT
                        || $batchSize + mb_strlen($command, '8bit') > self::EXTERNAL_COMMANDS_MAX_BYTES
                    )
                ) {
                    $jobIds[] = $this->submitEngineCommands($nodeId, $batch);
                    $batch = [];
                    $batchSize = 0;
                }
                $batch[] = $command;
                $batchSize += mb_strlen($command, '8bit');
            }
            if ($batch !== []) {
                $jobIds[] = $this->submitEngineCommands($nodeId, $batch);
            }
        }

        return $jobIds;
    }

    /**
     * @param int $nodeId
     * @param list<string> $commands
     *
     * @throws GorgoneCommandException
     *
     * @return string job id
     */
    private function submitEngineCommands(int $nodeId, array $commands): string
    {
        $jobId = $this->writeJobRepository->submitEngineCommands($nodeId, $commands);
        $this->unwaitedJobIds[$jobId] = true;

        return $jobId;
    }

    /**
     * @param int $nodeId
     *
     * @throws \Throwable
     *
     * @return list<string>
     */
    private function sendConfiguration(int $nodeId): array
    {
        // Every archive is built before the first one is pushed.
        $archives = [
            self::TARGET_ENGINE => $this->buildArchive($nodeId, self::TARGET_ENGINE, true),
            self::TARGET_BROKER => $this->buildArchive($nodeId, self::TARGET_BROKER, false),
            self::TARGET_VMWARE => $this->buildArchive($nodeId, self::TARGET_VMWARE, false),
        ];

        $jobIds = [];
        foreach ($archives as $target => $archive) {
            if (! $archive->isEmpty()) {
                $jobIds[] = $this->writeJobRepository->pushConfiguration($nodeId, $target, $archive);
            }
        }

        return $jobIds;
    }

    /**
     * @param int $nodeId
     * @param string $target
     * @param bool $isRequired
     *
     * @throws \Throwable
     *
     * @return TarArchive
     */
    private function buildArchive(int $nodeId, string $target, bool $isRequired): TarArchive
    {
        $files = $this->readConfigurationRepository->findFiles($nodeId, $target);
        if ($files === [] && $isRequired) {
            throw GorgoneCommandException::missingConfiguration($nodeId, $target);
        }

        try {
            return new TarArchive($files);
        } catch (\InvalidArgumentException $ex) {
            throw GorgoneCommandException::invalidConfigurationFile($nodeId, $target, $ex);
        }
    }

    /**
     * Gorgone identifies a node by nagios_server.id, Centreon sometimes by nagios_server.uid.
     *
     * @param string $poller
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function resolveNodeId(string $poller): int
    {
        return $this->findNodeId($poller) ?? throw GorgoneCommandException::unknownPoller($poller);
    }

    /**
     * @param string $poller
     *
     * @throws \Throwable
     *
     * @return int|null
     */
    private function findNodeId(string $poller): ?int
    {
        if (! array_key_exists($poller, $this->nodeIds)) {
            $this->nodeIds[$poller] = ctype_digit($poller) && (string) (int) $poller === $poller
                ? $this->readMonitoringServerRepository->findIdByInstanceId((int) $poller)
                : null;
        }

        return $this->nodeIds[$poller];
    }

    /**
     * Characters removed from the external commands (it was done by the Gorgone legacycmd module).
     *
     * @throws \Throwable
     *
     * @return list<string>
     */
    private function getIllegalCharacters(): array
    {
        if ($this->illegalCharacters === null) {
            $this->illegalCharacters = [];
            foreach ($this->optionService->findSelectedOptions([self::OPTION_ILLEGAL_CHARACTERS]) as $option) {
                $this->illegalCharacters = mb_str_split((string) $option->getValue());
            }
        }

        return $this->illegalCharacters;
    }

    /**
     * @throws \Throwable
     *
     * @return int timeout of the Gorgone commands, in seconds
     */
    private function getCommandTimeout(): int
    {
        $timeout = self::DEFAULT_COMMAND_TIMEOUT;
        foreach ($this->optionService->findSelectedOptions([self::OPTION_COMMAND_TIMEOUT]) as $option) {
            $timeout = (int) $option->getValue();
        }

        return max(1, $timeout);
    }

    /**
     * @return float current time in seconds, to measure durations
     */
    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : hrtime(true) / 1e9;
    }

    /**
     * Command and poller of a line: the parameter may hold user data.
     *
     * @param string $line
     *
     * @return string
     */
    private static function describe(string $line): string
    {
        return implode(':', array_slice(explode(':', rtrim($line, "\r\n"), 3), 0, 2));
    }
}
