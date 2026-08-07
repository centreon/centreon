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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\AgentConfiguration;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\AgentConfiguration\InstallationCommandResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class GetInstallationCommandProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/agent-configurations/installation-command';
    private const POLLER_ADDRESS = '192.168.1.100';
    private const AGENT_PORT = 4317;
    private const CERTIFICATE_SHA = 'abc123sha456def789';
    private const CERTIFICATE_CN = 'test-poller.example.com';

    public function testItGetsInstallationCommandWithCertificates(): void
    {
        $pollerId = $this->insertPoller('test-poller-with-certs');
        $this->insertAgentConfiguration($pollerId, $this->buildCmaConfig(self::AGENT_PORT));
        $this->insertInstance($pollerId, self::CERTIFICATE_SHA, self::CERTIFICATE_CN);

        $this->login();

        $response = $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(InstallationCommandResource::class);

        $version = $this->getPlatformMajorMinorVersion();
        $data = $response->toArray();
        $expectedLinux = sprintf(
            'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/scripts_linux/install_cma.sh -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "%s:%d" -f "%s" -N "%s"',
            $version,
            self::POLLER_ADDRESS,
            self::AGENT_PORT,
            self::CERTIFICATE_SHA,
            self::CERTIFICATE_CN
        );
        $expectedWindows = sprintf(
            'curl https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/install_cma.ps1 -o install_cma.ps1 ; powershell -ExecutionPolicy Bypass -File .\install_cma.ps1 -endpoint "%s:%d" -fingerprint "%s" -commonname "%s"',
            $version,
            self::POLLER_ADDRESS,
            self::AGENT_PORT,
            self::CERTIFICATE_SHA,
            self::CERTIFICATE_CN
        );
        self::assertSame($expectedLinux, $data['linux_installation_command']);
        self::assertSame($expectedWindows, $data['windows_installation_command']);
    }

    public function testItGetsInstallationCommandWithoutCertificates(): void
    {
        $pollerId = $this->insertPoller('test-poller-no-certs');
        $this->insertAgentConfiguration($pollerId, $this->buildCmaConfig(self::AGENT_PORT));
        $this->insertInstance($pollerId, null, null);

        $this->login();

        $response = $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseIsSuccessful();

        $version = $this->getPlatformMajorMinorVersion();
        $data = $response->toArray();
        $expectedLinux = sprintf(
            'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/scripts_linux/install_cma.sh -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "%s:%d"',
            $version,
            self::POLLER_ADDRESS,
            self::AGENT_PORT,
        );
        $expectedWindows = sprintf(
            'curl https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/agent/installer/install_cma.ps1 -o install_cma.ps1 ; powershell -ExecutionPolicy Bypass -File .\install_cma.ps1 -endpoint "%s:%d"',
            $version,
            self::POLLER_ADDRESS,
            self::AGENT_PORT,
        );
        self::assertSame($expectedLinux, $data['linux_installation_command']);
        self::assertSame($expectedWindows, $data['windows_installation_command']);
    }

    public function testItReturns404WhenPollerNotFound(): void
    {
        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 99999));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns404WhenAgentConfigNotFound(): void
    {
        $pollerId = $this->insertPoller('test-poller-no-ac');
        $this->insertInstance($pollerId, null, null);

        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns401WhenNotAuthenticated(): void
    {
        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 1));
        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturns403WhenUserHasNoPermission(): void
    {
        $pollerId = $this->insertPoller('test-poller-no-perm');
        $this->insertAgentConfiguration($pollerId, $this->buildCmaConfig(self::AGENT_PORT));
        $this->insertInstance($pollerId, null, null);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCmaConfig(int $port): array
    {
        return [
            'port' => $port,
            'agent_initiated' => true,
            'poller_initiated' => false,
            'otel_public_certificate' => null,
            'otel_private_key' => null,
            'otel_ca_certificate' => null,
            'tokens' => [],
            'hosts' => [],
        ];
    }

    private function getPlatformMajorMinorVersion(): string
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $version = $connection->fetchOne("SELECT `value` FROM `informations` WHERE `key` = 'version'");
        $parts = explode('.', is_string($version) ? $version : '');

        return $parts[0] . '.' . ($parts[1] ?? '0');
    }

    private function insertPoller(string $name): int
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('nagios_server', [
            'name' => $name,
            'localhost' => '0',
            'ns_ip_address' => self::POLLER_ADDRESS,
            'uid' => random_int(100000000000001, 999999999999999),
        ]);

        return (int) $connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $configData
     */
    private function insertAgentConfiguration(int $pollerId, array $configData): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('agent_configuration', [
            'name' => 'test-ac-' . $pollerId,
            'type' => 'centreon-agent',
            'connection_mode' => 'no-tls',
            'configuration' => (string) json_encode($configData),
        ]);
        $acId = (int) $connection->lastInsertId();

        $connection->insert('ac_poller_relation', [
            'ac_id' => $acId,
            'poller_id' => $pollerId,
        ]);
    }

    private function insertInstance(int $pollerId, ?string $certificateSha, ?string $certificateCn): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        // instances.instance_id holds the Snowflake UID (nagios_server.uid), not the poller config id.
        $uid = $connection->fetchOne('SELECT uid FROM nagios_server WHERE id = ?', [$pollerId]);
        $instanceId = is_numeric($uid) ? (int) $uid : 0;

        /** @var Connection $realtimeConnection */
        $realtimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $realtimeConnection->insert('instances', [
            'instance_id' => $instanceId,
            'name' => 'test-instance-' . $pollerId,
            'cma_certificate_sha' => $certificateSha,
            'cma_certificate_cn' => $certificateCn,
        ]);
    }
}
