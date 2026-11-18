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
use Core\Gorgone\Application\Repository\CollectorEnrolmentRepositoryInterface;
use Core\Gorgone\Application\Repository\CollectorProvisionerInterface;
use Core\Gorgone\Application\Service\CollectorEnrolmentService;
use Core\Gorgone\Domain\Model\CollectorEnrolment;

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

    public function addToken(
        string $id,
        string $tokenHash,
        ?int $pollerId,
        ?string $name,
        int $expiresInSeconds,
        ?int $maxUses,
    ): void {
        $this->tokens[$id] = compact('tokenHash', 'pollerId', 'name', 'expiresInSeconds', 'maxUses');
    }

    public function findEnrolments(): array
    {
        return array_values($this->enrolments);
    }

    public function assign(string $enrolmentId, int $pollerId, string $name): void
    {
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

    public function enrol(string $id, string $state, ?int $pollerId, string $name, string $address): void
    {
        $this->enrolments[$id] = new CollectorEnrolment($id, $state, $pollerId, $name, $address, '0.1.0');
    }

    private function set(string $id, string $state, ?int $pollerId = null, ?string $name = null): void
    {
        $old = $this->enrolments[$id];
        $this->enrol($id, $state, $pollerId ?? $old->pollerId, $name ?? $old->name, $old->address);
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
        ->and($done)->toHaveCount(2)
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
