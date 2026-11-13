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

namespace Tests\Core\Gorgone\Application\Service;

use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Option\Option;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\ReadGorgoneConfigurationRepositoryInterface;
use Core\Gorgone\Application\Repository\ReadGorgoneJobRepositoryInterface;
use Core\Gorgone\Application\Repository\WriteGorgoneJobRepositoryInterface;
use Core\Gorgone\Application\Service\GorgoneCommandService;
use Core\Gorgone\Domain\Model\ConfigurationValidation;
use Core\Gorgone\Domain\Model\JobOutcome;
use Core\Gorgone\Domain\Model\TarArchive;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Psr\Log\LoggerInterface;

const POLLER_UID = 7300000000000000001;

/**
 * Records the calls made to the Gorgone API, in order.
 */
final class RecordingGorgoneJobRepository implements WriteGorgoneJobRepositoryInterface
{
    /** @var list<array<int, mixed>> */
    public array $calls = [];

    public ?\Throwable $failure = null;

    /** @var array<int, \Throwable> failure by node id */
    public array $nodeFailures = [];

    /** @var int number of calls, failed ones included */
    public int $attempts = 0;

    public function submitEngineCommands(int $nodeId, array $commands): string
    {
        return $this->record(['engine_commands', $nodeId, $commands]);
    }

    public function submitServiceControl(
        int $nodeId,
        string $service,
        string $operation,
        array $requiresLatestConfig = [],
    ): string {
        return $this->record([
            'service_control',
            $nodeId,
            $service,
            $operation,
            ...($requiresLatestConfig !== [] ? [$requiresLatestConfig] : []),
        ]);
    }

    public function pushConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->record(['config', $nodeId, $target, $archive->getFileNames()]);
    }

    public function validateConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->record(['validation', $nodeId, $target, $archive->getFileNames()]);
    }

    /**
     * @param array<int, mixed> $call
     */
    private function record(array $call): string
    {
        $this->attempts++;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (isset($this->nodeFailures[$call[1]])) {
            throw $this->nodeFailures[$call[1]];
        }
        $this->calls[] = $call;

        return 'job-' . count($this->calls);
    }
}

/**
 * Gives the state of the jobs and records the waits; a wait on a pending job lasts until its end.
 */
final class FakeGorgoneJobStateRepository implements ReadGorgoneJobRepositoryInterface
{
    /** @var array<string, array{0: string, 1: string, 2?: string|null, 3?: int|null}> state, message, output and exit code by job id, succeeded by default */
    public array $states = [];

    /** @var list<int> number of jobs submitted when each wait started */
    public array $submittedAtWaits = [];

    public ?RecordingGorgoneJobRepository $jobs = null;

    /** @var list<array{string, int}> job id and wait of each call */
    public array $waits = [];

    public ?\Throwable $failure = null;

    /** @var float current time, in seconds */
    public float $now = 1000.0;

    public function findOutcome(string $jobId, int $waitSeconds): JobOutcome
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->waits[] = [$jobId, $waitSeconds];
        $this->submittedAtWaits[] = count($this->jobs->calls ?? []);
        $outcome = new JobOutcome($jobId, ...($this->states[$jobId] ?? [JobOutcome::STATE_SUCCEEDED, '']));
        if ($outcome->isPending()) {
            $this->now += $waitSeconds;
        } else {
            // A finished job is answered at once, here after half a second.
            $this->now += 0.5;
        }

        return $outcome;
    }
}

beforeEach(function (): void {
    $this->jobs = new RecordingGorgoneJobRepository();
    $this->jobStates = new FakeGorgoneJobStateRepository();
    $this->jobStates->jobs = $this->jobs;
    $this->commandTimeout = '10';

    // Pollers 1, 2 and 5 exist; 5 is also known by its uid.
    $this->monitoringServers = $this->createMock(ReadMonitoringServerRepositoryInterface::class);
    $this->monitoringServers->method('findIdByInstanceId')->willReturnCallback(
        static fn (int $instanceId): ?int => match ($instanceId) {
            1, 2, 5 => $instanceId,
            POLLER_UID => 5,
            default => null,
        }
    );

    $this->files = [];
    $this->configuration = $this->createMock(ReadGorgoneConfigurationRepositoryInterface::class);
    $this->configuration->method('findFiles')->willReturnCallback(
        function (int $nodeId, string $target): array {
            /** @var array<int, array<string, array<string, string>>> $files */
            $files = $this->files;

            return $files[$nodeId][$target] ?? [];
        }
    );

    $this->optionService = $this->createMock(OptionServiceInterface::class);
    $this->optionService->method('findSelectedOptions')->willReturnCallback(
        fn (array $names): array => array_values(array_filter(
            [
                (new Option())->setName('gorgone_illegal_characters')->setValue('`$'),
                (new Option())->setName('gorgone_cmd_timeout')->setValue($this->commandTimeout),
            ],
            static fn (Option $option): bool => in_array($option->getName(), $names, true)
        ))
    );

    $this->createService = fn (int $flag = 1): GorgoneCommandService => new GorgoneCommandService(
        new FeatureFlags(false, json_encode(['gorgone_api_commands' => $flag], JSON_THROW_ON_ERROR)),
        $this->monitoringServers,
        $this->configuration,
        $this->jobs,
        $this->jobStates,
        $this->optionService,
        fn (): float => $this->jobStates->now,
    );
});

it('should be disabled by default', function (): void {
    $featuresFile = dirname(__DIR__, 6) . '/config/features.json';
    $features = json_decode((string) file_get_contents($featuresFile), true, 512, JSON_THROW_ON_ERROR);

    expect($features)->toHaveKey(GorgoneCommandService::FEATURE_FLAG)
        ->and($features[GorgoneCommandService::FEATURE_FLAG])->toBe(0);
});

it('should call nothing when the feature flag is disabled', function (int $flag): void {
    $this->monitoringServers->expects($this->never())->method('findIdByInstanceId');
    $this->configuration->expects($this->never())->method('findFiles');
    $this->optionService->expects($this->never())->method('findSelectedOptions');
    $service = ($this->createService)($flag);

    expect($service->isEnabled())->toBeFalse();

    try {
        $service->sendLegacyCommands(['RELOAD:1', 'EXTERNALCMD:1:[1700000000] ENABLE_NOTIFICATIONS', 'SENDCFGFILE:1']);
        $this->fail('An exception is expected');
    } catch (GorgoneCommandException $ex) {
        expect($ex->getMessage())->toContain('gorgone_api_commands');
    }

    expect($this->jobs->calls)->toBe([]);
})->with([
    'disabled' => [0],
    // 2 enables the flag on Cloud platforms only.
    'cloud only' => [2],
]);

it('should map a legacy command to a service control', function (
    string $command,
    string $service,
    string $operation,
    array $requiresLatestConfig,
): void {
    $jobIds = ($this->createService)()->sendLegacyCommands($command . ":1\n");

    // A service is not started, restarted nor reloaded on top of a failed push of its configuration.
    expect($this->jobs->calls)->toBe([
        ['service_control', 1, $service, $operation, ...($requiresLatestConfig !== [] ? [$requiresLatestConfig] : [])],
    ])->and($jobIds)->toBe(['job-1']);
})->with([
    // Not vmware for the engine: a poller without vmware refuses that push and must still restart.
    ['RESTART', 'engine', 'restart', ['engine', 'broker']],
    ['ENGINERESTART', 'engine', 'restart', ['engine', 'broker']],
    ['RELOAD', 'engine', 'reload', ['engine', 'broker']],
    ['ENGINERELOAD', 'engine', 'reload', ['engine', 'broker']],
    ['START', 'engine', 'start', ['engine', 'broker']],
    ['STOP', 'engine', 'stop', []],
    ['RELOADBROKER', 'broker', 'reload', ['broker']],
    ['RELOADCENTREONTRAPD', 'centreontrapd', 'reload', ['centreontrapd']],
    ['RESTARTCENTREONTRAPD', 'centreontrapd', 'restart', ['centreontrapd']],
    ['VMWARERESTART', 'vmware', 'restart', ['vmware']],
]);

it('should route the central poller like the other pollers', function (): void {
    // The service never looks at nagios_server.localhost: poller 1 is the central one.
    $this->files[1] = ['engine' => ['centengine.cfg' => 'engine'], 'broker' => ['central-broker.json' => '{}']];
    $this->files[2] = ['engine' => ['centengine.cfg' => 'engine'], 'broker' => ['central-module.json' => '{}']];

    ($this->createService)()->sendLegacyCommands(
        ['SENDCFGFILE:1', 'RELOADBROKER:1', 'RESTART:1', 'SENDCFGFILE:2', 'RELOADBROKER:2', 'RESTART:2']
    );

    $callsOf = fn (int $nodeId): array => array_values(array_map(
        static fn (array $call): array => array_merge(array_slice($call, 0, 1), array_slice($call, 2)),
        array_filter($this->jobs->calls, static fn (array $call): bool => $call[1] === $nodeId)
    ));
    expect($callsOf(1))->toBe([
        ['config', 'engine', ['centengine.cfg']],
        ['config', 'broker', ['central-broker.json']],
        ['service_control', 'broker', 'reload', ['broker']],
        ['service_control', 'engine', 'restart', ['engine', 'broker']],
    ])->and(array_column($callsOf(2), 0))->toBe(array_column($callsOf(1), 0));
});

it('should send the external commands of a poller in one call, in order', function (): void {
    $jobIds = ($this->createService)()->sendLegacyCommands(
        "EXTERNALCMD:1:[1700000000] ACKNOWLEDGE_HOST_PROBLEM;host;2;0;1;admin;comment\n"
        . "EXTERNALCMD:2:[1700000001] SCHEDULE_FORCED_HOST_CHECK;other;1700000001\n"
        . "EXTERNALCMD:1:[1700000002] PROCESS_SERVICE_CHECK_RESULT;host;svc;0;OK: 10:30|time=1s\n"
        . "\n"
    );

    expect($this->jobs->calls)->toBe([
        ['engine_commands', 1, [
            '[1700000000] ACKNOWLEDGE_HOST_PROBLEM;host;2;0;1;admin;comment',
            '[1700000002] PROCESS_SERVICE_CHECK_RESULT;host;svc;0;OK: 10:30|time=1s',
        ]],
        ['engine_commands', 2, ['[1700000001] SCHEDULE_FORCED_HOST_CHECK;other;1700000001']],
    ])->and($jobIds)->toBe(['job-1', 'job-2']);
});

it('should resolve the uid of a poller to its id', function (): void {
    ($this->createService)()->sendLegacyCommands([
        'EXTERNALCMD:' . POLLER_UID . ':[1700000000] ENABLE_NOTIFICATIONS',
        'EXTERNALCMD:5:[1700000001] DISABLE_NOTIFICATIONS',
        'RELOAD:' . POLLER_UID,
    ]);

    expect($this->jobs->calls)->toBe([
        ['engine_commands', 5, ['[1700000000] ENABLE_NOTIFICATIONS', '[1700000001] DISABLE_NOTIFICATIONS']],
        ['service_control', 5, 'engine', 'reload', ['engine', 'broker']],
    ]);
});

it('should remove the Gorgone illegal characters from the external commands', function (): void {
    ($this->createService)()->sendLegacyCommands(
        'EXTERNALCMD:1:[1700000000] ADD_HOST_COMMENT;host;1;admin;`rm` $HOME' . "\r\n"
    );

    expect($this->jobs->calls)->toBe([
        ['engine_commands', 1, ['[1700000000] ADD_HOST_COMMENT;host;1;admin;rm HOME']],
    ]);
});

it('should keep the order between external commands and the other commands', function (): void {
    ($this->createService)()->sendLegacyCommands([
        'EXTERNALCMD:1:[1700000000] FIRST',
        'RELOAD:1',
        'EXTERNALCMD:1:[1700000001] SECOND',
    ]);

    expect($this->jobs->calls)->toBe([
        ['engine_commands', 1, ['[1700000000] FIRST']],
        ['service_control', 1, 'engine', 'reload', ['engine', 'broker']],
        ['engine_commands', 1, ['[1700000001] SECOND']],
    ]);
});

it('should split a large number of external commands into several jobs', function (): void {
    $lines = [];
    for ($index = 0; $index < 450; $index++) {
        $lines[] = "EXTERNALCMD:1:[1700000000] COMMAND_{$index}";
    }
    ($this->createService)()->sendLegacyCommands($lines);

    expect(array_map(static fn (array $call): int => count($call[2]), $this->jobs->calls))->toBe([200, 200, 50])
        ->and($this->jobs->calls[2][2][49])->toBe('[1700000000] COMMAND_449');
});

it('should push the engine, broker and vmware configurations for SENDCFGFILE', function (): void {
    $this->files[2] = [
        'engine' => ['centengine.cfg' => 'engine', 'hosts.cfg' => 'hosts'],
        'broker' => ['central-module.json' => '{}'],
        'vmware' => ['centreon_vmware.json' => '{}'],
    ];

    $jobIds = ($this->createService)()->sendLegacyCommands('SENDCFGFILE:2');

    expect($this->jobs->calls)->toBe([
        ['config', 2, 'engine', ['centengine.cfg', 'hosts.cfg']],
        ['config', 2, 'broker', ['central-module.json']],
        ['config', 2, 'vmware', ['centreon_vmware.json']],
    ])->and($jobIds)->toBe(['job-1', 'job-2', 'job-3']);
});

it('should not push the vmware nor the broker configuration when there is none', function (): void {
    $this->files[2] = ['engine' => ['centengine.cfg' => 'engine']];

    ($this->createService)()->sendLegacyCommands('SENDCFGFILE:2');

    expect($this->jobs->calls)->toBe([['config', 2, 'engine', ['centengine.cfg']]]);
});

it('should fail without any call when the engine configuration is not generated', function (): void {
    $this->files[2] = ['broker' => ['central-module.json' => '{}']];

    expect(fn () => ($this->createService)()->sendLegacyCommands('SENDCFGFILE:2'))
        ->toThrow(GorgoneCommandException::class, 'No "engine" configuration file found for poller #2')
        ->and($this->jobs->calls)->toBe([]);
});

it('should push the traps database for SYNCTRAP', function (): void {
    $this->files[1] = ['centreontrapd' => ['centreontrapd.sdb' => 'SQLite format 3']];

    ($this->createService)()->sendLegacyCommands('SYNCTRAP:1');

    expect($this->jobs->calls)->toBe([['config', 1, 'centreontrapd', ['centreontrapd.sdb']]]);
});

it('should fail when the traps database is not generated', function (): void {
    expect(fn () => ($this->createService)()->sendLegacyCommands('SYNCTRAP:1'))
        ->toThrow(GorgoneCommandException::class, 'No "centreontrapd" configuration file found')
        ->and($this->jobs->calls)->toBe([]);
});

it('should refuse the commands the Gorgone API does not support, without any call', function (string $line): void {
    expect(fn () => ($this->createService)()->sendLegacyCommands(['RELOAD:1', $line]))
        ->toThrow(GorgoneCommandException::class)
        ->and($this->jobs->calls)->toBe([]);
})->with([
    'remote server worker' => ['STARTWORKER:1'],
    'remote server export' => ['SENDEXPORTFILE:2:12'],
    'unknown command' => ['UNKNOWN:1'],
    'lower case' => ['reload:1'],
    'no poller' => ['RELOAD'],
    'empty poller' => ['RELOAD:'],
    'empty external command' => ['EXTERNALCMD:1:'],
    'unknown poller' => ['RELOAD:99'],
    'poller name' => ['RELOAD:Central'],
]);

it('should send the other external commands then fail when a poller is unknown', function (): void {
    expect(fn () => ($this->createService)()->sendLegacyCommands([
        'EXTERNALCMD:0:[1700000000] LOST',
        'EXTERNALCMD:1:[1700000001] SENT',
    ]))->toThrow(GorgoneCommandException::class, 'Poller "0" does not exist')
        ->and($this->jobs->calls)->toBe([['engine_commands', 1, ['[1700000001] SENT']]]);
});

it('should end a command at a line break, as in a centcore file', function (): void {
    expect(fn () => ($this->createService)()->sendLegacyCommands([
        "EXTERNALCMD:1:[1700000000] PROCESS_HOST_CHECK_RESULT;host;0;first line\n[1700000000] SECOND",
    ]))->toThrow(GorgoneCommandException::class, 'Invalid Gorgone command line')
        ->and($this->jobs->calls)->toBe([]);
});

it('should log and propagate the errors of the Gorgone API', function (): void {
    $this->jobs->failure = GorgoneCommandException::unknownNode(1);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('log')
        ->with(
            'error',
            $this->stringContains('Poller #1 is not known by Gorgone'),
            // The parameters of the commands may hold user data: only command and poller are logged.
            $this->callback(static fn (array $context): bool => $context['commands'] === ['EXTERNALCMD:1'])
        );
    $service = ($this->createService)();
    $service->setLogger($logger);

    expect(fn () => $service->sendLegacyCommands('EXTERNALCMD:1:[1700000000] ADD_HOST_COMMENT;host;1;admin;secret'))
        ->toThrow(GorgoneCommandException::class, 'Poller #1 is not known by Gorgone');
});

it('should wrap the unexpected errors', function (): void {
    $this->jobs->failure = new \RuntimeException('connection refused');

    expect(fn () => ($this->createService)()->sendLegacyCommands('RESTART:1'))
        ->toThrow(GorgoneCommandException::class, 'connection refused');
});

it('should report the jobs which succeeded', function (): void {
    $outcomes = ($this->createService)()->waitForJobs(['job-1', 'job-2', 'job-1']);

    expect($outcomes->isFailed())->toBeFalse()
        ->and($outcomes->isPending())->toBeFalse()
        ->and($outcomes->all())->toHaveCount(2)
        ->and(array_column($this->jobStates->waits, 0))->toBe(['job-1', 'job-2']);
});

it('should report the message of a failed, expired or cancelled job', function (string $state, string $message, string $expected): void {
    $this->jobStates->states['job-2'] = [$state, $message];
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('log')->with('error', $this->stringContains($expected));
    $service = ($this->createService)();
    $service->setLogger($logger);

    $outcomes = $service->waitForJobs(['job-1', 'job-2', 'job-3']);

    expect($outcomes->isFailed())->toBeTrue()
        ->and($outcomes->isPending())->toBeFalse()
        ->and($outcomes->getFailureMessage())->toBe($expected)
        ->and($outcomes->of(['job-1', 'job-3'])->isFailed())->toBeFalse()
        // The jobs after a failed one are still read.
        ->and(array_column($this->jobStates->waits, 0))->toBe(['job-1', 'job-2', 'job-3']);
})->with([
    'failed' => ['failed', 'centengine: configuration check failed', 'centengine: configuration check failed'],
    'failed by the latest configuration push' => ['failed', 'latest configuration push failed', 'latest configuration push failed'],
    'expired without message' => ['expired', '', 'expired'],
    'cancelled' => ['cancelled', '', 'cancelled'],
]);

it('should report a job still in progress when the time is up, without error', function (string $state): void {
    $this->jobStates->states['job-1'] = [$state, 'accepted'];
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('log');
    $service = ($this->createService)();
    $service->setLogger($logger);

    $outcomes = $service->waitForJobs(['job-1', 'job-2']);

    expect($outcomes->isPending())->toBeTrue()
        ->and($outcomes->isFailed())->toBeFalse()
        ->and($outcomes->getFailureMessage())->toBe('')
        ->and($outcomes->of(['job-2'])->isPending())->toBeFalse()
        // The time was spent on the first job: the state of the second one is read without waiting.
        ->and($this->jobStates->waits)->toBe([['job-1', 10], ['job-2', 0]])
        ->and($this->jobStates->now)->toBeLessThanOrEqual(1000.0 + 10 + 0.5);
})->with(['queued', 'dispatched', 'running']);

it('should share the Gorgone command timeout between the jobs', function (): void {
    ($this->createService)()->waitForJobs(['job-1', 'job-2', 'job-3']);

    // Each finished job is answered in half a second.
    expect($this->jobStates->waits)->toBe([['job-1', 10], ['job-2', 9], ['job-3', 9]]);
});

it('should never wait more than 30 seconds, whatever the Gorgone command timeout', function (): void {
    $this->commandTimeout = '300';
    $this->jobStates->states['job-1'] = ['running', ''];
    $this->jobStates->states['job-2'] = ['running', ''];

    $outcomes = ($this->createService)()->waitForJobs(['job-1', 'job-2', 'job-3']);

    expect($this->jobStates->waits)->toBe([['job-1', 30], ['job-2', 0], ['job-3', 0]])
        ->and($this->jobStates->now)->toBeLessThanOrEqual(1000.0 + GorgoneCommandService::MAX_WAIT_SECONDS + 1)
        ->and($outcomes->isPending())->toBeTrue();
});

it('should wait at least one second when the Gorgone command timeout is not set', function (): void {
    $this->commandTimeout = '0';

    ($this->createService)()->waitForJobs(['job-1']);

    expect($this->jobStates->waits)->toBe([['job-1', 1]]);
});

it('should never wait for the external commands', function (): void {
    $service = ($this->createService)();
    $jobIds = $service->sendLegacyCommands([
        'EXTERNALCMD:1:[1700000000] ACKNOWLEDGE_HOST_PROBLEM;host;2;0;1;admin;comment',
        'EXTERNALCMD:2:[1700000000] SCHEDULE_HOST_DOWNTIME;other;1;2;1;0;3600;admin;comment',
        'RELOAD:1',
        'EXTERNALCMD:1:[1700000001] SCHEDULE_FORCED_HOST_CHECK;host;1700000001',
    ]);
    // Even a failed one is neither read nor reported.
    $this->jobStates->states['job-1'] = ['failed', 'unknown external command'];

    $outcomes = $service->waitForJobs($jobIds);

    expect($jobIds)->toBe(['job-1', 'job-2', 'job-3', 'job-4'])
        ->and($this->jobStates->waits)->toBe([['job-3', 10]])
        ->and($outcomes->isFailed())->toBeFalse()
        ->and(array_map(static fn (JobOutcome $outcome): string => $outcome->getJobId(), $outcomes->all()))
        ->toBe(['job-3']);
});

it('should call nothing when only external commands were sent', function (): void {
    $this->optionService->expects($this->once())->method('findSelectedOptions');
    $service = ($this->createService)();
    $jobIds = $service->sendLegacyCommands('EXTERNALCMD:1:[1700000000] ENABLE_NOTIFICATIONS');

    expect($service->waitForJobs($jobIds)->all())->toBe([])
        ->and($this->jobStates->waits)->toBe([]);
});

it('should describe the outcome of each operation', function (): void {
    $this->jobStates->states['job-2'] = ['failed', 'cannot write /etc/centreon-engine/hosts.cfg'];
    $this->jobStates->states['job-4'] = ['running', ''];

    $report = ($this->createService)()->reportOutcomes([
        'Central' => ['job-1'],
        'Poller-1' => ['job-2', 'job-3'],
        'Poller-2' => ['job-4', 'job-5'],
        'Poller-3' => [],
    ]);

    expect($report)->toBe([
        'messages' => [
            'Central: done.',
            'Poller-1: failed (cannot write /etc/centreon-engine/hosts.cfg).',
            'Poller-2: still in progress.',
            'Poller-3: done.',
        ],
        'failures' => ['Poller-1: failed (cannot write /etc/centreon-engine/hosts.cfg).'],
    ])->and(array_column($this->jobStates->waits, 0))->toBe(['job-1', 'job-2', 'job-3', 'job-4', 'job-5']);
});

it('should not wait when the feature flag is disabled', function (): void {
    expect(fn () => ($this->createService)(0)->waitForJobs(['job-1']))
        ->toThrow(GorgoneCommandException::class, 'gorgone_api_commands')
        ->and($this->jobStates->waits)->toBe([]);
});

it('should log and propagate the errors met while reading the jobs', function (): void {
    $this->jobStates->failure = new \RuntimeException('connection refused');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('log')
        ->with('error', $this->stringContains('connection refused'));
    $service = ($this->createService)();
    $service->setLogger($logger);

    expect(fn () => $service->reportOutcomes(['Central' => ['job-1']]))
        ->toThrow(GorgoneCommandException::class, 'connection refused');
});

const ENGINE_FILES = ['centengine.DEBUG' => 'cfg_file=hosts.cfg', 'hosts.cfg' => 'define host {}'];
const CHECK_OK = "Checking hosts...\n\nTotal Warnings: 0\nTotal Errors:   0\n\nThings look okay\n";
const CHECK_KO = "Error: Could not find any host matching 'srv'\n\nTotal Warnings: 1\nTotal Errors:   2\n";
const UNVERIFIED_MESSAGE = 'The configuration could not be verified now: the poller will verify it when it is applied.';

it('should ask the poller to validate the engine archive a push would send', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES, 'broker' => ['central-broker.json' => '{}']]];
    $this->jobStates->states['job-1'] = ['succeeded', 'engine: configuration valid', CHECK_OK, 0];
    $service = ($this->createService)();

    $validations = $service->validateConfiguration([1]);
    $service->sendLegacyCommands('SENDCFGFILE:1');

    expect($this->jobs->calls[0])->toBe(['validation', 1, 'engine', ['centengine.DEBUG', 'hosts.cfg']])
        // The configuration push sends the same engine files.
        ->and($this->jobs->calls[1])->toBe(['config', 1, 'engine', ['centengine.DEBUG', 'hosts.cfg']])
        ->and(array_keys($validations))->toBe([1])
        ->and($validations[1]->getPollerId())->toBe(1)
        ->and($validations[1]->getStatus())->toBe(ConfigurationValidation::STATUS_VALID)
        ->and($validations[1]->isVerified())->toBeTrue()
        ->and($validations[1]->getOutput())->toBe(CHECK_OK)
        ->and($validations[1]->getExitCode())->toBe(0)
        ->and($validations[1]->getMessage())->toBe('engine: configuration valid');
});

it('should report a configuration refused by the engine of the poller', function (
    ?string $output,
    ?int $exitCode,
    string $expectedOutput,
    int $expectedExitCode,
): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES]];
    $this->jobStates->states['job-2'] = ['failed', 'engine: configuration rejected, 2 error(s)', $output, $exitCode];

    $validations = ($this->createService)()->validateConfiguration([1, 2]);

    expect($validations[1]->isValid())->toBeTrue()
        ->and($validations[2]->getStatus())->toBe(ConfigurationValidation::STATUS_INVALID)
        ->and($validations[2]->isVerified())->toBeTrue()
        ->and($validations[2]->getOutput())->toBe($expectedOutput)
        ->and($validations[2]->getExitCode())->toBe($expectedExitCode)
        ->and($validations[2]->getMessage())->toBe('engine: configuration rejected, 2 error(s)');
})->with([
    'with the output of the checker' => [CHECK_KO, 2, CHECK_KO, 2],
    'without output nor exit code' => [null, null, '', 1],
    'with an exit code of 0' => [CHECK_KO, 0, CHECK_KO, 1],
]);

it('should not treat a poller which did not verify in time as an error', function (string $state): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES]];
    $this->jobStates->states['job-1'] = [$state, 'accepted'];
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('log');
    $service = ($this->createService)();
    $service->setLogger($logger);

    $validations = $service->validateConfiguration([1, 2]);

    expect($validations[1]->getStatus())->toBe(ConfigurationValidation::STATUS_UNVERIFIED)
        ->and($validations[1]->isUnverified())->toBeTrue()
        ->and($validations[1]->isVerified())->toBeFalse()
        ->and($validations[1]->isError())->toBeFalse()
        ->and($validations[1]->getMessage())->toBe(UNVERIFIED_MESSAGE)
        ->and($validations[1]->getOutput())->toBe('')
        ->and($validations[1]->getExitCode())->toBe(0)
        // The other pollers are still read.
        ->and($validations[2]->isValid())->toBeTrue();
})->with([
    'not connected' => ['queued'],
    'sent' => ['dispatched'],
    'still checking' => ['running'],
    'never connected' => ['expired'],
    'cancelled' => ['cancelled'],
]);

it('should ask every poller before waiting for the first answer', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES], 5 => ['engine' => ENGINE_FILES]];

    $validations = ($this->createService)()->validateConfiguration([5, 1, 2, 5]);

    expect(array_column($this->jobs->calls, 1))->toBe([5, 1, 2])
        ->and($this->jobStates->submittedAtWaits)->toBe([3, 3, 3])
        ->and(array_column($this->jobStates->waits, 0))->toBe(['job-1', 'job-2', 'job-3'])
        // Results in the order of the request.
        ->and(array_keys($validations))->toBe([5, 1, 2]);
});

it('should share the time allowed between the validations', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES], 5 => ['engine' => ENGINE_FILES]];

    ($this->createService)()->validateConfiguration([1, 2, 5]);

    // Each finished job is answered in half a second.
    expect($this->jobStates->waits)->toBe([['job-1', 10], ['job-2', 9], ['job-3', 9]]);
});

it('should never wait more than 30 seconds for the validations', function (): void {
    $this->commandTimeout = '300';
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES], 5 => ['engine' => ENGINE_FILES]];
    $this->jobStates->states['job-1'] = ['queued', ''];
    $this->jobStates->states['job-2'] = ['running', ''];
    $this->jobStates->states['job-3'] = ['succeeded', 'engine: configuration valid', CHECK_OK, 0];

    $validations = ($this->createService)()->validateConfiguration([1, 2, 5]);

    expect($this->jobStates->waits)->toBe([['job-1', 30], ['job-2', 0], ['job-3', 0]])
        ->and($this->jobStates->now)->toBeLessThanOrEqual(1000.0 + GorgoneCommandService::MAX_WAIT_SECONDS + 1)
        ->and($validations[1]->isUnverified())->toBeTrue()
        ->and($validations[2]->isUnverified())->toBeTrue()
        // A job already finished is read even once the time is up.
        ->and($validations[5]->isValid())->toBeTrue();
});

it('should report the error of the Gorgone API for the poller it concerns', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES], 5 => ['engine' => ENGINE_FILES]];
    $this->jobs->nodeFailures[2] = GorgoneCommandException::unexpectedResponse('validation', 500, 'store unavailable');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('log')->with('error', $this->stringContains('store unavailable'));
    $service = ($this->createService)();
    $service->setLogger($logger);

    $validations = $service->validateConfiguration([1, 2, 5]);

    expect($validations[2]->getStatus())->toBe(ConfigurationValidation::STATUS_ERROR)
        ->and($validations[2]->isVerified())->toBeFalse()
        ->and($validations[2]->getMessage())->toContain('HTTP 500', 'store unavailable')
        ->and($validations[1]->isValid())->toBeTrue()
        ->and($validations[5]->isValid())->toBeTrue()
        ->and($this->jobs->attempts)->toBe(3)
        ->and(array_keys($validations))->toBe([1, 2, 5]);
});

it('should report an unreachable Gorgone API for every poller, after one attempt', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES], 5 => ['engine' => ENGINE_FILES]];
    $this->jobs->failure = GorgoneCommandException::requestFailed('validation', new \RuntimeException('connection refused'));

    $validations = ($this->createService)()->validateConfiguration([1, 2, 5]);

    expect(array_keys($validations))->toBe([1, 2, 5])
        ->and($this->jobs->attempts)->toBe(1)
        ->and($this->jobStates->waits)->toBe([]);
    foreach ($validations as $validation) {
        expect($validation->isError())->toBeTrue()
            ->and($validation->getMessage())->toContain('connection refused');
    }
});

it('should report a poller whose engine configuration is not generated, without any call for it', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES]];

    $validations = ($this->createService)()->validateConfiguration([2, 1]);

    expect($validations[2]->isError())->toBeTrue()
        ->and($validations[2]->getMessage())->toContain('No "engine" configuration file found for poller #2')
        ->and($validations[1]->isValid())->toBeTrue()
        ->and($this->jobs->calls)->toBe([['validation', 1, 'engine', ['centengine.DEBUG', 'hosts.cfg']]]);
});

it('should report the error met while reading the validations for every poller asked', function (): void {
    $this->files = [1 => ['engine' => ENGINE_FILES], 2 => ['engine' => ENGINE_FILES]];
    $this->jobStates->failure = new \RuntimeException('connection refused');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('log')->with('error', $this->stringContains('connection refused'));
    $service = ($this->createService)();
    $service->setLogger($logger);

    $validations = $service->validateConfiguration([1, 2]);

    expect($validations[1]->isError())->toBeTrue()
        ->and($validations[2]->isError())->toBeTrue()
        ->and($validations[2]->getMessage())->toBe('connection refused');
});

it('should validate nothing when the feature flag is disabled', function (int $flag): void {
    $this->configuration->expects($this->never())->method('findFiles');
    $this->optionService->expects($this->never())->method('findSelectedOptions');

    expect(fn () => ($this->createService)($flag)->validateConfiguration([1]))
        ->toThrow(GorgoneCommandException::class, 'gorgone_api_commands')
        ->and($this->jobs->attempts)->toBe(0)
        ->and($this->jobStates->waits)->toBe([]);
})->with([
    'disabled' => [0],
    'cloud only' => [2],
]);

it('should call nothing when no poller is to validate', function (): void {
    $this->optionService->expects($this->never())->method('findSelectedOptions');

    expect(($this->createService)()->validateConfiguration([]))->toBe([])
        ->and($this->jobs->attempts)->toBe(0)
        ->and($this->jobStates->waits)->toBe([]);
});
