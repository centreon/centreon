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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
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

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function nullOnAFieldThatCannotBeNull(): iterable
    {
        yield 'check options arguments' => [['check_options' => ['args' => null]]];

        yield 'event handler arguments' => [['data_processing' => ['event_handler_args' => null]]];

        yield 'notification options' => [['notifications' => ['options' => null]]];

        yield 'contact additive inheritance' => [['notifications' => ['contact_additive_inheritance' => null]]];

        yield 'contact group additive inheritance' => [['notifications' => ['contact_group_additive_inheritance' => null]]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('nullOnAFieldThatCannotBeNull')]
    public function testANullInsideASubObjectIsRefusedWithTheSameStatusAsAtTheRoot(array $body): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame(422);
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

    public function testActivatingWithAnotherChangeLogsOneMassChangeLine(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false, 'alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('0', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]));
        self::assertSame('mc', $this->latestActionType($hostId));
        self::assertSame([1], $this->actionLineCounts($hostId));
    }

    public function testActivationIsLoggedAsEnableOrDisableWhenTheOtherKeysChangeNothing(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => false, 'name' => $name]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('disable', $this->latestActionType($hostId));
        self::assertSame([1], $this->actionLineCounts($hostId));
    }

    public function testAnotherChangeWithoutActivationLogsOneMassChangeLine(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => ['activated' => true, 'alias' => 'front']]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('mc', $this->latestActionType($hostId));
        self::assertSame([1], $this->actionLineCounts($hostId));
    }

    public function testTheTextsAreSetReplacedAndCleared(): void
    {
        $this->login();
        $hostId = $this->newHost();

        $this->assertTheHostFollowsTheRequests($hostId, [
            [
                [
                    'alias' => 'front',
                    'snmp_community' => 'public',
                    'extended_informations' => ['note_url' => 'https://notes.example/n', 'note' => 'a note', 'action_url' => 'https://actions.example/a', 'alt_icon' => 'an icon', 'comment' => 'a comment', 'geo_coordinates' => '48.85,2.35'],
                ],
                ['front', 'public', 'https://notes.example/n', 'a note', 'https://actions.example/a', 'an icon', 'a comment', '48.85', '2.35'],
            ],
            [
                [
                    'alias' => 'back',
                    'snmp_community' => 'private',
                    'extended_informations' => ['note_url' => 'https://notes.example/m', 'note' => 'another note', 'action_url' => 'https://actions.example/b', 'alt_icon' => 'another icon', 'comment' => 'another comment', 'geo_coordinates' => '10.5,-20.5'],
                ],
                ['back', 'private', 'https://notes.example/m', 'another note', 'https://actions.example/b', 'another icon', 'another comment', '10.5', '-20.5'],
            ],
            [
                [
                    'alias' => null,
                    'snmp_community' => null,
                    'extended_informations' => ['note_url' => null, 'note' => null, 'action_url' => null, 'alt_icon' => null, 'comment' => null, 'geo_coordinates' => null],
                ],
                [null, null, null, null, null, null, null, null, null],
            ],
        ], static fn (Host $host): array => [
            $host->alias?->value,
            $host->snmpCommunity?->value,
            $host->extendedInformations?->noteUrl,
            $host->extendedInformations?->note,
            $host->extendedInformations?->actionUrl,
            $host->extendedInformations?->altIcon,
            $host->extendedInformations?->comment,
            $host->extendedInformations?->geoCoordinates?->latitude,
            $host->extendedInformations?->geoCoordinates?->longitude,
        ]);
    }

    public function testTheNumbersAreSetReplacedAndCleared(): void
    {
        $this->login();
        $hostId = $this->newHost();

        $this->assertTheHostFollowsTheRequests($hostId, [
            [
                [
                    'scheduling_options' => ['max_check_attempts' => 3, 'normal_check_interval' => 5, 'retry_check_interval' => 1],
                    'data_processing' => ['acknowledgment_timeout' => 30, 'freshness_threshold' => 60, 'low_flap_threshold' => 10, 'high_flap_threshold' => 40],
                    'notifications' => ['interval' => 10, 'first_delay' => 2, 'recovery_delay' => 3],
                ],
                [3, 5, 1, 30, 60, 10, 40, 10, 2, 3],
            ],
            [
                [
                    'scheduling_options' => ['max_check_attempts' => 4, 'normal_check_interval' => 6, 'retry_check_interval' => 2],
                    'data_processing' => ['acknowledgment_timeout' => 31, 'freshness_threshold' => 61, 'low_flap_threshold' => 11, 'high_flap_threshold' => 41],
                    'notifications' => ['interval' => 11, 'first_delay' => 3, 'recovery_delay' => 4],
                ],
                [4, 6, 2, 31, 61, 11, 41, 11, 3, 4],
            ],
            [
                [
                    'scheduling_options' => ['max_check_attempts' => null, 'normal_check_interval' => null, 'retry_check_interval' => null],
                    'data_processing' => ['acknowledgment_timeout' => null, 'freshness_threshold' => null, 'low_flap_threshold' => null, 'high_flap_threshold' => null],
                    'notifications' => ['interval' => null, 'first_delay' => null, 'recovery_delay' => null],
                ],
                [null, null, null, null, null, null, null, null, null, null],
            ],
        ], static fn (Host $host): array => [
            $host->schedulingOptions->maxCheckAttempts,
            $host->schedulingOptions->normalCheckInterval,
            $host->schedulingOptions->retryCheckInterval,
            $host->dataProcessing->acknowledgmentTimeout,
            $host->dataProcessing->freshnessThreshold,
            $host->dataProcessing->lowFlapThreshold,
            $host->dataProcessing->highFlapThreshold,
            $host->notifications?->interval,
            $host->notifications?->firstDelay,
            $host->notifications?->recoveryDelay,
        ]);
    }

    public function testTheReferencesAreSetReplacedAndCleared(): void
    {
        $this->login();
        $pollerA = $this->insertPoller($this->uniqueName('poller'));
        $pollerB = $this->insertPoller($this->uniqueName('poller'));
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerA);
        /** @var list<int|string> $timezoneIds */
        $timezoneIds = $this->connection->fetchFirstColumn('SELECT timezone_id FROM timezone ORDER BY timezone_id LIMIT 2');
        $timezones = array_map(static fn (int|string $id): int => (int) $id, $timezoneIds);
        $severities = [$this->insertSeverity(1), $this->insertSeverity(2)];
        $icons = [$this->insertMedia(), $this->insertMedia()];
        $periods = [$this->insertTimePeriod(), $this->insertTimePeriod()];
        $checkCommands = [$this->insertCommand(2), $this->insertCommand(2)];
        $eventCommands = [$this->insertCommand(3), $this->insertCommand(3)];
        $request = static fn (int $poller, int $index): array => [
            'poller_id' => $poller,
            'timezone_id' => $timezones[$index],
            'severity_id' => $severities[$index],
            'extended_informations' => ['icon_id' => $icons[$index]],
            'scheduling_options' => ['check_timeperiod_id' => $periods[$index]],
            'notifications' => ['timeperiod_id' => $periods[$index]],
            'data_processing' => ['event_handler_command_id' => $eventCommands[$index]],
            'check_options' => ['command_id' => $checkCommands[$index]],
        ];

        $this->assertTheHostFollowsTheRequests($hostId, [
            [$request($pollerA, 0), [$pollerA, $timezones[0], $severities[0], $icons[0], $periods[0], $periods[0], $eventCommands[0], $checkCommands[0]]],
            [$request($pollerB, 1), [$pollerB, $timezones[1], $severities[1], $icons[1], $periods[1], $periods[1], $eventCommands[1], $checkCommands[1]]],
            [
                [
                    'timezone_id' => null,
                    'severity_id' => null,
                    'extended_informations' => ['icon_id' => null],
                    'scheduling_options' => ['check_timeperiod_id' => null],
                    'notifications' => ['timeperiod_id' => null],
                    'data_processing' => ['event_handler_command_id' => null],
                    'check_options' => ['command_id' => null],
                ],
                [$pollerB, null, null, null, null, null, null, null],
            ],
        ], static fn (Host $host): array => [
            $host->pollerId->value,
            $host->timezoneId?->value,
            $host->severityId?->value,
            $host->extendedInformations?->iconId?->value,
            $host->schedulingOptions->checkTimeperiodId?->value,
            $host->notifications?->periodId?->value,
            $host->dataProcessing->eventHandlerCommandId?->value,
            $host->checkOptions->checkCommandId?->value,
        ]);
    }

    public function testTheTriStatesTakeEachOfTheirValues(): void
    {
        $this->login();
        $hostId = $this->newHost();
        $request = static fn (?string $value): array => [
            'data_processing' => ['check_freshness' => $value, 'flap_detection_enabled' => $value, 'event_handler_enabled' => $value],
            'scheduling_options' => ['active_check_enabled' => $value, 'passive_check_enabled' => $value],
            'notifications' => ['enabled' => $value],
        ];

        $this->assertTheHostFollowsTheRequests($hostId, [
            [$request('true'), array_fill(0, 6, 'true')],
            [$request('false'), array_fill(0, 6, 'false')],
            [$request('use_default'), array_fill(0, 6, 'use_default')],
            [$request('true'), array_fill(0, 6, 'true')],
            // A null is "use the default" too.
            [$request(null), array_fill(0, 6, 'use_default')],
        ], static fn (Host $host): array => [
            $host->dataProcessing->checkFreshness->value,
            $host->dataProcessing->flapDetectionEnabled->value,
            $host->dataProcessing->eventHandlerEnabled->value,
            $host->schedulingOptions->activeCheckEnabled->value,
            $host->schedulingOptions->passiveCheckEnabled->value,
            $host->notifications?->enabled->value,
        ]);
    }

    public function testTheBooleansAndTheSnmpVersionAreSwitched(): void
    {
        $this->login();
        // The additive inheritance flags only count when the platform enables the additive inheritance mode.
        $this->connection->executeStatement('DELETE FROM options WHERE `key` = :key', ['key' => 'inheritance_mode']);
        $this->connection->executeStatement('INSERT INTO options (`key`, `value`) VALUES (:key, :value)', ['key' => 'inheritance_mode', 'value' => '1']);

        $hostId = $this->newHost();

        $this->assertTheHostFollowsTheRequests($hostId, [
            [['activated' => false, 'snmp_version' => '3', 'notifications' => ['contact_additive_inheritance' => true, 'contact_group_additive_inheritance' => true]], [false, '3', true, true]],
            [['activated' => true, 'snmp_version' => '2c', 'notifications' => ['contact_additive_inheritance' => false, 'contact_group_additive_inheritance' => false]], [true, '2c', false, false]],
            [['snmp_version' => '1'], [true, '1', false, false]],
            [['snmp_version' => null], [true, null, false, false]],
        ], static fn (Host $host): array => [
            $host->activated,
            $host->snmpVersion?->value,
            $host->notifications?->contactAdditiveInheritance,
            $host->notifications?->contactGroupAdditiveInheritance,
        ]);
    }

    public function testTheListsOfArgumentsAreSetReplacedAndEmptied(): void
    {
        $this->login();
        $hostId = $this->newHost();
        $checkCommand = $this->insertCommand(2);
        $eventCommand = $this->insertCommand(3);

        $this->assertTheHostFollowsTheRequests($hostId, [
            [['check_options' => ['command_id' => $checkCommand, 'args' => ['-w', '80']], 'data_processing' => ['event_handler_command_id' => $eventCommand, 'event_handler_args' => ['start']]], [['-w', '80'], ['start']]],
            [['check_options' => ['args' => ['-c', '90', '-v']], 'data_processing' => ['event_handler_args' => ['stop', 'now']]], [['-c', '90', '-v'], ['stop', 'now']]],
            [['check_options' => ['args' => []], 'data_processing' => ['event_handler_args' => []]], [[], []]],
        ], static fn (Host $host): array => [$host->checkOptions->args, $host->dataProcessing->eventHandlerArgs]);
    }

    #[DataProvider('requesters')]
    public function testOnlyWhoMaySeeAndChangeTheHostChangesIt(string $requester, int $status): void
    {
        $pollerId = $this->insertPoller($this->uniqueName('poller'));
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);
        $this->actAs($requester, $hostId);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => [
            'alias' => 'front',
            'scheduling_options' => ['max_check_attempts' => 3],
            'notifications' => ['first_delay' => 2],
        ]]);

        self::assertResponseStatusCodeSame($status);
        $row = $this->connection->fetchAssociative('SELECT host_alias, host_max_check_attempts, host_first_notification_delay FROM host WHERE host_id = ?', [$hostId]);
        self::assertEquals($status === 204 ? ['host_alias' => 'front', 'host_max_check_attempts' => 3, 'host_first_notification_delay' => 2] : ['host_alias' => null, 'host_max_check_attempts' => null, 'host_first_notification_delay' => null], $row);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function requesters(): iterable
    {
        yield 'an administrator' => ['administrator', 204];

        yield 'a user who has access to the host' => ['user with access to the host', 204];

        yield 'a user without any access right' => ['user without any access right', 403];

        yield 'a user who cannot see the host' => ['user who cannot see the host', 404];
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

    /**
     * @return list<mixed>
     */
    private function actionLineCounts(int $hostId): array
    {
        return $this->realTimeConnection->fetchFirstColumn(
            "SELECT COUNT(*) FROM log_action WHERE object_id = ? AND object_type = 'host'",
            [$hostId],
        );
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

    private function newHost(): int
    {
        return $this->insertHost($this->uniqueName('host'), $this->insertPoller($this->uniqueName('poller')));
    }

    /**
     * Sends each request in turn and, after each one, checks what the host holds.
     *
     * @param list<array{array<string, mixed>, list<mixed>}> $requests the body, and what $read gives afterwards
     * @param \Closure(Host): list<mixed> $read
     */
    private function assertTheHostFollowsTheRequests(int $hostId, array $requests, \Closure $read): void
    {
        foreach ($requests as $position => [$body, $expected]) {
            $this->request('PATCH', self::BASE_ENDPOINT . '/' . $hostId, self::PATCH_HEADERS + ['json' => $body]);

            self::assertResponseStatusCodeSame(204, sprintf('request %d: %s', $position + 1, json_encode($body)));
            /** @var HostRepository $repository */
            $repository = self::getContainer()->get(HostRepository::class);
            $host = $repository->findOne(new HostId($hostId));
            self::assertInstanceOf(Host::class, $host);
            self::assertSame($expected, $read($host), sprintf('what the host holds after request %d', $position + 1));
        }
    }

    private function actAs(string $requester, int $hostId): void
    {
        if ($requester === 'administrator') {
            $this->login();

            return;
        }

        $alias = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($alias);
        if ($requester !== 'user without any access right') {
            $aclGroupId = $this->grantHostReadAndWriteTopologyRole($contactId);
            if ($requester === 'user with access to the host') {
                $this->linkHostToAcl($hostId, $aclGroupId);
            }
        }

        $this->login($alias);
    }

    private function insertSeverity(int $level): int
    {
        $name = $this->uniqueName('severity');
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name, 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMedia(): int
    {
        $name = $this->uniqueName('icon') . '.png';
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imageId = (int) $this->connection->lastInsertId();

        // A media is only found through its folder.
        $this->connection->insert('view_img_dir', ['dir_name' => 'dir']);
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => (int) $this->connection->lastInsertId(), 'img_img_id' => $imageId]);

        return $imageId;
    }

    private function insertTimePeriod(): int
    {
        $name = $this->uniqueName('period');
        $this->connection->insert('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCommand(int $type): int
    {
        $this->connection->insert('command', [
            'command_name' => $this->uniqueName('command'),
            'command_line' => '$USER1$/check_ping -H $HOSTADDRESS$',
            'command_type' => $type,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
