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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class DeleteHostProcessorTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('DELETE', '/api/configuration/hosts/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $hostId = $this->insertHost('server-01', $this->insertPoller('Central'));

        $this->request('DELETE', '/api/configuration/hosts/' . $hostId);

        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturns404ForANonExistentHost(): void
    {
        $this->login();

        $this->request('DELETE', '/api/configuration/hosts/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            'message' => 'One or more hosts do not exist.',
        ]);
    }

    public function testItDeletesAnExistingHost(): void
    {
        $hostId = $this->insertHost('server-01', $this->insertPoller('Central'));

        $this->login();

        $this->request('DELETE', '/api/configuration/hosts/' . $hostId);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->connection->fetchOne(
            'SELECT host_id FROM host WHERE host_id = :id',
            ['id' => $hostId],
        ));
    }

    public function testItCascadeDeletesAServiceExclusivelyLinkedToTheHost(): void
    {
        $hostId = $this->insertHost('server-01', $this->insertPoller('Central'));
        $serviceId = $this->insertService('ping', $hostId);

        $this->login();

        $this->request('DELETE', '/api/configuration/hosts/' . $hostId);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->connection->fetchOne(
            'SELECT service_id FROM service WHERE service_id = :id',
            ['id' => $serviceId],
        ));
    }

    public function testItLeavesAServiceSharedWithAnotherHostUntouched(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost('server-01', $pollerId);
        $otherHostId = $this->insertHost('server-02', $pollerId);
        $serviceId = $this->insertService('shared', $hostId);
        $this->connection->insert('host_service_relation', [
            'host_host_id' => $otherHostId,
            'service_service_id' => $serviceId,
        ]);

        $this->login();

        $this->request('DELETE', '/api/configuration/hosts/' . $hostId);

        self::assertResponseIsSuccessful();
        self::assertNotFalse($this->connection->fetchOne(
            'SELECT service_id FROM service WHERE service_id = :id',
            ['id' => $serviceId],
        ));
    }

    private function insertPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function insertService(string $description, int $hostId): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => '1',
        ]);
        $serviceId = (int) $this->connection->lastInsertId();

        $this->connection->insert('host_service_relation', [
            'host_host_id' => $hostId,
            'service_service_id' => $serviceId,
        ]);

        return $serviceId;
    }
}
