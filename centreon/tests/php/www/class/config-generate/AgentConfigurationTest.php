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

use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\TinyHost;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;

beforeEach(function (): void {
    $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class);
    $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class);

    $this->agentConfiguration = new AgentConfiguration(
        $this->createMock(Backend::class),
        $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        $this->readTokenRepository,
        $this->readHostRepository,
    );

    $this->formatCmaConfiguration = fn (array $data): array => (new ReflectionMethod(
        AgentConfiguration::class,
        'formatCmaConfiguration'
    ))->invoke($this->agentConfiguration, $data, ConnectionModeEnum::SECURE);

    $this->host = static fn (int $id, string $address): array => [
        'id' => $id,
        'address' => $address,
        'port' => 4317,
        'poller_ca_certificate' => null,
        'poller_ca_name' => null,
        'token' => ['name' => 'token', 'creator_id' => 1],
    ];

    $this->data = fn (array $hosts): array => [
        'agent_initiated' => false,
        'poller_initiated' => true,
        'otel_public_certificate' => null,
        'otel_ca_certificate' => null,
        'otel_private_key' => null,
        'port' => null,
        'create_host_auto' => false,
        'tokens' => [],
        'hosts' => $hosts,
    ];
});

it('exports reverse connections as a list when a configured host no longer exists', function (): void {
    // Host 2 has been deleted but is still referenced by the agent configuration.
    $this->readHostRepository
        ->method('findByIds')
        ->willReturn([
            1 => new TinyHost(1, 'host_1', null, 1),
            3 => new TinyHost(3, 'host_3', null, 1),
        ]);

    $configuration = ($this->formatCmaConfiguration)(($this->data)([
        ($this->host)(1, '10.0.0.1'),
        ($this->host)(2, '10.0.0.2'),
        ($this->host)(3, '10.0.0.3'),
    ]));

    $reverseConnections = $configuration['centreon_agent']['reverse_connections'];
    expect($reverseConnections)->toBeList()
        ->and(array_column($reverseConnections, 'host'))->toBe(['10.0.0.1', '10.0.0.3'])
        ->and(json_encode($reverseConnections))->toStartWith('[');
});

it('exports all reverse connections when every configured host exists', function (): void {
    $this->readHostRepository
        ->method('findByIds')
        ->willReturn([
            1 => new TinyHost(1, 'host_1', null, 1),
            2 => new TinyHost(2, 'host_2', null, 1),
        ]);

    $configuration = ($this->formatCmaConfiguration)(($this->data)([
        ($this->host)(1, '10.0.0.1'),
        ($this->host)(2, '10.0.0.2'),
    ]));

    expect(array_column($configuration['centreon_agent']['reverse_connections'], 'host'))
        ->toBe(['10.0.0.1', '10.0.0.2']);
});
