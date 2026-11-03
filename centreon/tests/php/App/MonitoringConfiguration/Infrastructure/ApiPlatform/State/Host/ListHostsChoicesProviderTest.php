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
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/hosts. The provider rules are pinned by
 * ListHostsChoicesProviderLogicTest and the SQL and scoping by DbalHostChoiceRepositoryTest; the gate, the
 * OpenAPI absence and the routing are pinned by ServiceFormSelectorsWiringTest.
 */
final class ListHostsChoicesProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    private const ENDPOINT = '/api/configuration/services/hosts';

    private Connection $connection;

    private Connection $realTimeConnection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    /**
     * A service read-write user needs no host role of its own. Being restricted, it sees only the host its
     * real-time ACL grants, not the one it is not granted; the item IRI is the host's real one.
     */
    public function testItListsForAServiceReadWriteUser(): void
    {
        $name = "host-{$this->tag}";
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        // matches the name filter too, but has no real-time ACL row for the user's group
        $this->connection->insert('host', ['host_name' => "not-granted-{$this->tag}", 'host_register' => '1']);
        $username = bin2hex(random_bytes(8));
        $aclGroupId = $this->grantServiceTopologyRole(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::ACL_ACCESS_READ_WRITE,
            $this->tag,
        );
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $hostId]);
        $this->login($username);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$hostId], array_column($member, 'id'), 'The host the real-time ACL does not grant is absent.');
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
        self::assertSame("/api/configuration/hosts/{$hostId}", $member[0]['@id']);
        self::assertSame($hostId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
    }
}
