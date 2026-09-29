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

final class DuplicateHostProcessorTest extends ApiTestCase
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
        $this->request('POST', '/api/configuration/hosts/1/_duplicate');
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('web'), $pollerId);

        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturns404WhenTheHostDoesNotExist(): void
    {
        $this->login();

        $this->request('POST', '/api/configuration/hosts/2147483000/_duplicate');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItDuplicatesAHostWithTheFirstFreeSuffix(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('web');
        $hostId = $this->insertHost($name, $pollerId);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $copyId = $this->connection->fetchOne(
            "SELECT host_id FROM host WHERE host_name = :name AND host_register = '1'",
            ['name' => $name . '_1'],
        );
        self::assertIsScalar($copyId, 'the copy is persisted under the first free "_1" suffix');
        self::assertNotSame($hostId, (int) $copyId);
    }

    public function testItFlagsTheSourcePollerAsChanged(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);
        $hostId = $this->insertHost($this->uniqueName('web'), $pollerId);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $updatedFlag = $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
        self::assertSame('1', $updatedFlag);
    }

    public function testItCopiesTheSourceAclConfigurationRelationsOntoTheCopy(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('web');
        $hostId = $this->insertHost($name, $pollerId);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'res-' . $name,
            'acl_res_alias' => 'res-' . $name,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_resources_host_relations', [
            'host_host_id' => $hostId,
            'acl_res_id' => $aclResId,
        ]);

        $this->login();

        $this->request('POST', "/api/configuration/hosts/{$hostId}/_duplicate");
        self::assertResponseStatusCodeSame(204);

        $copyId = $this->connection->fetchOne(
            "SELECT host_id FROM host WHERE host_name = :name AND host_register = '1'",
            ['name' => $name . '_1'],
        );
        self::assertIsScalar($copyId, 'the copy is persisted under the first free "_1" suffix');

        $copyRelationCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM acl_resources_host_relations WHERE host_host_id = :hostId AND acl_res_id = :aclResId',
            ['hostId' => (int) $copyId, 'aclResId' => $aclResId],
        );
        self::assertIsScalar($copyRelationCount);
        self::assertSame(1, (int) $copyRelationCount, 'the copy inherits the source ACL resource relation');
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

    private function uniqueName(string $prefix = 'host'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
    }
}
