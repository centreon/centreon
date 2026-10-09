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

namespace Tests\www\class\ConfigGenerate;

use AgentConfiguration;
use Backend;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\TinyHost;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AgentConfigurationTest extends TestCase
{
    private ReadHostRepositoryInterface&MockObject $readHostRepository;

    private AgentConfiguration $agentConfiguration;

    protected function setUp(): void
    {
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class);

        $this->agentConfiguration = new AgentConfiguration(
            $this->createMock(Backend::class),
            $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
            $this->createMock(ReadTokenRepositoryInterface::class),
            $this->readHostRepository,
        );
    }

    public function testReverseConnectionsAreExportedAsListWhenConfiguredHostNoLongerExists(): void
    {
        // Host 2 has been deleted but is still referenced by the agent configuration.
        $this->readHostRepository
            ->method('findByIds')
            ->willReturn([
                1 => new TinyHost(1, 'host_1', null, 1),
                3 => new TinyHost(3, 'host_3', null, 1),
            ]);

        $reverseConnections = $this->formatReverseConnections([
            $this->host(1, '10.0.0.1'),
            $this->host(2, '10.0.0.2'),
            $this->host(3, '10.0.0.3'),
        ]);

        $this->assertTrue(array_is_list($reverseConnections));
        $this->assertSame(['10.0.0.1', '10.0.0.3'], array_column($reverseConnections, 'host'));
        $this->assertStringStartsWith('[', (string) json_encode($reverseConnections));
    }

    public function testAllReverseConnectionsAreExportedWhenEveryConfiguredHostExists(): void
    {
        $this->readHostRepository
            ->method('findByIds')
            ->willReturn([
                1 => new TinyHost(1, 'host_1', null, 1),
                2 => new TinyHost(2, 'host_2', null, 1),
            ]);

        $reverseConnections = $this->formatReverseConnections([
            $this->host(1, '10.0.0.1'),
            $this->host(2, '10.0.0.2'),
        ]);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], array_column($reverseConnections, 'host'));
    }

    /**
     * @param list<array<string, mixed>> $hosts
     *
     * @return array<mixed>
     */
    private function formatReverseConnections(array $hosts): array
    {
        $data = [
            'is_reverse' => true,
            'otel_public_certificate' => null,
            'otel_private_key' => null,
            'otel_ca_certificate' => null,
            'tokens' => [],
            'hosts' => $hosts,
        ];

        /** @var array{centreon_agent: array{reverse_connections: array<mixed>}} $configuration */
        $configuration = (new ReflectionMethod(AgentConfiguration::class, 'formatCmaConfiguration'))
            ->invoke($this->agentConfiguration, $data, ConnectionModeEnum::SECURE);

        return $configuration['centreon_agent']['reverse_connections'];
    }

    /**
     * @return array<string, mixed>
     */
    private function host(int $id, string $address): array
    {
        return [
            'id' => $id,
            'address' => $address,
            'port' => 4317,
            'poller_ca_certificate' => null,
            'poller_ca_name' => null,
            'token' => ['name' => 'token', 'creator_id' => 1],
        ];
    }
}
