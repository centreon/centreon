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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class GetInstallationCommandProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/pollers/installation-command';
    private const POLLER_UID = 123456789012345;
    private const POLLER_NAME = 'test-poller';
    private const POLLER_TYPE = 'vm';
    private const CENTRAL_ADDRESS = '192.168.1.100';

    protected function setUp(): void
    {
        parent::setUp();
        $this->insertCentral();
    }

    public function testItReturns401WhenNotAuthenticated(): void
    {
        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 1));
        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturns400WhenPollerIdIsInvalid(): void
    {
        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 0));
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns404WhenPollerNotFound(): void
    {
        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 99999));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns403WhenUserHasNoPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, 1));
        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturns404WhenNoValidTokenExists(): void
    {
        $pollerId = $this->insertPoller(self::POLLER_NAME);
        $this->login();

        $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns200WithInstallationCommand(): void
    {
        $pollerId = $this->insertPoller(self::POLLER_NAME);
        $tokenValue = $this->insertPollerToken('test-token-default');
        $this->login();

        $response = $this->request('GET', sprintf('%s/%d', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseIsSuccessful();

        $data = $response->toArray();
        $expected = sprintf(
            'curl -fsSL http://localhost/poller/install.sh | bash -s -- --poller_token test-token-default:%s --uid %s --name %s --type %s --central_url http://localhost --appsecret test-app-secret --salt test-salt',
            $tokenValue,
            self::POLLER_UID,
            escapeshellarg(self::POLLER_NAME),
            self::POLLER_TYPE,
        );
        self::assertSame($expected, $data['installation_command']);
    }

    public function testItReturns200WithInstallationCommandForNamedToken(): void
    {
        $pollerId = $this->insertPoller(self::POLLER_NAME);
        $this->insertPollerToken('other-token');
        $namedTokenValue = $this->insertPollerToken('named-token');
        $this->login();

        $response = $this->request('GET', sprintf('%s/%d?token-name=named-token', self::BASE_ENDPOINT, $pollerId));
        self::assertResponseIsSuccessful();

        $data = $response->toArray();
        $expected = sprintf(
            'curl -fsSL http://localhost/poller/install.sh | bash -s -- --poller_token named-token:%s --uid %s --name %s --type %s --central_url http://localhost --appsecret test-app-secret --salt test-salt',
            $namedTokenValue,
            self::POLLER_UID,
            escapeshellarg(self::POLLER_NAME),
            self::POLLER_TYPE,
        );
        self::assertSame($expected, $data['installation_command']);
    }

    private function insertCentral(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('nagios_server', [
            'name' => 'Central',
            'localhost' => '1',
            'ns_ip_address' => '127.0.0.1',
            'ns_activate' => '1',
            'uid' => 100000000000001,
        ]);
        $centralServerId = (int) $connection->lastInsertId();

        $connection->insert('platform_topology', [
            'address' => self::CENTRAL_ADDRESS,
            'hostname' => 'central.example.com',
            'name' => 'Central',
            'type' => 'central',
            'parent_id' => null,
            'server_id' => $centralServerId,
            'pending' => '0',
        ]);
    }

    private function insertPoller(string $name): int
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('nagios_server', [
            'name' => $name,
            'localhost' => '0',
            'ns_ip_address' => '127.0.0.1',
            'uid' => self::POLLER_UID,
            'poller_type' => self::POLLER_TYPE,
            'ns_activate' => '1',
        ]);

        return (int) $connection->lastInsertId();
    }

    private function insertPollerToken(string $tokenName): string
    {
        $tokenValue = bin2hex(random_bytes(16));
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('authentication_tokens', [
            'token_name' => $tokenName,
            'token_string' => $tokenValue,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);

        return $tokenValue;
    }
}
