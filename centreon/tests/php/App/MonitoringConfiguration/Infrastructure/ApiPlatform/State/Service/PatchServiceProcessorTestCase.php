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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * Fixtures of the HTTP tests of the service partial update: every row is tagged so that tests never collide, and
 * rolled back after each test.
 */
abstract class PatchServiceProcessorTestCase extends ApiTestCase
{
    protected const BASE_ENDPOINT = '/api/configuration/services';
    protected const CONTENT_TYPE = 'application/merge-patch+json';
    protected const READ_WRITE = 1;
    protected const READ_ONLY = 2;
    protected const CHECK = 2;
    protected const MISCELLANEOUS = 3;

    protected Connection $connection;

    protected Connection $realTimeConnection;

    protected string $tag;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->tag = 'svcpatch' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function patch(int|string $id, array $body): ResponseInterface
    {
        return $this->request('PATCH', self::BASE_ENDPOINT . '/' . $id, ['headers' => ['Content-Type' => self::CONTENT_TYPE], 'json' => $body]);
    }

    protected function patchRaw(int|string $id, string $body): ResponseInterface
    {
        return $this->request('PATCH', self::BASE_ENDPOINT . '/' . $id, ['headers' => ['Content-Type' => self::CONTENT_TYPE], 'body' => $body]);
    }

    protected function uniqueName(string $prefix): string
    {
        return $prefix . '-' . $this->tag . '-' . ++$this->sequence;
    }

    protected function insertPoller(): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $this->uniqueName('poller'),
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
            'updated' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertHost(int $pollerId, bool $activated = true): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->uniqueName('host'),
            'host_address' => '127.0.0.1',
            'host_activate' => $activated ? '1' : '0',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
    }

    /**
     * @param array<string, mixed> $columns
     */
    protected function insertService(string $description, int $hostId, array $columns = []): int
    {
        $this->connection->insert('service', ['service_description' => $description, 'service_register' => '1', 'service_activate' => '1', ...$columns]);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId]);

        return $serviceId;
    }

    protected function insertCommand(int $type = self::CHECK, ?string $name = null): int
    {
        $this->connection->insert('command', ['command_name' => $name ?? $this->uniqueName('command'), 'command_line' => '$USER1$/check', 'command_type' => $type]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertServiceTemplate(?int $checkCommandId = null, ?int $eventHandlerCommandId = null): int
    {
        $this->connection->insert('service', [
            'service_description' => $this->uniqueName('template'),
            'service_alias' => $this->uniqueName('template'),
            'service_register' => '0',
            'service_activate' => '1',
            'command_command_id' => $checkCommandId,
            'command_command_id2' => $eventHandlerCommandId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertServiceGroup(): int
    {
        $name = $this->uniqueName('sg');
        $this->connection->insert('servicegroup', ['sg_name' => $name, 'sg_alias' => $name, 'sg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertServiceCategory(?int $level = null): int
    {
        $name = $this->uniqueName('sc');
        $this->connection->insert('service_categories', ['sc_name' => $name, 'sc_description' => $name, 'sc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertTrap(): int
    {
        $this->connection->insert('traps_vendor', ['name' => $this->uniqueName('vendor'), 'alias' => 'vendor']);
        $vendorId = (int) $this->connection->lastInsertId();
        $this->connection->insert('traps', ['traps_name' => $this->uniqueName('trap'), 'traps_oid' => '.1.3.6.1.6.3.1.1.5.3', 'manufacturer_id' => $vendorId]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertContact(): int
    {
        $name = $this->uniqueName('contact');
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertContactGroup(): int
    {
        $name = $this->uniqueName('cg');
        $this->connection->insert('contactgroup', ['cg_name' => $name, 'cg_alias' => $name, 'cg_type' => 'local', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    protected function insertMacro(int $serviceId, string $name, string $value, bool $isPassword = false): int
    {
        $this->connection->insert('on_demand_macro_service', [
            'svc_macro_name' => '$_SERVICE' . $name . '$',
            'svc_macro_value' => $value,
            'is_password' => $isPassword ? 1 : 0,
            'svc_svc_id' => $serviceId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<int>
     */
    protected function ids(string $sql, int $parameter): array
    {
        $ids = $this->connection->fetchFirstColumn($sql, [$parameter]);
        Assert::allIntegerish($ids);
        $ids = array_map(intval(...), $ids);
        sort($ids);

        return $ids;
    }

    /**
     * @param non-empty-string $prefix
     */
    protected static function assertMessageStartsWith(string $prefix, ResponseInterface $response): void
    {
        $message = $response->toArray(false)['message'] ?? null;
        self::assertIsString($message);
        self::assertStringStartsWith($prefix, $message);
    }

    /**
     * A column as text, whatever type the driver reads it as; null stays null.
     */
    protected function text(mixed $value): ?string
    {
        Assert::nullOrScalar($value);

        return $value === null ? null : (string) $value;
    }

    protected function column(string $column, int $serviceId): mixed
    {
        return $this->connection->fetchOne("SELECT {$column} FROM service WHERE service_id = ?", [$serviceId]);
    }

    /**
     * The activity-log lines of the service, which live in the real-time database, oldest first.
     *
     * @return list<array{action_type: mixed, object_name: mixed, details: array<mixed>}>
     */
    protected function activityLogs(int $serviceId): array
    {
        $logs = $this->realTimeConnection->fetchAllAssociative(
            "SELECT action_log_id, action_type, object_name FROM log_action WHERE object_type = 'service' AND object_id = ? ORDER BY action_log_id",
            [$serviceId],
        );

        return array_map(fn (array $log): array => [
            'action_type' => $log['action_type'],
            'object_name' => $log['object_name'],
            'details' => $this->realTimeConnection->fetchAllKeyValue(
                'SELECT field_name, field_value FROM log_action_modification WHERE action_log_id = ? ORDER BY field_name',
                [$log['action_log_id']],
            ),
        ], $logs);
    }

    protected function pollerUpdated(int $pollerId): mixed
    {
        return $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
    }

    protected function insertUnchangedAclResource(): int
    {
        $this->connection->insert('acl_resources', ['acl_res_name' => $this->uniqueName('res'), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    protected function aclResourceChanged(int $aclResourceId): int
    {
        $changed = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResourceId]);
        Assert::integerish($changed);

        return (int) $changed;
    }

    protected function realTimeDatabaseName(): string
    {
        $name = $this->realTimeConnection->getDatabase();
        Assert::stringNotEmpty($name);

        return $name;
    }

    /**
     * Grants the (host, service) pair to the access group in the real-time ACL cache, through the main connection:
     * the lookup reads centreon_acl cross-database, inside the test transaction.
     */
    protected function grantPair(int $groupId, int $hostId, int $serviceId): void
    {
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (?, ?, ?)',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName()),
            ),
            [$groupId, $hostId, $serviceId],
        );
    }

    /**
     * @return list<int> the access groups granted the (host, service) pair in the real-time ACL cache
     */
    protected function groupsGrantedThePair(int $hostId, int $serviceId): array
    {
        $ids = $this->realTimeConnection->fetchFirstColumn(
            'SELECT group_id FROM centreon_acl WHERE host_id = ? AND service_id = ?',
            [$hostId, $serviceId],
        );
        Assert::allIntegerish($ids);

        return array_map(intval(...), $ids);
    }

    /**
     * Logs in a non-admin with "Configuration > Services > Services by host" at the given access right, member of
     * one access group, granted no resource yet.
     *
     * @return array{contactId: int, groupId: int}
     */
    protected function loginAsNonAdmin(int $accessRight = self::READ_WRITE): array
    {
        $username = 'service-user-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);

        $this->connection->insert('acl_groups', [
            'acl_group_name' => $this->uniqueName('acl-group'),
            'acl_group_alias' => 'service group',
            'acl_group_activate' => '1',
            'acl_group_changed' => '0',
        ]);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => (int) $contactId]);

        $this->connection->insert('acl_topology', ['acl_topo_name' => $this->uniqueName('acl-rule'), 'acl_topo_alias' => 'rule', 'acl_topo_activate' => '1']);

        $topologyRuleId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $groupId, 'acl_topology_id' => $topologyRuleId]);
        foreach ([6, 602, 60201] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$topologyPage]);
            Assert::integerish($topologyId, "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => $accessRight,
            ]);
        }

        $this->login($username);

        return ['contactId' => (int) $contactId, 'groupId' => $groupId];
    }

    /**
     * An active ACL resource of the access group, granting the given service groups and categories.
     *
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     */
    protected function grantResources(int $groupId, array $serviceGroupIds = [], array $categoryIds = []): void
    {
        $this->connection->insert('acl_resources', ['acl_res_name' => $this->uniqueName('res'), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $resourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $resourceId, 'acl_group_id' => $groupId]);
        foreach ($serviceGroupIds as $serviceGroupId) {
            $this->connection->insert('acl_resources_sg_relations', ['acl_res_id' => $resourceId, 'sg_id' => $serviceGroupId]);
        }
        foreach ($categoryIds as $categoryId) {
            $this->connection->insert('acl_resources_sc_relations', ['acl_res_id' => $resourceId, 'sc_id' => $categoryId]);
        }
    }
}
