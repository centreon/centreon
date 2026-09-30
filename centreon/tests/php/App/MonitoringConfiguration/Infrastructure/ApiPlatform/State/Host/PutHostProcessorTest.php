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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class PutHostProcessorTest extends ApiTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The activity log (log_action) lives in centstorage, on the realtime connection.
        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('PUT', $this->endpoint(1), ['json' => []]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($this->uniqueName('server'), $pollerId),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testItUpdatesAHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Linux servers');
        $hostId = $this->insertHost($this->uniqueName('server-old'), $pollerId);
        $newName = $this->uniqueName('server-new');

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $newName,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'id' => $hostId,
            'name' => $newName,
            'address' => '10.0.0.9',
            'activated' => true,
            'poller' => ['id' => $pollerId, 'name' => 'Central'],
            'groups' => [
                ['id' => $groupId, 'name' => 'Linux servers'],
            ],
        ]);

        self::assertSame(
            $newName,
            $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]),
        );
    }

    public function testItReturns404ForAnUnknownHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('PUT', $this->endpoint(999999), [
            'json' => $this->payload($this->uniqueName('server'), $pollerId),
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns409WhenTheNameIsUsedByAnotherHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $otherName = $this->uniqueName('other');
        $this->insertHost($otherName, $pollerId);
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($otherName, $pollerId),
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testItAllowsAHostToKeepItsOwnName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($name, $pollerId),
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testItReturns422ForABlankName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => '   ',
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItChangesTheActivationState(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['activated' => false]);
        self::assertSame(
            '0',
            $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]),
        );
    }

    public function testItFlagsBothPollersWhenThePollerChanges(): void
    {
        $this->login();
        $oldPollerId = $this->insertPoller('poller-old');
        $newPollerId = $this->insertPoller('poller-new');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $oldPollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $oldPollerId]);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $newPollerId]);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($name, $newPollerId),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$newPollerId]));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$oldPollerId]));
    }

    public function testItWritesExactlyOneActivityLogLine(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        // Exactly one line for the whole update (not the legacy up-to-3), of the "change" type.
        /** @var int|string $logCount */
        $logCount = $this->realTimeConnection->fetchOne(
            "SELECT COUNT(*) FROM log_action WHERE object_id = ? AND object_type = 'host'",
            [$hostId],
        );
        self::assertSame(1, (int) $logCount);
        self::assertSame(
            'c',
            $this->realTimeConnection->fetchOne(
                "SELECT action_type FROM log_action WHERE object_id = ? AND object_type = 'host'",
                [$hostId],
            ),
        );
    }

    public function testItRejectsACircularRelation(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('edited');
        $editedId = $this->insertHost($name, $pollerId);
        $childId = $this->insertHost($this->uniqueName('child'), $pollerId);
        $parentId = $this->insertHost($this->uniqueName('parent'), $pollerId);

        // Existing chain edited -> child -> parent. Setting parent as the edited host's parent while
        // keeping child as its child closes the loop edited -> child -> parent -> edited.
        $this->insertParentRelation(childId: $childId, parentId: $editedId);
        $this->insertParentRelation(childId: $parentId, parentId: $childId);

        $this->request('PUT', $this->endpoint($editedId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
                'parent_host_ids' => [$parentId],
                'child_host_ids' => [$childId],
            ],
        ]);

        // The circular-relation conflict names an input field (parent_host_ids), so the
        // InvalidReferenceExceptionListener surfaces it as a 422 validation error, like the create path.
        self::assertResponseStatusCodeSame(422);
    }

    private function insertParentRelation(int $childId, int $parentId): void
    {
        $this->connection->insert('host_hostparent_relation', [
            'host_host_id' => $childId,
            'host_parent_hp_id' => $parentId,
        ]);
    }

    private function endpoint(int $id): string
    {
        return '/api/configuration/hosts/' . $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $name, int $pollerId): array
    {
        return [
            'name' => $name,
            'address' => '10.0.0.9',
            'poller_id' => $pollerId,
            'activated' => true,
        ];
    }

    private function uniqueName(string $prefix = 'host'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
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

    private function insertHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

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

        // Every registered host has a companion row in practice; update() UPDATEs it, so mirror that.
        $this->connection->insert('extended_host_information', ['host_host_id' => $hostId]);

        return $hostId;
    }
}
