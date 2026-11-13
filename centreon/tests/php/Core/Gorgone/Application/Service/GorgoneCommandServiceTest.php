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
use Core\Gorgone\Application\Repository\WriteGorgoneJobRepositoryInterface;
use Core\Gorgone\Application\Service\GorgoneCommandService;
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

    public function submitEngineCommands(int $nodeId, array $commands): string
    {
        return $this->record(['engine_commands', $nodeId, $commands]);
    }

    public function submitServiceControl(int $nodeId, string $service, string $operation): string
    {
        return $this->record(['service_control', $nodeId, $service, $operation]);
    }

    public function pushConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->record(['config', $nodeId, $target, $archive->getFileNames()]);
    }

    /**
     * @param array<int, mixed> $call
     */
    private function record(array $call): string
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->calls[] = $call;

        return 'job-' . count($this->calls);
    }
}

beforeEach(function (): void {
    $this->jobs = new RecordingGorgoneJobRepository();

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
    $this->optionService->method('findSelectedOptions')->willReturn(
        [(new Option())->setName('gorgone_illegal_characters')->setValue('`$')]
    );

    $this->createService = fn (int $flag = 1): GorgoneCommandService => new GorgoneCommandService(
        new FeatureFlags(false, json_encode(['gorgone_api_commands' => $flag], JSON_THROW_ON_ERROR)),
        $this->monitoringServers,
        $this->configuration,
        $this->jobs,
        $this->optionService,
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
): void {
    $jobIds = ($this->createService)()->sendLegacyCommands($command . ":1\n");

    expect($this->jobs->calls)->toBe([['service_control', 1, $service, $operation]])
        ->and($jobIds)->toBe(['job-1']);
})->with([
    ['RESTART', 'engine', 'restart'],
    ['ENGINERESTART', 'engine', 'restart'],
    ['RELOAD', 'engine', 'reload'],
    ['ENGINERELOAD', 'engine', 'reload'],
    ['START', 'engine', 'start'],
    ['STOP', 'engine', 'stop'],
    ['RELOADBROKER', 'broker', 'reload'],
    ['RELOADCENTREONTRAPD', 'centreontrapd', 'reload'],
    ['RESTARTCENTREONTRAPD', 'centreontrapd', 'restart'],
    ['VMWARERESTART', 'vmware', 'restart'],
]);

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
        ['service_control', 5, 'engine', 'reload'],
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
        ['service_control', 1, 'engine', 'reload'],
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
