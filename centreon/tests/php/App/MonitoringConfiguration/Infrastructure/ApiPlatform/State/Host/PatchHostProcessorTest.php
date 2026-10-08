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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Shared\ApiTestCase;

final class PatchHostProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    /** @var array{headers: array{Content-Type: string}} */
    private const PATCH_HEADERS = ['headers' => ['Content-Type' => 'application/merge-patch+json']];

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
        $this->request('PATCH', self::BASE_ENDPOINT . '/1', self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForAUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturnsNotFoundForAnUnknownHost(): void
    {
        $this->login();

        $this->request('PATCH', self::BASE_ENDPOINT . '/9999999', self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItDisablesAHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('0', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        self::assertSame('disable', $this->latestActionType($hostId));
    }

    public function testItEnablesADisabledHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('host', ['host_activate' => '0'], ['host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => true]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        self::assertSame('enable', $this->latestActionType($hostId));
    }

    public function testAnEmptyBodyChangesNothing(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => []]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        self::assertSame('0', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testANullActivatedIsRejected(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => null]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItRejectsANonBooleanActivatedField(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => 'not-a-bool']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItFlagsThePollerWhenTogglingActivation(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testItFlagsAllAclResourcesForAnAdmin(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . bin2hex(random_bytes(4)), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResId]);
        self::assertSame(1, (int) $changedFlag);
    }

    public function testItIsASilentNoOpWhenAlreadyInTheRequestedState(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId); // inserted as activated
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => true]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        // Unchanged state: the poller must not be flagged for a reload it does not need.
        self::assertSame('0', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testItLetsARestrictedViewerDisableAHostInItsScope(): void
    {
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        // findOne scopes on centreon_acl: make the host visible to the viewer's access group.
        $this->linkHostToAcl($hostId, $aclGroupId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('0', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        // The audit line records the acting (restricted) user as the actor.
        self::assertEquals($contactId, $this->latestActor($hostId));
    }

    public function testItHidesAHostOutsideTheRestrictedViewerScope(): void
    {
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        // Deliberately NOT linked to the viewer's access group in centreon_acl.

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false]]);

        // Out of ACL scope reads as not found (no existence leak, not a 403), and nothing is toggled.
        self::assertResponseStatusCodeSame(404);
        self::assertSame('1', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItUpdatesOnlyTheSentFields(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_name, host_address, host_alias FROM host WHERE host_id = ?', [$hostId]);
        self::assertSame(['host_name' => $name, 'host_address' => '127.0.0.1', 'host_alias' => 'front'], $row);
        self::assertSame('mc', $this->latestActionType($hostId));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testNullClearsTheAlias(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('host', ['host_alias' => 'front'], ['host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['alias' => null]]);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testAFieldThatCannotBeNullIsRejectedWhenSentAsNull(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['name' => null]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($name, $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testASubObjectCannotBeNull(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['scheduling_options' => null]]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRefusesANameUsedByAnotherHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $takenName = $this->uniqueName('taken');
        $this->insertHost($takenName, $pollerId);
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['name' => $takenName]]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame($name, $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItAcceptsTheCurrentNameOfTheHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['name' => $name, 'alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unknownReferences(): iterable
    {
        yield 'poller' => [['poller_id' => 9999999]];

        yield 'timezone' => [['timezone_id' => 9999999]];

        yield 'severity' => [['severity_id' => 9999999]];

        yield 'check time period' => [['scheduling_options' => ['check_timeperiod_id' => 9999999]]];

        yield 'icon' => [['extended_informations' => ['icon_id' => 9999999]]];

        yield 'check command' => [['check_options' => ['command_id' => 9999999]]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('unknownReferences')]
    public function testItRejectsAReferenceToSomethingThatDoesNotExist(array $body): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => $body + ['alias' => 'front']]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testMovingAHostFlagsBothPollers(): void
    {
        $this->login();
        $oldPollerId = $this->insertPoller('Old');
        $newPollerId = $this->insertPoller('New');
        $hostId = $this->insertHost($this->uniqueName('host'), $oldPollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $oldPollerId]);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $newPollerId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['poller_id' => $newPollerId]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            [$newPollerId],
            $this->connection->fetchFirstColumn('SELECT nagios_server_id FROM ns_host_relation WHERE host_host_id = ?', [$hostId]),
        );
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$oldPollerId]));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$newPollerId]));
    }

    public function testASubObjectIsUpdatedFieldByField(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('host', ['host_check_interval' => 7, 'host_max_check_attempts' => 3], ['host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['scheduling_options' => ['max_check_attempts' => 5]]]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_max_check_attempts, host_check_interval FROM host WHERE host_id = ?', [$hostId]);
        self::assertSame(['host_max_check_attempts' => 5, 'host_check_interval' => 7], $row);
    }

    public function testNullClearsAValueInsideASubObject(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->update('host', ['host_notification_interval' => 15], ['host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['notifications' => ['interval' => null, 'first_delay' => 2]]]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_notification_interval, host_first_notification_delay FROM host WHERE host_id = ?', [$hostId]);
        self::assertSame(['host_notification_interval' => null, 'host_first_notification_delay' => 2], $row);
    }

    public function testTheExtendedInformationsAreUpdated(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->insert('extended_host_information', ['host_host_id' => $hostId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['extended_informations' => ['note' => 'hello']]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('hello', $this->connection->fetchOne('SELECT ehi_notes FROM extended_host_information WHERE host_host_id = ?', [$hostId]));
    }

    public function testArgumentsWithoutACheckCommandAreRejected(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['check_options' => ['args' => ['-w', '80']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->connection->fetchOne('SELECT command_command_id_arg1 FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testTheSnmpCommunityIsStoredAndCleared(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['snmp_community' => 'public', 'snmp_version' => '2c']]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_snmp_community, host_snmp_version FROM host WHERE host_id = ?', [$hostId]);
        self::assertSame(['host_snmp_community' => 'public', 'host_snmp_version' => '2c'], $row);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['snmp_community' => null]]);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->connection->fetchOne('SELECT host_snmp_community FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testAFieldUpdateFlagsAllAclResourcesForAnAdmin(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . bin2hex(random_bytes(4)), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResId]);
        self::assertSame(1, (int) $changedFlag);
    }

    public function testARestrictedViewerCanUpdateAHostInItsScopeAndItsAccessGroupIsFlagged(): void
    {
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->login($username);
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->linkHostToAcl($hostId, $aclGroupId);
        $this->connection->update('acl_groups', ['acl_group_changed' => 0], ['acl_group_id' => $aclGroupId]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('front', $this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$hostId]));
        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$aclGroupId]);
        self::assertSame(1, (int) $changedFlag);
        self::assertEquals($contactId, $this->latestActor($hostId));
        self::assertSame('mc', $this->latestActionType($hostId));
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
        return $prefix . '-' . bin2hex(random_bytes(6));
    }

    private function latestActionType(int $hostId): mixed
    {
        return $this->realTimeConnection->fetchOne(
            "SELECT action_type FROM log_action WHERE object_id = ? AND object_type = 'host' ORDER BY action_log_id DESC LIMIT 1",
            [$hostId],
        );
    }

    private function latestActor(int $hostId): mixed
    {
        return $this->realTimeConnection->fetchOne(
            "SELECT log_contact_id FROM log_action WHERE object_id = ? AND object_type = 'host' ORDER BY action_log_id DESC LIMIT 1",
            [$hostId],
        );
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
     * Grants the "Configuration > Hosts > Hosts" read-write topology access (topology_page 60101),
     * bridged to HostPermissionEnum::CanReadAndWrite. Returns the created access group id.
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

    private function linkHostToAcl(int $hostId, int $aclGroupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $aclGroupId,
            'host_id' => $hostId,
        ]);
    }
}
