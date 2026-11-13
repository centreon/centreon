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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * The lists and the macros of PATCH /configuration/host_templates/{id}.
 */
final class PatchHostTemplateRelationsProcessorTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    use InsertsMonitoringConnectorTemplates;
    private const URI = '/api/configuration/host_templates';
    private const MERGE_PATCH = 'application/merge-patch+json';
    private const JSON = ['headers' => ['accept' => 'application/json']];

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

        $this->tag = Uuid::v4()->toBase58();
    }

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);
        parent::tearDown();
    }

    public function testAnUnchangedSaveShapedLikeTheFormWritesLogsAndFlagsNothing(): void
    {
        $id = $this->insertFullTemplate();
        $pollerId = $this->insertPoller();
        $this->insertHostOn($id, $pollerId);
        $before = $this->snapshot($id);
        $this->login();

        $this->patch($id, $this->formPayload($id));

        self::assertResponseStatusCodeSame(204);
        self::assertSame($before, $this->snapshot($id));
        self::assertSame(0, $this->logRowCount($id));
        self::assertSame('0', $this->pollerUpdated($pollerId));
    }

    public function testAHostTemplateReadOnlyUserCannotChangeTheLists(): void
    {
        $id = $this->insertTemplate('read-only');
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $this->patch($id, ['category_ids' => [$this->insertCategory('denied')]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->ids('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ?', $id));
    }

    public function testTheParentsAreReplacedInTheOrderGiven(): void
    {
        [$first, $second] = [$this->insertTemplate('first'), $this->insertTemplate('second')];
        $id = $this->insertTemplate('child');
        $this->link($id, $first, 1);
        $this->link($id, $second, 2);
        $this->login();

        $this->patch($id, ['template_ids' => [$second, $first]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([$second, $first], $this->parentIds($id));
    }

    public function testAddedParentsGoAfterTheCurrentOnesAndRemovedOnesLeave(): void
    {
        [$first, $second, $third] = [$this->insertTemplate('first'), $this->insertTemplate('second'), $this->insertTemplate('third')];
        $id = $this->insertTemplate('child');
        $this->link($id, $first, 1);
        $this->link($id, $second, 2);
        $this->login();

        $this->patch($id, ['template_ids_to_add' => [$third, $first]]);
        self::assertSame([$first, $second, $third], $this->parentIds($id));

        $this->patch($id, ['template_ids_to_remove' => [$second]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$first, $third], $this->parentIds($id));
    }

    public function testTheOtherListsAreReplacedAddedToAndRemovedFrom(): void
    {
        $id = $this->insertTemplate('lists');
        [$categoryA, $categoryB] = [$this->insertCategory('a'), $this->insertCategory('b')];
        [$linkedA, $linkedB] = [$this->insertServiceTemplate('a'), $this->insertServiceTemplate('b')];
        [$contactA, $contactB] = [$this->insertContact('a'), $this->insertContact('b')];
        [$groupA, $groupB] = [$this->insertContactGroup('a'), $this->insertContactGroup('b')];
        $this->login();

        $this->patch($id, [
            'category_ids' => [$categoryA],
            'linked_service_template_ids' => [$linkedB, $linkedA],
            'notifications' => ['contacts' => [$contactA], 'contact_groups' => [$groupA], 'options' => ['down']],
        ]);
        self::assertResponseStatusCodeSame(204);
        $this->patch($id, [
            'category_ids_to_add' => [$categoryB],
            'linked_service_template_ids_to_remove' => [$linkedB],
            'notifications' => ['contacts_to_add' => [$contactB], 'contact_groups_to_remove' => [$groupA], 'options_to_add' => ['recovery']],
        ]);
        self::assertResponseStatusCodeSame(204);

        self::assertSame([$categoryA, $categoryB], $this->ids('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', $id));
        self::assertSame([$linkedA], $this->ids('SELECT service_service_id FROM host_service_relation WHERE host_host_id = ?', $id));
        self::assertSame([$contactA, $contactB], $this->ids('SELECT contact_id FROM contact_host_relation WHERE host_host_id = ? ORDER BY contact_id', $id));
        self::assertSame([], $this->ids('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', $id));
        self::assertSame('d,r', $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$id]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideTwoKeysOfOneList')]
    public function testTwoKeysOfOneListAreRefused(array $body, string $field): void
    {
        $id = $this->insertTemplate('two-keys');
        $this->login();

        $response = $this->patch($id, $body);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn($field, $response);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideTwoKeysOfOneList(): iterable
    {
        yield 'parents' => [['template_ids' => [], 'template_ids_to_remove' => []], 'template_ids_to_remove'];

        yield 'categories' => [['category_ids_to_add' => [], 'category_ids_to_remove' => []], 'category_ids_to_remove'];

        yield 'linked service templates' => [['linked_service_template_ids' => [], 'linked_service_template_ids_to_add' => []], 'linked_service_template_ids_to_add'];

        yield 'contacts' => [['notifications' => ['contacts' => [], 'contacts_to_add' => []]], 'notifications.contacts_to_add'];

        yield 'contact groups' => [['notifications' => ['contact_groups' => [], 'contact_groups_to_remove' => []]], 'notifications.contact_groups_to_remove'];

        yield 'options' => [['notifications' => ['options' => [], 'options_to_add' => []]], 'notifications.options_to_add'];

        yield 'macros' => [['check_options' => ['macros' => [], 'macros_to_remove' => []]], 'check_options.macros_to_remove'];
    }

    #[DataProvider('provideLists')]
    public function testANullListIsRefused(string $key): void
    {
        $id = $this->insertTemplate('null-list');
        $this->login();

        $this->patch($id, [$key => null]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLists(): iterable
    {
        foreach (['template_ids', 'category_ids', 'linked_service_template_ids'] as $key) {
            yield $key => [$key];
        }
    }

    public function testTheTemplateCannotBeItsOwnParent(): void
    {
        $id = $this->insertTemplate('self');
        $this->login();

        $response = $this->patch($id, ['template_ids' => [$id]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('template_ids', $response);
    }

    public function testADescendantCannotBecomeAParentEvenThroughAnAddition(): void
    {
        $id = $this->insertTemplate('root');
        $child = $this->insertTemplate('child');
        $grandchild = $this->insertTemplate('grandchild');
        $this->link($child, $id, 1);
        $this->link($grandchild, $child, 1);
        $this->login();

        $this->patch($id, ['template_ids' => [$grandchild]]);
        self::assertResponseStatusCodeSame(422);

        $response = $this->patch($id, ['template_ids_to_add' => [$child]]);
        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('template_ids', $response);
        self::assertSame([], $this->parentIds($id));
    }

    public function testTheLeafOfA2000LevelDescendantChainCannotBecomeAParent(): void
    {
        $id = $this->insertTemplate('chain-root');
        $leaf = $this->insertChainBelow($id, 2000);
        $this->login();

        $response = $this->patch($id, ['template_ids' => [$leaf]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('template_ids', $response);
    }

    public function testALoopStoredThroughTheTemplateRefusesASaveKeepingItsBackEdge(): void
    {
        $id = $this->insertTemplate('looped');
        $other = $this->insertTemplate('other');
        $this->link($id, $other, 1);
        $this->link($other, $id, 1);
        $this->login();

        $this->patch($id, ['template_ids' => [$other]]);
        self::assertResponseStatusCodeSame(422);

        $unrelated = $this->insertTemplate('unrelated');
        $this->patch($id, ['template_ids' => [$unrelated]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$unrelated], $this->parentIds($id));
    }

    public function testALoopStoredElsewhereDoesNotHangTheSave(): void
    {
        [$loopStart, $loopEnd] = [$this->insertTemplate('loop-a'), $this->insertTemplate('loop-b')];
        $this->link($loopStart, $loopEnd, 1);
        $this->link($loopEnd, $loopStart, 1);
        $id = $this->insertTemplate('outside');
        $this->login();

        $this->patch($id, ['template_ids' => [$loopStart]]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testATemplateWith5000ChildrenTakesAnUnrelatedParent(): void
    {
        $id = $this->insertTemplate('wide');
        for ($index = 0; $index < 5000; ++$index) {
            $this->link($this->insertTemplate("wide-child-{$index}"), $id, 1);
        }
        $parent = $this->insertTemplate('wide-parent');
        $this->login();

        $this->patch($id, ['template_ids' => [$parent]]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testATemplateAtTheBottomOfA2000LevelAncestorChainTakesAnUnrelatedParent(): void
    {
        $top = $this->insertTemplate('ancestors-top');
        $bottom = $this->insertChainBelow($top, 2000);
        $parent = $this->insertTemplate('ancestors-parent');
        $this->login();

        $this->patch($bottom, ['template_ids_to_add' => [$parent]]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testAnUnlicensedParentAddedIsRefusedAndOneAlreadySetIsKept(): void
    {
        $connector = $this->insertTemplate('connector');
        $this->insertMonitoringConnectorTemplates($this->connection, $connector);
        $id = $this->insertTemplate('licensed');
        $kept = $this->insertTemplate('kept');
        $this->login();

        $response = $this->patch($id, ['template_ids' => [$connector]]);
        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('template_ids', $response);

        $this->link($id, $connector, 1);
        $this->patch($id, ['template_ids_to_add' => [$kept]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$connector, $kept], $this->parentIds($id));

        $this->patch($id, ['template_ids_to_remove' => [$connector]]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testAnUnlicensedServiceTemplateAddedIsRefusedAndOneAlreadyLinkedIsKept(): void
    {
        $connector = $this->insertServiceTemplate('connector');
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $connector);
        $plain = $this->insertServiceTemplate('plain');
        $id = $this->insertTemplate('licensed');
        $this->login();

        $response = $this->patch($id, ['linked_service_template_ids_to_add' => [$connector]]);
        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('linked_service_template_ids', $response);

        $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $connector]);
        $this->patch($id, ['linked_service_template_ids' => [$connector, $plain]]);
        self::assertResponseStatusCodeSame(204);

        $this->patch($id, ['linked_service_template_ids_to_remove' => [$connector]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$plain], $this->ids('SELECT service_service_id FROM host_service_relation WHERE host_host_id = ?', $id));
    }

    public function testChangingTheParentsOrTheLinkedServiceTemplatesNeverTouchesTheServicesOfAHost(): void
    {
        $id = $this->insertTemplate('used');
        $parent = $this->insertTemplate('parent');
        $linked = $this->insertServiceTemplate('linked');
        $hostId = $this->insertHostOn($id, $this->insertPoller());
        $serviceId = $this->insertRow('service', ['service_description' => "service-{$this->tag}", 'service_register' => '1', 'service_template_model_stm_id' => $linked]);
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
        $hostServices = static fn (Connection $connection): array => $connection->fetchAllAssociative(
            'SELECT hsr.service_service_id, s.service_description FROM host_service_relation hsr LEFT JOIN service s ON s.service_id = hsr.service_service_id WHERE hsr.host_host_id = ? ORDER BY hsr.service_service_id',
            [$hostId],
        );
        $before = $hostServices($this->connection);
        $this->login();

        $this->patch($id, ['template_ids' => [$parent], 'linked_service_template_ids' => [$linked]]);
        self::assertResponseStatusCodeSame(204);
        $this->patch($id, ['linked_service_template_ids' => []]);
        self::assertResponseStatusCodeSame(204);

        self::assertSame($before, $hostServices($this->connection));
    }

    public function testAnUnknownContactGroupIsRefusedAndAnLdapOneAccepted(): void
    {
        $id = $this->insertTemplate('groups');
        $ldapGroup = $this->insertRow('contactgroup', ['cg_name' => "ldap-{$this->tag}", 'cg_alias' => 'ldap', 'cg_type' => 'ldap', 'cg_activate' => '1']);
        $this->login();

        $response = $this->patch($id, ['notifications' => ['contact_groups' => [2147483647]]]);
        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('notifications.contact_groups', $response);

        $this->patch($id, ['notifications' => ['contact_groups' => [$ldapGroup]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$ldapGroup], $this->ids('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', $id));
    }

    /**
     * @param \Closure(self): array<string, mixed> $body
     */
    #[DataProvider('provideUnloggedChanges')]
    public function testARelationsContactsOrMacrosOnlyChangeIsNotLoggedButFlagsThePollers(\Closure $body): void
    {
        $id = $this->insertTemplate('unlogged');
        $child = $this->insertTemplate('unlogged-child');
        $this->link($child, $id, 1);
        [$directPoller, $indirectPoller] = [$this->insertPoller(), $this->insertPoller()];
        $this->insertHostOn($id, $directPoller);
        $this->insertHostOn($child, $indirectPoller);
        $this->login();

        $this->patch($id, $body($this));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->logRowCount($id));
        self::assertSame(['1', '1'], [$this->pollerUpdated($directPoller), $this->pollerUpdated($indirectPoller)]);
    }

    /**
     * @return iterable<string, array{\Closure(self): array<string, mixed>}>
     */
    public static function provideUnloggedChanges(): iterable
    {
        yield 'parents' => [static fn (self $test): array => ['template_ids' => [$test->insertTemplate('new-parent')]]];

        yield 'categories' => [static fn (self $test): array => ['category_ids' => [$test->insertCategory('new')]]];

        yield 'contacts' => [static fn (self $test): array => ['notifications' => ['contacts' => [$test->insertContact('new')]]]];

        yield 'macros' => [static fn (self $test): array => ['check_options' => ['macros_to_upsert' => [['name' => 'new', 'value' => 'x']]]]];
    }

    public function testAnOptionsChangeIsLogged(): void
    {
        $id = $this->insertTemplate('options');
        $this->login();

        $this->patch($id, ['notifications' => ['options_to_add' => ['down']]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->logRowCount($id));
    }

    public function testNoneCannotJoinTheCurrentOptions(): void
    {
        $id = $this->insertTemplate('none', ['host_notification_options' => 'd']);
        $this->login();

        $response = $this->patch($id, ['notifications' => ['options_to_add' => ['none']]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('notifications', $response);
    }

    public function testTheMacrosAreReplacedUpsertedAndRemoved(): void
    {
        $id = $this->insertTemplate('macros');
        $port = $this->insertMacro($id, '$_HOSTPORT$', '80');
        $timeout = $this->insertMacro($id, '$_HOSTTIMEOUT$', '10');
        $this->login();

        $this->patch($id, ['check_options' => ['macros_to_upsert' => [['id' => $port, 'name' => 'port', 'value' => '443'], ['name' => 'new', 'value' => 'n']]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_HOSTNEW$' => 'n', '$_HOSTPORT$' => '443', '$_HOSTTIMEOUT$' => '10'], $this->macros($id));
        self::assertSame(['$_HOSTPORT$' => $port], array_intersect_key($this->macroIds($id), ['$_HOSTPORT$' => true]));

        $this->patch($id, ['check_options' => ['macros_to_remove' => [$timeout]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_HOSTNEW$' => 'n', '$_HOSTPORT$' => '443'], $this->macros($id));

        $this->patch($id, ['check_options' => ['macros' => [['id' => $port, 'name' => 'port', 'value' => '443']]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_HOSTPORT$' => '443'], $this->macros($id));
    }

    public function testRemovingAMacroTheTemplateDoesNotOwnIsRefusedOnCheckOptions(): void
    {
        $id = $this->insertTemplate('macros');
        $foreign = $this->insertMacro($this->insertTemplate('other'), '$_HOSTFOREIGN$', 'x');
        $this->login();

        $response = $this->patch($id, ['check_options' => ['macros_to_remove' => [$foreign]]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('check_options', $response);
        self::assertSame('x', $this->connection->fetchOne('SELECT host_macro_value FROM on_demand_macro_host WHERE host_macro_id = ?', [$foreign]));
    }

    public function testTwoMacrosOfOneNameAreRefused(): void
    {
        $id = $this->insertTemplate('macros');
        $port = $this->insertMacro($id, '$_HOSTPORT$', '80');
        $this->insertMacro($id, '$_HOSTTIMEOUT$', '10');
        $this->login();

        $this->patch($id, ['check_options' => ['macros_to_upsert' => [['name' => 'a', 'value' => '1'], ['name' => 'A', 'value' => '2']]]]);
        self::assertResponseStatusCodeSame(422);

        $response = $this->patch($id, ['check_options' => ['macros_to_upsert' => [['id' => $port, 'name' => 'timeout', 'value' => '1']]]]);
        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('check_options', $response);
    }

    public function testAPasswordMacroSentWithoutAValueKeepsItsValue(): void
    {
        $id = $this->insertTemplate('password');
        $password = $this->insertMacro($id, '$_HOSTPWD$', 'stored', true);
        $this->login();

        $this->patch($id, ['check_options' => ['macros' => [['id' => $password, 'name' => 'pwd', 'value' => null, 'is_password' => true], ['name' => 'other', 'value' => 'o']]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('stored', $this->connection->fetchOne('SELECT host_macro_value FROM on_demand_macro_host WHERE host_macro_id = ?', [$password]));
    }

    public function testAVaultReferenceAsAMacroValueIsRefused(): void
    {
        $id = $this->insertTemplate('vault-ref');
        $this->login();

        $this->patch($id, ['check_options' => ['macros_to_upsert' => [['name' => 'pwd', 'value' => 'secret::vault::monitoring/hosts/x::_HOSTPWD', 'is_password' => true]]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->macros($id));
    }

    public function testAReservedMacroNameIsAccepted(): void
    {
        if ($this->connection->fetchOne('SELECT 1 FROM nagios_macro WHERE macro_name = ?', ['$_HOSTSNMPCOMMUNITY$']) === false) {
            $this->connection->insert('nagios_macro', ['macro_name' => '$_HOSTSNMPCOMMUNITY$']);
        }
        $id = $this->insertTemplate('reserved');
        $this->login();

        $this->patch($id, ['check_options' => ['macros_to_upsert' => [['name' => 'SNMPCOMMUNITY', 'value' => 'x']]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_HOSTSNMPCOMMUNITY$' => 'x'], $this->macros($id));
    }

    public function testAReplacementKeepsTheCategoriesARestrictedUserCannotSee(): void
    {
        [$visible, $hidden, $added] = [$this->insertCategory('visible'), $this->insertCategory('hidden'), $this->insertCategory('added')];
        $id = $this->insertTemplate('scoped');
        foreach ([$visible, $hidden] as $categoryId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $categoryId]);
        }
        $aclGroupId = $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);
        $aclResourceId = $this->insertRow('acl_resources', ['acl_res_name' => "res-{$this->tag}", 'acl_res_alias' => "res-{$this->tag}", 'acl_res_activate' => '1']);
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $aclResourceId, 'acl_group_id' => $aclGroupId]);
        foreach ([$visible, $added] as $categoryId) {
            $this->connection->insert('acl_resources_hc_relations', ['acl_res_id' => $aclResourceId, 'hc_id' => $categoryId]);
        }

        $this->patch($id, ['category_ids' => [$added]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([$hidden, $added], $this->ids('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', $id));
    }

    /**
     * The template the full-detail read test uses: every field, list and macro set.
     */
    private function insertFullTemplate(): int
    {
        $checkCommandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $eventHandlerId = $this->insertRow('command', ['command_name' => "handler-{$this->tag}", 'command_line' => '$USER1$/handle', 'command_type' => 2]);
        $checkPeriodId = $this->insertRow('timeperiod', ['tp_name' => "check-{$this->tag}", 'tp_alias' => 'check']);
        $notificationPeriodId = $this->insertRow('timeperiod', ['tp_name' => "notify-{$this->tag}", 'tp_alias' => 'notify']);
        $timezoneId = $this->insertRow('timezone', ['timezone_name' => "zone-{$this->tag}", 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);
        $directoryId = $this->insertRow('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $iconId = $this->insertRow('view_img', ['img_name' => "icon-{$this->tag}", 'img_path' => "icon-{$this->tag}.png"]);
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $directoryId, 'img_img_id' => $iconId]);
        [$firstParentId, $secondParentId] = [$this->insertTemplate('parent-a'), $this->insertTemplate('parent-b')];
        $this->insertMacro($firstParentId, '$_HOSTINHERITED$', 'i');
        $categoryId = $this->insertCategory('category');
        $severityId = $this->insertRow('hostcategories', ['hc_name' => "severity-{$this->tag}", 'hc_alias' => 'severity', 'hc_activate' => '1', 'level' => 1]);
        $contactId = $this->insertContact('contact');
        $contactGroupId = $this->insertContactGroup('group');
        $linkedId = $this->insertServiceTemplate('linked');
        $id = $this->insertTemplate('full', [
            'host_alias' => 'An alias',
            'host_snmp_community' => 'public',
            'host_snmp_version' => '2c',
            'host_location' => $timezoneId,
            'host_comment' => 'A comment',
            'command_command_id' => $checkCommandId,
            'command_command_id_arg1' => '!-w!80',
            'timeperiod_tp_id' => $checkPeriodId,
            'host_max_check_attempts' => 3,
            'host_check_interval' => 5,
            'host_retry_check_interval' => 1,
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_check_freshness' => '1',
            'host_freshness_threshold' => 60,
            'host_flap_detection_enabled' => '0',
            'host_low_flap_threshold' => 10,
            'host_high_flap_threshold' => 40,
            'host_event_handler_enabled' => '1',
            'command_command_id2' => $eventHandlerId,
            'command_command_id_arg2' => '!restart',
            'host_acknowledgement_timeout' => 15,
            'host_notifications_enabled' => '1',
            'host_notification_options' => 'd,r',
            'host_notification_interval' => 30,
            'timeperiod_tp_id2' => $notificationPeriodId,
            'host_first_notification_delay' => 2,
            'host_recovery_notification_delay' => 4,
            'contact_additive_inheritance' => 0,
            'cg_additive_inheritance' => 0,
        ]);
        $this->connection->insert('extended_host_information', [
            'host_host_id' => $id,
            'ehi_notes_url' => 'https://notes.example.com',
            'ehi_notes' => 'A note',
            'ehi_action_url' => 'https://action.example.com',
            'ehi_icon_image' => $iconId,
            'ehi_icon_image_alt' => 'An icon',
        ]);
        $this->link($id, $firstParentId, 1);
        $this->link($id, $secondParentId, 2);
        foreach ([$categoryId, $severityId] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $this->connection->insert('contact_host_relation', ['host_host_id' => $id, 'contact_id' => $contactId]);
        $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $id, 'contactgroup_cg_id' => $contactGroupId]);
        $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $linkedId]);
        $this->insertMacro($id, '$_HOSTPORT$', '443');
        $this->insertMacro($id, '$_HOSTPWD$', 'secret', true);

        return $id;
    }

    /**
     * What the form sends back for a template it opened and saved untouched: every block, the lists as
     * replacements, the template's own macros by id (a password without its value), no SNMP community.
     *
     * @return array<string, mixed>
     */
    private function formPayload(int $id): array
    {
        $detail = $this->request('GET', self::URI . '/' . $id, self::JSON)->toArray();
        self::assertResponseStatusCodeSame(200);
        $check = $this->member($detail, 'check_options');
        $extended = $this->member($detail, 'extended_informations');
        $scheduling = $this->member($detail, 'scheduling_options');
        $data = $this->member($detail, 'data_processing');
        $notifications = $this->member($detail, 'notifications');

        return [
            'name' => $detail['name'],
            'alias' => $detail['alias'],
            'snmp_version' => $detail['snmp_version'],
            'timezone_id' => $this->member($detail, 'timezone')['id'],
            'severity_id' => $this->member($detail, 'severity')['id'],
            'template_ids' => $this->idsOf($detail['templates']),
            'category_ids' => $this->idsOf($detail['categories']),
            'linked_service_template_ids' => $this->idsOf($detail['linked_service_templates']),
            'check_options' => [
                'command_id' => $this->member($check, 'command')['id'],
                'args' => $check['args'],
                'macros' => array_values(array_map(
                    static fn (array $macro): array => [
                        'id' => $macro['id'],
                        'name' => $macro['name'],
                        'value' => $macro['value'] ?? null,
                        'is_password' => $macro['is_password'],
                        'parent' => null,
                    ],
                    array_filter($this->listOf($check['macros']), static fn (array $macro): bool => ! isset($macro['parent'])),
                )),
            ],
            'extended_informations' => [
                'note_url' => $extended['note_url'],
                'note' => $extended['note'],
                'action_url' => $extended['action_url'],
                'icon_id' => $this->member($extended, 'icon')['id'],
                'alt_icon' => $extended['alt_icon'],
                'comment' => $extended['comment'],
            ],
            'scheduling_options' => [
                'check_timeperiod_id' => $this->member($scheduling, 'check_period')['id'],
                'max_check_attempts' => $scheduling['max_check_attempts'],
                'normal_check_interval' => $scheduling['normal_check_interval'],
                'retry_check_interval' => $scheduling['retry_check_interval'],
                'active_check_enabled' => $scheduling['active_check_enabled'],
                'passive_check_enabled' => $scheduling['passive_check_enabled'],
            ],
            'data_processing' => [
                'check_freshness' => $data['check_freshness'],
                'freshness_threshold' => $data['freshness_threshold'],
                'event_handler_enabled' => $data['event_handler_enabled'],
                'event_handler_command_id' => $this->member($data, 'event_handler')['id'],
                'event_handler_args' => $data['event_handler_args'],
                'acknowledgment_timeout' => $data['acknowledgment_timeout'],
                'flap_detection_enabled' => $data['flap_detection_enabled'],
                'low_flap_threshold' => $data['low_flap_threshold'],
                'high_flap_threshold' => $data['high_flap_threshold'],
            ],
            'notifications' => [
                'enabled' => $notifications['enabled'],
                'contacts' => $this->idsOf($notifications['contacts']),
                'contact_groups' => $this->idsOf($notifications['contact_groups']),
                'options' => $notifications['options'],
                'interval' => $notifications['interval'],
                'timeperiod_id' => $this->member($notifications, 'timeperiod')['id'],
                'first_delay' => $notifications['first_delay'],
                'recovery_delay' => $notifications['recovery_delay'],
                'contact_additive_inheritance' => $notifications['contact_additive_inheritance'],
                'contact_group_additive_inheritance' => $notifications['contact_group_additive_inheritance'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(int $id): array
    {
        $rows = [];
        foreach ([
            'host' => 'SELECT * FROM host WHERE host_id = ?',
            'extended' => 'SELECT * FROM extended_host_information WHERE host_host_id = ?',
            'parents' => 'SELECT * FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`',
            'categories' => 'SELECT * FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id',
            'contacts' => 'SELECT * FROM contact_host_relation WHERE host_host_id = ?',
            'groups' => 'SELECT * FROM contactgroup_host_relation WHERE host_host_id = ?',
            'linked' => 'SELECT * FROM host_service_relation WHERE host_host_id = ? ORDER BY hsr_id',
            'macros' => 'SELECT * FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY host_macro_id',
        ] as $key => $sql) {
            $rows[$key] = $this->connection->fetchAllAssociative($sql, [$id]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(int $id, array $body): ResponseInterface
    {
        return $this->request('PATCH', self::URI . '/' . $id, ['headers' => ['Content-Type' => self::MERGE_PATCH], 'json' => $body]);
    }

    private function assertRefusedOn(string $field, ResponseInterface $response): void
    {
        $message = $response->toArray(false)['message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString("[{$field}]", $message);
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertTemplate(string $prefix, array $columns = []): int
    {
        return $this->insertRow('host', $columns + ['host_name' => "{$prefix}-{$this->tag}-" . Uuid::v4()->toBase58(), 'host_alias' => $prefix, 'host_register' => '0', 'host_activate' => '1']);
    }

    /**
     * @return int the template at the bottom of the chain
     */
    private function insertChainBelow(int $topId, int $levels): int
    {
        $parentId = $topId;
        for ($level = 0; $level < $levels; ++$level) {
            $childId = $this->insertTemplate("level-{$level}");
            $this->link($childId, $parentId, 1);
            $parentId = $childId;
        }

        return $parentId;
    }

    private function link(int $childId, int $parentId, int $order): void
    {
        $this->connection->insert('host_template_relation', ['host_host_id' => $childId, 'host_tpl_id' => $parentId, '`order`' => $order]);
    }

    private function insertCategory(string $prefix): int
    {
        return $this->insertRow('hostcategories', ['hc_name' => "{$prefix}-" . Uuid::v4()->toBase58(), 'hc_alias' => $prefix, 'hc_activate' => '1']);
    }

    private function insertServiceTemplate(string $prefix): int
    {
        return $this->insertRow('service', ['service_description' => "{$prefix}-" . Uuid::v4()->toBase58(), 'service_register' => '0']);
    }

    private function insertContact(string $prefix): int
    {
        $name = "{$prefix}-" . Uuid::v4()->toBase58();

        return $this->insertRow('contact', ['contact_name' => $name, 'contact_alias' => $name, 'contact_admin' => '0', 'contact_register' => '1', 'contact_activate' => '1']);
    }

    private function insertContactGroup(string $prefix): int
    {
        return $this->insertRow('contactgroup', ['cg_name' => "{$prefix}-" . Uuid::v4()->toBase58(), 'cg_alias' => $prefix, 'cg_type' => 'local', 'cg_activate' => '1']);
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword = false): int
    {
        return $this->insertRow('on_demand_macro_host', ['host_host_id' => $hostId, 'host_macro_name' => $name, 'host_macro_value' => $value, 'is_password' => $isPassword ? 1 : null]);
    }

    private function insertPoller(): int
    {
        return $this->insertRow('nagios_server', ['name' => 'poller-' . Uuid::v4()->toBase58(), 'ns_ip_address' => '127.0.0.1', 'uid' => random_int(1, \PHP_INT_MAX), 'updated' => '0']);
    }

    private function insertHostOn(int $templateId, int $pollerId): int
    {
        $hostId = $this->insertRow('host', ['host_name' => 'host-' . Uuid::v4()->toBase58(), 'host_register' => '1', 'host_activate' => '1']);
        $this->link($hostId, $templateId, 1);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertRow(string $table, array $columns): int
    {
        $this->connection->insert($table, $columns);

        return (int) $this->connection->lastInsertId();
    }

    private function pollerUpdated(int $pollerId): mixed
    {
        return $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
    }

    private function logRowCount(int $id): int
    {
        return $this->asInt($this->realTimeConnection->fetchOne("SELECT COUNT(*) FROM log_action WHERE object_id = ? AND object_type = 'host'", [$id]));
    }

    /**
     * @return list<int>
     */
    private function parentIds(int $id): array
    {
        return $this->ids('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`', $id);
    }

    /**
     * @return list<int>
     */
    private function ids(string $sql, int $id): array
    {
        return array_map($this->asInt(...), $this->connection->fetchFirstColumn($sql, [$id]));
    }

    /**
     * @return array<string, string>
     */
    private function macros(int $id): array
    {
        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue('SELECT host_macro_name, host_macro_value FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY host_macro_name', [$id]);
    }

    /**
     * @return array<string, int>
     */
    private function macroIds(int $id): array
    {
        $ids = [];
        foreach ($this->connection->fetchAllKeyValue('SELECT host_macro_name, host_macro_id FROM on_demand_macro_host WHERE host_host_id = ?', [$id]) as $name => $macroId) {
            $ids[(string) $name] = $this->asInt($macroId);
        }

        return $ids;
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private function member(array $body, string $key): array
    {
        $value = $body[$key] ?? null;
        Assert::isArray($value);

        return $value;
    }

    /**
     * @return list<array<mixed>>
     */
    private function listOf(mixed $value): array
    {
        Assert::isList($value);
        Assert::allIsArray($value);

        return $value;
    }

    /**
     * @return list<int>
     */
    private function idsOf(mixed $items): array
    {
        return array_map(fn (array $item): int => $this->asInt($item['id'] ?? null), $this->listOf($items));
    }

    private function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer value.');

        return (int) $value;
    }
}
