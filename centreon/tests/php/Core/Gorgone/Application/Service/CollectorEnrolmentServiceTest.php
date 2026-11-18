<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Core\Gorgone\Application\Repository\CollectorDeployerInterface;
use Core\Gorgone\Application\Repository\CollectorEnrolmentRecordRepositoryInterface;
use Core\Gorgone\Application\Repository\CollectorEnrolmentRepositoryInterface;
use Core\Gorgone\Application\Repository\CollectorProvisionerInterface;
use Core\Gorgone\Application\Service\CollectorEnrolmentService;
use Core\Gorgone\Domain\Model\CollectorEnrolment;
use Core\Gorgone\Domain\Model\CollectorEnrolmentToken;

/**
 * gorgone-server as the service sees it: tokens by hash, enrolments, nodes.
 */
final class InMemoryEnrolments implements CollectorEnrolmentRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $tokens = [];

    /** @var array<string, CollectorEnrolment> */
    public array $enrolments = [];

    /** @var array<int, array{connected: bool, configured: bool}> */
    public array $nodes = [];

    /** @var list<int> */
    public array $deletedNodes = [];

    /** @var list<array{string, int}> */
    public array $assignments = [];

    /** @var list<int> */
    public array $rotations = [];

    public function addToken(
        string $id,
        string $tokenHash,
        ?int $pollerId,
        ?string $name,
        int $expiresInSeconds,
        ?int $maxUses,
        bool $approvalRequired = false,
    ): void {
        $this->tokens[$id] = compact('tokenHash', 'pollerId', 'name', 'expiresInSeconds', 'maxUses', 'approvalRequired');
    }

    public function findTokens(): array
    {
        $tokens = [];
        foreach ($this->tokens as $id => $token) {
            $tokens[] = new CollectorEnrolmentToken(
                (string) $id,
                $token['pollerId'],
                $token['name'],
                1_000_000 + $token['expiresInSeconds'] * 1000,
                $token['maxUses'],
                0,
                1_000_000,
                $token['approvalRequired'],
                null,
            );
        }

        return $tokens;
    }

    public function deleteToken(string $tokenId): bool
    {
        $known = isset($this->tokens[$tokenId]);
        unset($this->tokens[$tokenId]);

        return $known;
    }

    public function approve(string $enrolmentId): void
    {
        $enrolment = $this->enrolments[$enrolmentId] ?? throw CollectorEnrolmentException::notFound('unknown');
        if ($enrolment->state === CollectorEnrolment::STATE_APPROVED) {
            return;
        }
        if (! $enrolment->awaitsApproval()) {
            throw CollectorEnrolmentException::conflict('enrolment is ' . $enrolment->state);
        }
        $this->set($enrolmentId, CollectorEnrolment::STATE_APPROVED);
    }

    public function reject(string $enrolmentId): void
    {
        $enrolment = $this->enrolments[$enrolmentId] ?? throw CollectorEnrolmentException::notFound('unknown');
        if (! in_array($enrolment->state, [CollectorEnrolment::STATE_PENDING, CollectorEnrolment::STATE_APPROVED, CollectorEnrolment::STATE_REJECTED], true)) {
            throw CollectorEnrolmentException::conflict('enrolment is ' . $enrolment->state);
        }
        $this->set($enrolmentId, CollectorEnrolment::STATE_REJECTED);
    }

    public function rotateCredential(int $pollerId): void
    {
        $this->rotations[] = $pollerId;
    }

    public function findEnrolments(): array
    {
        return array_values($this->enrolments);
    }

    public function assign(string $enrolmentId, int $pollerId, string $name): void
    {
        // As gorgone-server: an enrolment that waits for an admin cannot be assigned.
        if (! $this->enrolments[$enrolmentId]->isAssignable()) {
            throw CollectorEnrolmentException::conflict('enrolment awaits approval');
        }
        $this->assignments[] = [$enrolmentId, $pollerId];
        $this->set($enrolmentId, CollectorEnrolment::STATE_ENROLLED, $pollerId, $name);
    }

    public function complete(string $enrolmentId): void
    {
        $this->set($enrolmentId, CollectorEnrolment::STATE_COMPLETED);
    }

    public function findNodeStates(): array
    {
        return $this->nodes;
    }

    public function deleteNode(int $pollerId): void
    {
        $this->deletedNodes[] = $pollerId;
        foreach ($this->enrolments as $enrolment) {
            if ($enrolment->pollerId === $pollerId) {
                $this->set($enrolment->id, CollectorEnrolment::STATE_REVOKED);
            }
        }
    }

    public function enrol(
        string $id,
        string $state,
        ?int $pollerId,
        string $name,
        string $address,
        bool $approvalRequired = false,
    ): void {
        $this->enrolments[$id] = new CollectorEnrolment(
            $id,
            $state,
            $pollerId,
            $name,
            $address,
            '0.1.0',
            'token-of-' . $id,
            true,
            $approvalRequired,
        );
    }

    private function set(string $id, string $state, ?int $pollerId = null, ?string $name = null): void
    {
        $old = $this->enrolments[$id];
        $this->enrol(
            $id,
            $state,
            $pollerId ?? $old->pollerId,
            $name ?? $old->name,
            $old->address,
            $old->approvalRequired
        );
    }
}

/**
 * Table collector_enrolment and the cron lock.
 */
final class InMemoryRecords implements CollectorEnrolmentRecordRepositoryInterface
{
    /** @var array<string, array{name: string, address: string, pollerId: int|null}> */
    public array $records = [];

    public bool $locked = false;

    public function lock(): bool
    {
        if ($this->locked) {
            return false;
        }

        return $this->locked = true;
    }

    public function unlock(): void
    {
        $this->locked = false;
    }

    public function findAll(): array
    {
        return $this->records;
    }

    public function add(string $enrolmentId, string $name, string $address): void
    {
        $this->records[$enrolmentId] ??= ['name' => $name, 'address' => $address, 'pollerId' => null];
    }

    public function setPoller(string $enrolmentId, int $pollerId): void
    {
        $this->records[$enrolmentId]['pollerId'] = $pollerId;
    }
}

/**
 * Pollers created as the wizard would.
 */
final class InMemoryProvisioner implements CollectorProvisionerInterface
{
    /** @var list<array{id: int, name: string, address: string}> */
    public array $pollers = [['id' => 1, 'name' => 'Central', 'address' => '127.0.0.1']];

    /** @var list<array{string, string, string, int, string}> */
    public array $created = [];

    /** @var list<int> */
    public array $centreonEngine = [];

    public function createPoller(
        string $name,
        string $address,
        string $brokerAddress,
        int $creatorId,
        string $creatorName,
    ): int {
        $id = max(array_column($this->pollers, 'id')) + 1;
        $this->pollers[] = ['id' => $id, 'name' => $name, 'address' => $address];
        $this->created[] = [$name, $address, $brokerAddress, $creatorId, $creatorName];
        $this->centreonEngine[] = $id;

        return $id;
    }

    public function markRunByCentreonEngine(int $pollerId): void
    {
        $this->centreonEngine[] = $pollerId;
    }

    public function findPollers(): array
    {
        return $this->pollers;
    }

    public function findCentralAddress(): ?string
    {
        return '127.0.0.1';
    }

    public function findAdministrator(): array
    {
        return [1, 'admin'];
    }
}

beforeEach(function (): void {
    $this->enrolments = new InMemoryEnrolments();
    $this->records = new InMemoryRecords();
    $this->provisioner = new InMemoryProvisioner();
    $this->deployed = [];
    $this->deployOutcome = true;
    $deployer = $this->createMock(CollectorDeployerInterface::class);
    $deployer->method('deploy')->willReturnCallback(function (int $pollerId): array {
        $this->deployed[] = $pollerId;

        return [$this->deployOutcome, 'output'];
    });
    $this->optionValues = ['collector_broker_address' => 'broker'];
    $optionService = $this->createMock(OptionServiceInterface::class);
    $optionService->method('findSelectedOptions')->willReturnCallback(
        fn (array $names): array => array_values(array_map(
            static fn (string $name, string $value): Option => (new Option())->setName($name)->setValue($value),
            array_keys(array_intersect_key($this->optionValues, array_flip($names))),
            array_intersect_key($this->optionValues, array_flip($names))
        ))
    );
    $gorgone = $this->createMock(ConfigurationLoaderApiInterface::class);
    $gorgone->method('getApiIpAddress')->willReturn('gorgone');
    $gorgone->method('isApiConnectionSecure')->willReturn(false);
    $this->randomCalls = 0;

    $this->createService = fn (array $flags = []): CollectorEnrolmentService => new CollectorEnrolmentService(
        new FeatureFlags(false, json_encode($flags + [
            'collector_enrolment' => 1,
            'gorgone_api_commands' => 1,
            'engine_structured_configuration' => 1,
        ], JSON_THROW_ON_ERROR)),
        $this->enrolments,
        $this->provisioner,
        $deployer,
        $optionService,
        $gorgone,
        $this->records,
        function (int $length): string {
            $this->randomCalls++;

            return str_repeat(chr(0xA0 + $this->randomCalls), $length);
        },
    );
});

it('creates the poller of a named enrolment and pushes only the hash of its single-use token', function (): void {
    $created = ($this->createService)()->create('collector-1', null, false, null, null, 7, 'jdoe');

    expect($created->token)->toBe(str_repeat('a1', 32))
        ->and($created->pollerName)->toBe('collector-1')
        ->and($created->pollerId)->toBe(2)
        ->and($created->maxUses)->toBe(1)
        ->and($created->isFleet)->toBeFalse()
        ->and($this->provisioner->created)->toBe([['collector-1', 'collector-1', 'broker', 7, 'jdoe']])
        ->and($this->provisioner->centreonEngine)->toBe([2]);

    $token = $this->enrolments->tokens[$created->id];
    expect($token['tokenHash'])->toBe(hash('sha256', $created->token))
        ->and($token['pollerId'])->toBe(2)
        ->and($token['name'])->toBe('collector-1')
        ->and($token['expiresInSeconds'])->toBe(86400)
        ->and(json_encode($this->enrolments->tokens))->not->toContain($created->token);

    expect($created->environment)->toBe([
        'CENTREON_ENGINE__SERVER_URL' => 'http://gorgone:5556',
        'CENTREON_ENGINE__ENROLMENT_TOKEN' => $created->token,
    ])->and($created->command)->toBe(
        'docker run -d --restart always --name collector-1 --hostname collector-1'
        . ' -e CENTREON_ENGINE__SERVER_URL=http://gorgone:5556'
        . ' -e CENTREON_ENGINE__ENROLMENT_TOKEN=' . $created->token . ' centreon-engine'
    );
});

it('binds a token to an existing poller and marks it run by centreon-engine', function (): void {
    $this->optionValues['collector_server_url'] = 'https://gorgone.example:443';
    $this->optionValues['collector_image'] = 'registry.example/centreon-engine:26.10';
    $created = ($this->createService)()->create(null, 1, false, 3600, null, 7, 'jdoe');

    expect($created->pollerName)->toBe('Central')
        ->and($this->provisioner->created)->toBe([])
        ->and($this->provisioner->centreonEngine)->toBe([1])
        ->and($this->enrolments->tokens[$created->id]['pollerId'])->toBe(1)
        ->and($created->environment['CENTREON_ENGINE__SERVER_URL'])->toBe('https://gorgone.example:443')
        ->and($created->command)->toEndWith(' registry.example/centreon-engine:26.10');
});

it('puts the configured docker network in the command', function (): void {
    $this->optionValues['collector_docker_network'] = 'centreon_default';
    $created = ($this->createService)()->create('collector-1', null, false, 3600, null, 7, 'jdoe');

    expect($created->command)->toContain(' --hostname collector-1 --network centreon_default -e ');
});

it('creates a fleet token with an expiry and optional uses, and no poller yet', function (): void {
    $created = ($this->createService)()->create(null, null, true, 600, 5, 7, 'jdoe');

    expect($created->pollerId)->toBeNull()
        ->and($created->maxUses)->toBe(5)
        ->and($this->provisioner->created)->toBe([])
        ->and($this->enrolments->tokens[$created->id])->toMatchArray(['pollerId' => null, 'expiresInSeconds' => 600, 'maxUses' => 5])
        ->and($created->command)->not->toContain('--hostname');
});

it('refuses invalid requests', function (array $arguments, string $message): void {
    expect(fn () => ($this->createService)()->create(...$arguments))
        ->toThrow(CollectorEnrolmentException::class, $message);
    expect($this->enrolments->tokens)->toBe([])
        ->and($this->provisioner->created)->toBe([]);
})->with([
    'nothing' => [[null, null, false, null, null, 1, 'a'], 'exactly one'],
    'two kinds' => [['x', null, true, null, null, 1, 'a'], 'exactly one'],
    'bad name' => [['bad name!', null, false, null, null, 1, 'a'], 'name must be'],
    'taken name' => [['central', null, false, null, null, 1, 'a'], 'already exists'],
    'unknown poller' => [[null, 99, false, null, null, 1, 'a'], 'does not exist'],
    'uses on a named token' => [['x', null, false, null, 2, 1, 'a'], 'max_uses'],
    'too long' => [[null, null, true, 99999999, null, 1, 'a'], 'expires_in'],
]);

it('needs its feature flag and the structured configuration through the API', function (string $flag): void {
    expect(fn () => ($this->createService)([$flag => 0])->create(null, null, true, null, null, 1, 'a'))
        ->toThrow(CollectorEnrolmentException::class, $flag);
})->with(['collector_enrolment', 'gorgone_api_commands', 'engine_structured_configuration']);

it('creates the poller of a pending fleet enrolment and assigns it', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'web-01.example', '10.1.2.3');
    // Same host name as an existing poller: the name gets a suffix.
    $this->provisioner->pollers[] = ['id' => 5, 'name' => 'web-02', 'address' => '10.1.2.4'];
    $this->enrolments->enrol('e2', CollectorEnrolment::STATE_PENDING, null, 'web-02', '10.1.2.4');

    $done = ($this->createService)()->synchronize();

    expect($this->provisioner->created)->toBe([
        ['web-01.example', '10.1.2.3', 'broker', 1, 'admin'],
        ['web-02-2', 'web-02-2', 'broker', 1, 'admin'],
    ])
        ->and($this->enrolments->enrolments['e1']->pollerId)->toBe(6)
        ->and($this->enrolments->enrolments['e1']->state)->toBe(CollectorEnrolment::STATE_ENROLLED)
        ->and($this->enrolments->enrolments['e2']->name)->toBe('web-02-2')
        ->and($done)->toHaveCount(4)
        // Not connected yet: nothing exported.
        ->and($this->deployed)->toBe([]);
});

it('sends the first configuration once the collector is connected, then completes', function (): void {
    $this->provisioner->pollers[] = ['id' => 2, 'name' => 'c2', 'address' => 'c2'];
    $this->provisioner->pollers[] = ['id' => 3, 'name' => 'c3', 'address' => 'c3'];
    $this->provisioner->pollers[] = ['id' => 4, 'name' => 'c4', 'address' => 'c4'];
    $this->enrolments->enrol('e2', CollectorEnrolment::STATE_ENROLLED, 2, 'c2', 'c2');
    $this->enrolments->enrol('e3', CollectorEnrolment::STATE_ENROLLED, 3, 'c3', 'c3');
    $this->enrolments->enrol('e4', CollectorEnrolment::STATE_ENROLLED, 4, 'c4', 'c4');
    $this->enrolments->nodes = [
        2 => ['connected' => true, 'configured' => false],
        3 => ['connected' => false, 'configured' => false],
        // Configured by someone else already: just completed.
        4 => ['connected' => true, 'configured' => true],
    ];

    ($this->createService)()->synchronize();

    expect($this->deployed)->toBe([2])
        ->and($this->enrolments->enrolments['e2']->state)->toBe(CollectorEnrolment::STATE_COMPLETED)
        ->and($this->enrolments->enrolments['e3']->state)->toBe(CollectorEnrolment::STATE_ENROLLED)
        ->and($this->enrolments->enrolments['e4']->state)->toBe(CollectorEnrolment::STATE_COMPLETED);

    // Done once: a completed enrolment is not exported again.
    ($this->createService)()->synchronize();
    expect($this->deployed)->toBe([2]);
});

it('retries a failed first configuration at the next run', function (): void {
    $this->provisioner->pollers[] = ['id' => 2, 'name' => 'c2', 'address' => 'c2'];
    $this->enrolments->enrol('e2', CollectorEnrolment::STATE_ENROLLED, 2, 'c2', 'c2');
    $this->enrolments->nodes = [2 => ['connected' => true, 'configured' => false]];
    $this->deployOutcome = false;

    ($this->createService)()->synchronize();
    expect($this->enrolments->enrolments['e2']->state)->toBe(CollectorEnrolment::STATE_ENROLLED);

    $this->deployOutcome = true;
    ($this->createService)()->synchronize();
    expect($this->deployed)->toBe([2, 2])
        ->and($this->enrolments->enrolments['e2']->state)->toBe(CollectorEnrolment::STATE_COMPLETED);
});

it('deletes the node of a deleted poller, which revokes its credential', function (): void {
    $this->enrolments->enrol('e9', CollectorEnrolment::STATE_COMPLETED, 9, 'gone', 'gone');
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_COMPLETED, 1, 'Central', '127.0.0.1');

    ($this->createService)()->synchronize();

    expect($this->enrolments->deletedNodes)->toBe([9])
        ->and($this->enrolments->enrolments['e9']->state)->toBe(CollectorEnrolment::STATE_REVOKED);
    ($this->createService)()->synchronize();
    expect($this->enrolments->deletedNodes)->toBe([9]);
});

it('does nothing when the feature flag is off', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'web', 'web');

    expect(($this->createService)(['collector_enrolment' => 0])->synchronize())->toBe([])
        ->and($this->provisioner->created)->toBe([]);
});

it('creates a fleet token whose enrolments wait for an admin', function (): void {
    $created = ($this->createService)()->create(null, null, true, 600, null, 7, 'jdoe', true);

    expect($created->approvalRequired)->toBeTrue()
        ->and($this->enrolments->tokens[$created->id]['approvalRequired'])->toBeTrue();
    expect(fn () => ($this->createService)()->create('named-1', null, false, null, null, 7, 'jdoe', true))
        ->toThrow(CollectorEnrolmentException::class, 'approval_required');
});

it('creates no poller for an enrolment that waits for approval, then one once approved', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge-01', '10.0.0.1', true);
    $service = ($this->createService)();

    $service->synchronize();
    expect($this->provisioner->created)->toBe([])
        ->and($this->records->records)->toBe([])
        ->and($this->enrolments->enrolments['e1']->state)->toBe(CollectorEnrolment::STATE_PENDING);

    $service->approve('e1');
    // Approving again is harmless.
    $service->approve('e1');
    expect($this->enrolments->enrolments['e1']->state)->toBe(CollectorEnrolment::STATE_APPROVED);

    $service->synchronize();
    expect($this->provisioner->created)->toHaveCount(1)
        ->and($this->enrolments->enrolments['e1']->state)->toBe(CollectorEnrolment::STATE_ENROLLED)
        ->and($this->enrolments->enrolments['e1']->pollerId)->toBe(2)
        ->and($this->records->records['e1'])->toBe(['name' => 'edge-01', 'address' => '10.0.0.1', 'pollerId' => 2]);
});

it('creates no poller for a rejected enrolment, and refuses decisions on bound ones', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge-01', '10.0.0.1', true);
    $this->provisioner->pollers[] = ['id' => 2, 'name' => 'c2', 'address' => 'c2'];
    $this->enrolments->enrol('e2', CollectorEnrolment::STATE_COMPLETED, 2, 'c2', 'c2');
    $service = ($this->createService)();

    $service->reject('e1');
    $service->synchronize();
    expect($this->enrolments->enrolments['e1']->state)->toBe(CollectorEnrolment::STATE_REJECTED)
        ->and($this->provisioner->created)->toBe([]);

    expect(fn () => $service->approve('e1'))->toThrow(CollectorEnrolmentException::class, 'rejected');
    expect(fn () => $service->reject('e2'))->toThrow(CollectorEnrolmentException::class, 'completed');
    expect(fn () => $service->approve('nope'))->toThrow(CollectorEnrolmentException::class);
});

it('never creates a second poller when a run dies after creating one', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge-01', '10.0.0.1');
    $service = ($this->createService)();

    // The run dies right after the creation: nothing recorded the poller id, nothing assigned it.
    $service->synchronize(static function (int $pollerId): never {
        throw new \RuntimeException("killed after poller #{$pollerId}");
    });
    expect($this->provisioner->created)->toHaveCount(1)
        ->and($this->enrolments->assignments)->toBe([])
        ->and($this->records->records['e1']['pollerId'])->toBeNull()
        ->and($this->records->locked)->toBeFalse();

    // The next run finds the poller by the name recorded before its creation.
    $service->synchronize();
    $service->synchronize();
    expect($this->provisioner->created)->toHaveCount(1)
        ->and($this->enrolments->assignments)->toBe([['e1', 2]])
        ->and($this->records->records['e1']['pollerId'])->toBe(2)
        ->and(array_column($this->provisioner->pollers, 'name'))->toBe(['Central', 'edge-01']);
});

it('reuses the recorded poller when the run died before the assignment', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge-01', '10.0.0.1');
    // Created and recorded by a former run, renamed since by an admin.
    $this->provisioner->pollers[] = ['id' => 7, 'name' => 'renamed', 'address' => '10.0.0.1'];
    $this->records->records['e1'] = ['name' => 'edge-01', 'address' => '10.0.0.1', 'pollerId' => 7];

    ($this->createService)()->synchronize();

    expect($this->provisioner->created)->toBe([])
        ->and($this->enrolments->assignments)->toBe([['e1', 7]]);
});

it('keeps the names reserved by other enrolments whose poller is not created yet', function (): void {
    $this->records->records['e0'] = ['name' => 'edge', 'address' => 'edge', 'pollerId' => null];
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge', 'edge');

    ($this->createService)()->synchronize();

    expect($this->provisioner->created[0][0])->toBe('edge-2');
});

it('skips a run while another one holds the lock', function (): void {
    $this->enrolments->enrol('e1', CollectorEnrolment::STATE_PENDING, null, 'edge-01', '10.0.0.1');
    $this->records->locked = true;

    expect(($this->createService)()->synchronize())->toBe(['another run is in progress: skipped'])
        ->and($this->provisioner->created)->toBe([]);
});

it('lists and revokes tokens', function (): void {
    $service = ($this->createService)();
    $created = $service->create(null, null, true, 600, 3, 7, 'jdoe');

    $tokens = $service->findTokens();
    expect($tokens)->toHaveCount(1)
        ->and($tokens[0]->isFleet())->toBeTrue()
        ->and($tokens[0]->maxUses)->toBe(3);

    $service->revokeToken($created->id);
    expect($service->findTokens())->toBe([]);
    expect(fn () => $service->revokeToken($created->id))->toThrow(CollectorEnrolmentException::class, 'Unknown');
});

it('asks the collector of an existing poller to rotate its credential', function (): void {
    $service = ($this->createService)();

    $service->rotateCredential(1);
    expect($this->enrolments->rotations)->toBe([1]);
    expect(fn () => $service->rotateCredential(42))->toThrow(CollectorEnrolmentException::class, 'does not exist');
});
