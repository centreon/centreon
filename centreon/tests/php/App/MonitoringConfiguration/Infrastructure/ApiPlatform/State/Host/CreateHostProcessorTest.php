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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class CreateHostProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('POST', self::BASE_ENDPOINT, ['json' => []]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '127.0.0.1',
                'poller_id' => $pollerId,
            ],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItCreatesAHostWithItsPollerAndHostGroups(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Linux servers');
        $name = $this->uniqueName('server');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.1',
                'poller_id' => $pollerId,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'name' => $name,
            'address' => '10.0.0.1',
            'activated' => true,
            'poller' => ['id' => $pollerId, 'name' => 'Central'],
            'templates' => [],
            'groups' => [
                ['id' => $groupId, 'name' => 'Linux servers'],
            ],
        ]);

        /** @var HostRepository $repository */
        $repository = self::getContainer()->get(HostRepository::class);
        self::assertTrue($repository->isNameUsedByHostOrTemplate(new HostName($name)));
    }

    public function testItNormalizesSpacesInTheName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '  my host  ',
                'address' => '10.0.0.2',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['name' => 'my_host']);
    }

    public function testItRejectsAModulePrefixedName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '_Module_Foo',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The reserved-prefix check must apply to the same normalized value HostName ends up
     * persisting: leading whitespace is trimmed away before the prefix ever gets a chance to
     * "hide" behind it.
     */
    public function testItRejectsAModulePrefixedNameWithLeadingWhitespace(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '  _Module_Foo',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsADuplicateName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('dup');
        $this->insertHost($name, $pollerId);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.4',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testItRejectsAnUnknownPoller(): void
    {
        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.5',
                'poller_id' => 999999,
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnknownHostGroup(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.6',
                'poller_id' => $pollerId,
                'host_group_ids' => [999999],
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '',
                'address' => '10.0.0.7',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAMalformedAddress(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => 'http://10.0.0.8',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * End-to-end check that the ReloadAclEventHandler chain actually fires on a real request —
     * ReloadAclEventHandlerTest already covers its internal branching in isolation with fakes,
     * this only proves the wiring (event fired by the handler, listener registered, real ACL
     * tables touched) is genuinely connected for a non-admin creator.
     */
    public function testItGrantsRealTimeAclAccessAndFlagsTheCreatorsGroupForANonAdminCreator(): void
    {
        $pollerId = $this->insertPoller('Central');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->connection->update('acl_groups', ['acl_group_changed' => 0], ['acl_group_id' => $aclGroupId]);

        $this->login($username);

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        /** @var int $hostId */
        $hostId = $response->toArray()['id'];

        /** @var array{group_id: int|string, service_id: int|string|null}|false $aclRow */
        $aclRow = $this->realTimeConnection->fetchAssociative(
            'SELECT group_id, service_id FROM centreon_acl WHERE host_id = ?',
            [$hostId],
        );
        self::assertNotFalse($aclRow, "Expected a centreon_acl row to have been seeded for the creator's group");
        self::assertSame($aclGroupId, (int) $aclRow['group_id']);
        self::assertNull($aclRow['service_id']);

        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$aclGroupId]);
        self::assertSame(1, (int) $changedFlag);
    }

    public function testItFlagsAllAclResourcesForAnAdminCreator(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . bin2hex(random_bytes(4)), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.10',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResId]);
        self::assertSame(1, (int) $changedFlag);
    }

    /**
     * End-to-end check that FlagPollerChangedEventHandler actually fires on a real request —
     * FlagPollerChangedEventHandlerTest already covers its branching in isolation with fakes,
     * this only proves the wiring (event fired by the handler, listener registered, the real
     * poller row touched) is genuinely connected.
     */
    public function testItFlagsThePollerAsChangedWhenCreatingAHost(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);
        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.14',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $updatedFlag = $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
        self::assertSame('1', $updatedFlag);
    }

    /**
     * A restricted (non-admin) creator referencing a poller outside their own ACL scope gets
     * the same not-found error as a truly nonexistent poller — matching CreateHostCommandHandlerTest's
     * unit coverage of the same rule, exercised here through the real ACL tables end to end.
     */
    public function testARestrictedCreatorCannotReferenceAnInaccessiblePoller(): void
    {
        $accessiblePollerId = $this->insertPoller('Accessible');
        $inaccessiblePollerId = $this->insertPoller('Inaccessible');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$accessiblePollerId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.14',
                'poller_id' => $inaccessiblePollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testARestrictedCreatorCannotReferenceAnInaccessibleHostGroup(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleGroupId = $this->insertHostGroup('Accessible group');
        $inaccessibleGroupId = $this->insertHostGroup('Inaccessible group');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$pollerId]);
        $this->restrictContactToHostGroups($contactId, [$accessibleGroupId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.15',
                'poller_id' => $pollerId,
                'host_group_ids' => [$inaccessibleGroupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The positive counterpart to the two rejection tests above: a restricted creator referencing
     * only what they can actually see still succeeds.
     */
    public function testARestrictedCreatorCanCreateAHostWithinTheirAccessibleScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Accessible group');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$pollerId]);
        $this->restrictContactToHostGroups($contactId, [$groupId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.16',
                'poller_id' => $pollerId,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * The Cloud-mandatory-host-groups rule itself lives in the WhenPlatform validation
     * constraint on CreateHostInput::$hostGroupIds (see Shared\Infrastructure\Validator\Constraints\WhenPlatformTest for the
     * Cloud/on-premise branch coverage). WhenPlatformValidator is resolved through Symfony's
     * validator constraint locator, which is compiled once at container build time and cannot be
     * forced to a different IS_CLOUD_PLATFORM value per test — so this suite only exercises the
     * on-premise behavior, which matches this test environment's real, unforced default.
     */
    public function testItAllowsEmptyHostGroupsOnPremise(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.13',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
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

        return $hostId;
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        /** @var int|string $contactId */
        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );

        return (int) $contactId;
    }

    /**
     * Grants the "Configuration > Hosts > Hosts" read-write topology access — the legacy menu
     * role bridged to HostPermissionEnum::CanReadAndWrite via
     * DbalCredentialTransformer::LEGACY_PERMISSION_MAP (topology_page 60101). access_right = 1
     * is read-write (2 would be read-only), see DbalCredentialRepository::MENU_ACCESS_READ_WRITE.
     */
    private function grantHostReadAndWriteTopologyRole(int $contactId): int
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'topology-rw-group-' . $contactId,
            'acl_group_alias' => 'topology-rw-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'topology-rw-rule-' . $contactId,
            'acl_topo_alias' => 'topology-rw-rule-' . $contactId,
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach ([6, 601, 60101] as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            self::assertIsScalar($topologyId, "topology_page {$topologyPage} not found in fixtures");

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 1, // read-write
            ]);
        }

        return $aclGroupId;
    }

    /**
     * Restricts the contact's resource access to exactly these pollers (via a dedicated ACL
     * resource, separate from the topology-only group), mirroring
     * DbalResourceAccessRepositoryTest::linkContactToAclResourceForPollers().
     *
     * @param list<int> $pollerIds
     */
    private function restrictContactToPollers(int $contactId, array $pollerIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'resource-poller-group-' . $contactId,
            'acl_group_alias' => 'resource-poller-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-poller-' . $contactId,
            'acl_res_alias' => 'resource-poller-' . $contactId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($pollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }
    }

    /**
     * Restricts the contact's resource access to exactly these host groups (no `all_hostgroups`
     * flag), mirroring DbalResourceAccessRepositoryTest::linkContactToAclResourceForHostGroups().
     *
     * @param list<int> $hostGroupIds
     */
    private function restrictContactToHostGroups(int $contactId, array $hostGroupIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'resource-hg-group-' . $contactId,
            'acl_group_alias' => 'resource-hg-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-hg-' . $contactId,
            'acl_res_alias' => 'resource-hg-' . $contactId,
            'acl_res_activate' => '1',
            'all_hostgroups' => '0',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($hostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', [
                'acl_res_id' => $aclResId,
                'hg_hg_id' => $hostGroupId,
            ]);
        }
    }
}
