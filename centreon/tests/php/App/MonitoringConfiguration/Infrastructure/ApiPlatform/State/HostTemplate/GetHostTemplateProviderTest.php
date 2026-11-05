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

use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\GetHostTemplateProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\HostTemplateResourceBuilder;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\HostTemplateResourceTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Domain\Service\AccessScopeResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class GetHostTemplateProviderTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    private const MESSAGE = 'You are not allowed to view host templates';

    // about 5 times the slowest warm 900-level response measured (1.05 s), so a loaded runner cannot flake
    private const MAX_SECONDS = 5.0;
    private const JSON = ['headers' => ['accept' => 'application/json']];

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', $this->uri($this->insertTemplate('tpl')));

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAHostReadWriteUserWithoutHostTemplateAccess(): void
    {
        $id = $this->insertTemplate('tpl');
        $username = bin2hex(random_bytes(8));
        $this->grantHostTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_WRITE, $this->tag);
        $this->login($username);

        $response = $this->request('GET', $this->uri($id));

        self::assertResponseStatusCodeSame(403);
        self::assertSame(self::MESSAGE, $response->toArray(false)['message']);
    }

    public function testAHostTemplateReadOnlyUserReadsIt(): void
    {
        $id = $this->insertTemplate('tpl');
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame($id, $body['id']);
    }

    public function testAHostTemplateReadWriteUserReadsIt(): void
    {
        $id = $this->insertTemplate('tpl');
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame($id, $body['id']);
    }

    public function testItReturnsNotFoundForAnUnknownTemplate(): void
    {
        $this->login();

        $this->request('GET', $this->uri(\PHP_INT_MAX >> 33));

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForAHost(): void
    {
        $this->connection->insert('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        $this->login();

        $this->request('GET', $this->uri($hostId));

        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('provideInvalidIds')]
    public function testAnInvalidIdIsNotFound(string $invalidId): void
    {
        $id = $this->insertTemplate('tpl');
        $this->login();

        $this->request('GET', '/api/configuration/host_templates/' . str_replace('<id>', (string) $id, $invalidId));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIds(): iterable
    {
        yield 'zero' => ['0'];

        yield 'leading zero' => ['0<id>'];

        yield 'not a number' => ['abc'];
    }

    public function testItReturnsTheFullDetail(): void
    {
        $checkCommandId = $this->insertCommand('check');
        $eventHandlerId = $this->insertCommand('handler');
        $checkPeriodId = $this->insertTimePeriod('check-period');
        $notificationPeriodId = $this->insertTimePeriod('notification-period');
        $timezoneId = $this->insertTimezone('zone');
        $iconId = $this->insertImage();
        $secondParentId = $this->insertTemplate('parent-b');
        $firstParentId = $this->insertTemplate('parent-a');
        $categoryId = $this->insertCategory('category', null);
        $severityId = $this->insertCategory('severity', 1);
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
            'contact_additive_inheritance' => 1,
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
        $this->linkTemplates($id, $firstParentId, 1);
        $this->linkTemplates($id, $secondParentId, 2);
        foreach ([$categoryId, $severityId] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $this->connection->insert('contact_host_relation', ['host_host_id' => $id, 'contact_id' => $contactId]);
        $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $id, 'contactgroup_cg_id' => $contactGroupId]);
        $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $linkedId]);

        $portId = $this->insertMacro($id, '$_HOSTPORT$', '443', false);
        $passwordId = $this->insertMacro($id, '$_HOSTPWD$', 'secret', true);
        $this->login();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertEqualsCanonicalizing(
            [
                'id', 'name', 'alias', 'is_locked', 'snmp_version', 'is_snmp_community_defined', 'timezone', 'severity',
                'categories', 'templates', 'linked_service_templates', 'check_options', 'extended_informations',
                'scheduling_options', 'data_processing', 'notifications',
            ],
            array_keys($body),
        );
        self::assertSame($id, $body['id']);
        self::assertSame("full-{$this->tag}", $body['name']);
        self::assertSame('An alias', $body['alias']);
        self::assertFalse($body['is_locked']);
        self::assertSame('2c', $body['snmp_version']);
        self::assertTrue($body['is_snmp_community_defined']);
        self::assertSame(['id' => $timezoneId, 'name' => "zone-{$this->tag}"], $body['timezone']);
        self::assertSame(['id' => $severityId, 'name' => "severity-{$this->tag}"], $body['severity']);
        self::assertSame([['id' => $categoryId, 'name' => "category-{$this->tag}"]], $body['categories']);
        self::assertSame(
            [['id' => $firstParentId, 'name' => "parent-a-{$this->tag}"], ['id' => $secondParentId, 'name' => "parent-b-{$this->tag}"]],
            $body['templates'],
        );
        self::assertSame([['id' => $linkedId, 'name' => "linked-{$this->tag}"]], $body['linked_service_templates']);
        self::assertSame(
            [
                'command' => ['id' => $checkCommandId, 'name' => "check-{$this->tag}"],
                'args' => ['-w', '80'],
                'macros' => [
                    ['id' => $portId, 'name' => 'PORT', 'value' => '443', 'is_password' => false],
                    ['id' => $passwordId, 'name' => 'PWD', 'is_password' => true],
                ],
            ],
            $body['check_options'],
        );
        $extendedInformations = $body['extended_informations'];
        Assert::isArray($extendedInformations);
        Assert::isArray($extendedInformations['icon']);
        self::assertSame($iconId, $extendedInformations['icon']['id']);
        self::assertSame("icon-{$this->tag}.png", $extendedInformations['icon']['name']);
        self::assertIsString($extendedInformations['icon']['url']);
        unset($extendedInformations['icon']);
        self::assertSame(
            [
                'note_url' => 'https://notes.example.com',
                'note' => 'A note',
                'action_url' => 'https://action.example.com',
                'alt_icon' => 'An icon',
                'comment' => 'A comment',
            ],
            $extendedInformations,
        );
        self::assertSame(
            [
                'check_period' => ['id' => $checkPeriodId, 'name' => "check-period-{$this->tag}"],
                'max_check_attempts' => 3,
                'normal_check_interval' => 5,
                'retry_check_interval' => 1,
                'active_check_enabled' => 'true',
                'passive_check_enabled' => 'false',
            ],
            $body['scheduling_options'],
        );
        self::assertSame(
            [
                'check_freshness' => 'true',
                'freshness_threshold' => 60,
                'event_handler_enabled' => 'true',
                'event_handler' => ['id' => $eventHandlerId, 'name' => "handler-{$this->tag}"],
                'acknowledgment_timeout' => 15,
                'flap_detection_enabled' => 'false',
                'low_flap_threshold' => 10,
                'high_flap_threshold' => 40,
                'event_handler_args' => ['restart'],
            ],
            $body['data_processing'],
        );
        self::assertSame(
            [
                'enabled' => 'true',
                'contacts' => [['id' => $contactId, 'name' => "contact-{$this->tag}"]],
                'contact_groups' => [['id' => $contactGroupId, 'name' => "group-{$this->tag}"]],
                'options' => ['down', 'recovery'],
                'interval' => 30,
                'timeperiod' => ['id' => $notificationPeriodId, 'name' => "notification-period-{$this->tag}"],
                'first_delay' => 2,
                'recovery_delay' => 4,
                'contact_additive_inheritance' => true,
                'contact_group_additive_inheritance' => false,
            ],
            $body['notifications'],
        );

        $this->request('GET', $this->uri($id));
        self::assertResponseStatusCodeSame(200);
        self::assertMatchesResourceItemJsonSchema(HostTemplateResource::class);
    }

    public function testABareTemplateKeepsItsListsAndOmitsItsEmptyValues(): void
    {
        $id = $this->insertTemplate('bare', ['host_alias' => ' ', 'host_snmp_version' => '']);
        $this->login();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $body['categories']);
        self::assertSame([], $body['templates']);
        self::assertSame([], $body['linked_service_templates']);
        self::assertSame(['args' => [], 'macros' => []], $body['check_options']);
        self::assertFalse($body['is_snmp_community_defined']);
        foreach (['alias', 'snmp_version', 'timezone', 'severity', 'extended_informations'] as $absent) {
            self::assertArrayNotHasKey($absent, $body);
        }
        foreach (['address', 'poller', 'activated', 'groups', 'parent_hosts', 'child_hosts', 'geo_coordinates'] as $hostOnly) {
            self::assertArrayNotHasKey($hostOnly, $body);
        }
    }

    public function testItReturnsAStoredOutOfBoundTemplateAsStored(): void
    {
        $note = str_repeat('n', 600);
        $url = 'https://example.com/' . str_repeat('u', 2980);
        $id = $this->insertTemplate('legacy', [
            'host_max_check_attempts' => 0,
            'host_acknowledgement_timeout' => 0,
            'host_high_flap_threshold' => 150,
            'host_snmp_version' => 'v9',
            'host_comment' => '',
            'command_command_id_arg1' => '!-w!80',
        ]);
        $this->connection->insert('extended_host_information', ['host_host_id' => $id, 'ehi_notes' => $note, 'ehi_notes_url' => $url, 'ehi_action_url' => ' ']);
        $this->login();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('snmp_version', $body);
        self::assertSame(['note_url' => $url, 'note' => $note], $body['extended_informations']);
        self::assertSame(0, $this->member($body, 'scheduling_options', 'max_check_attempts'));
        self::assertSame(0, $this->member($body, 'data_processing', 'acknowledgment_timeout'));
        self::assertSame(150, $this->member($body, 'data_processing', 'high_flap_threshold'));
        self::assertSame(['args' => ['-w', '80'], 'macros' => []], $body['check_options']);
    }

    public function testALockedTemplateReadsAsLocked(): void
    {
        $id = $this->insertTemplate('locked', ['host_locked' => 1]);
        $this->login();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($body['is_locked']);
    }

    public function testADisabledTemplateIsReturnedWithItsInheritedMacros(): void
    {
        $ancestorId = $this->insertTemplate('ancestor');
        $ancestorMacroId = $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10', false);
        $id = $this->insertTemplate('disabled', ['host_activate' => '0']);
        $this->linkTemplates($id, $ancestorId, 1);
        $ownMacroId = $this->insertMacro($id, '$_HOSTPORT$', '443', false);
        $this->login();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [
                ['id' => $ownMacroId, 'name' => 'PORT', 'value' => '443', 'is_password' => false],
                ['id' => $ancestorMacroId, 'name' => 'TIMEOUT', 'value' => '10', 'is_password' => false, 'parent' => 'template'],
            ],
            $this->member($body, 'check_options', 'macros'),
        );
    }

    public function testTheMacrosAreThoseOfTheMacrosRoute(): void
    {
        $commandId = $this->insertCommand('check', '$USER1$/check -t $_HOSTFROMCOMMAND$');
        $this->connection->insert('on_demand_macro_command', [
            'command_command_id' => $commandId,
            'command_macro_name' => 'FROMCOMMAND',
            'command_macro_type' => '1',
        ]);
        $ancestorId = $this->insertTemplate('ancestor', ['command_command_id' => $commandId]);
        $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10', false);
        $this->insertMacro($ancestorId, '$_HOSTPORT$', '80', false);
        $id = $this->insertTemplate('tpl');
        $this->linkTemplates($id, $ancestorId, 1);
        $this->insertMacro($id, '$_HOSTPORT$', '443', false);
        $this->login();

        $detailMacros = $this->listMember($this->request('GET', $this->uri($id), self::JSON)->toArray(), 'check_options', 'macros');
        $routeMacros = $this->request('GET', "/api/configuration/host_templates/host_templates/{$id}/macros", self::JSON)->toArray()['macros'];

        self::assertResponseStatusCodeSame(200);
        self::assertCount(3, $detailMacros);
        self::assertSame($routeMacros, $detailMacros);
    }

    public function testItsOwnCheckCommandOverridesItsAncestorsOneForTheMacros(): void
    {
        $ancestorCommandId = $this->insertCommand('ancestor-check', '$USER1$/check -t $_HOSTFROMANCESTOR$');
        $ownCommandId = $this->insertCommand('own-check', '$USER1$/check -t $_HOSTFROMOWN$');
        foreach ([$ancestorCommandId => 'FROMANCESTOR', $ownCommandId => 'FROMOWN'] as $commandId => $macroName) {
            $this->connection->insert('on_demand_macro_command', [
                'command_command_id' => $commandId,
                'command_macro_name' => $macroName,
                'command_macro_type' => '1',
            ]);
        }
        $ancestorId = $this->insertTemplate('ancestor', ['command_command_id' => $ancestorCommandId]);
        $id = $this->insertTemplate('tpl', ['command_command_id' => $ownCommandId]);
        $this->linkTemplates($id, $ancestorId, 1);
        $this->login();

        $macros = $this->listMember($this->request('GET', $this->uri($id), self::JSON)->toArray(), 'check_options', 'macros');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([['FROMOWN', 'command']], array_map(
            static fn (mixed $macro): array => [((array) $macro)['name'] ?? null, ((array) $macro)['parent'] ?? null],
            $macros,
        ));
    }

    public function testTheCommandMacrosOfItsOwnLinkedServiceTemplatesAreNotInherited(): void
    {
        $commandId = $this->insertCommand('service-check', '$USER1$/check -t $_HOSTFROMSERVICE$');
        $this->connection->insert('on_demand_macro_command', [
            'command_command_id' => $commandId,
            'command_macro_name' => 'FROMSERVICE',
            'command_macro_type' => '1',
        ]);
        $linkedId = $this->insertServiceTemplate('linked');
        $this->connection->update('service', ['command_command_id' => $commandId], ['service_id' => $linkedId]);
        $id = $this->insertTemplate('tpl');
        $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $linkedId]);
        $this->login();

        $detailMacros = $this->listMember($this->request('GET', $this->uri($id), self::JSON)->toArray(), 'check_options', 'macros');
        $routeMacros = $this->request('GET', "/api/configuration/host_templates/host_templates/{$id}/macros", self::JSON)->toArray()['macros'];

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $detailMacros);
        self::assertSame(['FROMSERVICE'], array_column((array) $routeMacros, 'name'));
    }

    public function testItAnswersPromptlyOnALoopAlreadyInTheData(): void
    {
        $first = $this->insertTemplate('loop-first');
        $second = $this->insertTemplate('loop-second');
        $this->linkTemplates($first, $second, 1);
        $this->linkTemplates($second, $first, 1);
        $this->insertMacro($second, '$_HOSTSECOND$', '2', false);
        $this->login();

        [$seconds, $body] = $this->timedDetail($first);

        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame([['id' => $second, 'name' => "loop-second-{$this->tag}"]], $body['templates']);
        self::assertSame(['SECOND'], array_column($this->listMember($body, 'check_options', 'macros'), 'name'));
    }

    /**
     * 900 levels stay under the recursion cap of the database server (1000 on MySQL and MariaDB).
     */
    public function testItReadsATemplateAtTheBottomOfA900LevelChain(): void
    {
        $topId = $this->insertTemplate('level-900');
        $this->insertMacro($topId, '$_HOSTTOP$', 'top', false);
        $childId = $topId;
        for ($level = 899; $level >= 1; $level--) {
            $parentId = $childId;
            $childId = $this->insertTemplate("level-{$level}");
            $this->linkTemplates($childId, $parentId, 1);
        }
        $this->login();

        [$seconds, $body] = $this->timedDetail($childId);

        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame(['TOP'], array_column($this->listMember($body, 'check_options', 'macros'), 'name'));
    }

    public function testItHidesTheOutOfScopeCategoriesSeverityContactsAndGroupsFromARestrictedUser(): void
    {
        $id = $this->insertTemplate('scoped');
        $visibleCategoryId = $this->insertCategory('visible-category', null);
        $hiddenCategoryId = $this->insertCategory('hidden-category', null);
        $severityId = $this->insertCategory('severity', 1);
        foreach ([$visibleCategoryId, $hiddenCategoryId, $severityId] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $visibleContactId = $this->insertContact('visible-contact');
        $hiddenContactId = $this->insertContact('hidden-contact');
        foreach ([$visibleContactId, $hiddenContactId] as $contactId) {
            $this->connection->insert('contact_host_relation', ['host_host_id' => $id, 'contact_id' => $contactId]);
        }
        $visibleGroupId = $this->insertContactGroup('visible-group');
        $hiddenGroupId = $this->insertContactGroup('hidden-group');
        foreach ([$visibleGroupId, $hiddenGroupId] as $groupId) {
            $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $id, 'contactgroup_cg_id' => $groupId]);
        }
        $aclGroupId = $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);
        $this->connection->insert('acl_resources', ['acl_res_name' => "res-{$this->tag}", 'acl_res_alias' => "res-{$this->tag}", 'acl_res_activate' => '1']);
        $aclResourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $aclResourceId, 'acl_group_id' => $aclGroupId]);
        $this->connection->insert('acl_resources_hc_relations', ['acl_res_id' => $aclResourceId, 'hc_id' => $visibleCategoryId]);
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $aclGroupId, 'contact_contact_id' => $visibleContactId]);
        $this->connection->insert('acl_group_contactgroups_relations', ['acl_group_id' => $aclGroupId, 'cg_cg_id' => $visibleGroupId]);

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$visibleCategoryId], array_column((array) $body['categories'], 'id'));
        self::assertArrayNotHasKey('severity', $body);
        self::assertSame([$visibleContactId], array_column($this->listMember($body, 'notifications', 'contacts'), 'id'));
        self::assertSame([$visibleGroupId], array_column($this->listMember($body, 'notifications', 'contact_groups'), 'id'));
    }

    /**
     * The provider is replaced before the request: the platform is bound from the environment, so it
     * cannot change per test.
     */
    public function testItOmitsTheOnPremiseOnlyFieldsOnACloudPlatform(): void
    {
        $id = $this->insertTemplate('cloud', [
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_max_check_attempts' => 3,
            'host_acknowledgement_timeout' => 15,
            'host_flap_detection_enabled' => '1',
            'host_low_flap_threshold' => 10,
            'host_high_flap_threshold' => 40,
            'command_command_id_arg2' => '!restart',
            'host_check_freshness' => '1',
            'host_notifications_enabled' => '1',
        ]);
        $this->login();
        $this->forceCloudPlatform();

        $body = $this->request('GET', $this->uri($id), self::JSON)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('notifications', $body);
        self::assertSame(['max_check_attempts' => 3], $body['scheduling_options']);
        self::assertSame(
            ['check_freshness' => 'true', 'event_handler_enabled' => 'use_default', 'event_handler_args' => []],
            $body['data_processing'],
        );
    }

    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();

        /** @var HostTemplateResourceTransformer $transformer */
        $transformer = $container->get(HostTemplateResourceTransformer::class);
        /** @var HostTemplateRepository $hostTemplateRepository */
        $hostTemplateRepository = $container->get(HostTemplateRepository::class);
        /** @var HostCategoryRepository $hostCategoryRepository */
        $hostCategoryRepository = $container->get(HostCategoryRepository::class);
        /** @var HostSeverityRepository $hostSeverityRepository */
        $hostSeverityRepository = $container->get(HostSeverityRepository::class);
        /** @var ServiceTemplateRepository $serviceTemplateRepository */
        $serviceTemplateRepository = $container->get(ServiceTemplateRepository::class);
        /** @var CommandRepository $commandRepository */
        $commandRepository = $container->get(CommandRepository::class);
        /** @var TimezoneRepository $timezoneRepository */
        $timezoneRepository = $container->get(TimezoneRepository::class);
        /** @var TimePeriodRepository $timePeriodRepository */
        $timePeriodRepository = $container->get(TimePeriodRepository::class);
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = $container->get(MediaRepository::class);
        /** @var MediaUrlGenerator $mediaUrlGenerator */
        $mediaUrlGenerator = $container->get(MediaUrlGenerator::class);
        /** @var NotificationContactRepository $contactRepository */
        $contactRepository = $container->get(NotificationContactRepository::class);
        /** @var ContactGroupRepository $contactGroupRepository */
        $contactGroupRepository = $container->get(ContactGroupRepository::class);
        /** @var AccessScopeResolver $accessScopes */
        $accessScopes = $container->get(AccessScopeResolver::class);
        /** @var HostMacroTransformer $macroTransformer */
        $macroTransformer = $container->get(HostMacroTransformer::class);
        /** @var InheritedHostMacrosResolver $inheritedHostMacrosResolver */
        $inheritedHostMacrosResolver = $container->get(InheritedHostMacrosResolver::class);
        /** @var Security $security */
        $security = $container->get(Security::class);

        $builder = new HostTemplateResourceBuilder(
            $transformer,
            $hostTemplateRepository,
            $hostCategoryRepository,
            $hostSeverityRepository,
            $serviceTemplateRepository,
            $commandRepository,
            $timezoneRepository,
            $timePeriodRepository,
            $mediaRepository,
            $mediaUrlGenerator,
            $contactRepository,
            $contactGroupRepository,
            $accessScopes,
            $macroTransformer,
            $inheritedHostMacrosResolver,
            isCloudPlatform: true,
        );

        $container->set(GetHostTemplateProvider::class, new GetHostTemplateProvider($security, $hostTemplateRepository, $builder));
    }

    /**
     * The first requests of a test process are slow whatever their query: two warm-ups, then the
     * timed request.
     *
     * @return array{float, array<array-key, mixed>}
     */
    private function timedDetail(int $id): array
    {
        $this->request('GET', $this->uri($id), self::JSON);
        $this->request('GET', $this->uri($id), self::JSON);

        $start = hrtime(true);
        $response = $this->request('GET', $this->uri($id), self::JSON);
        $seconds = (hrtime(true) - $start) / 1e9;
        self::assertResponseStatusCodeSame(200);

        return [$seconds, $response->toArray()];
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function member(array $body, string $object, string $key): mixed
    {
        $section = $body[$object] ?? null;
        Assert::isArray($section);

        return $section[$key] ?? null;
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private function listMember(array $body, string $object, string $key): array
    {
        $list = $this->member($body, $object, $key);
        Assert::isArray($list);

        return $list;
    }

    private function uri(int $id): string
    {
        return "/api/configuration/host_templates/{$id}";
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertTemplate(string $prefix, array $columns = []): int
    {
        $this->connection->insert('host', $columns + [
            'host_name' => "{$prefix}-{$this->tag}",
            'host_register' => '0',
            'host_activate' => '1',
            'host_locked' => 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkTemplates(int $childId, int $parentId, int $order): void
    {
        $this->connection->insert('host_template_relation', ['host_host_id' => $childId, 'host_tpl_id' => $parentId, '`order`' => $order]);
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : 0,
            'host_host_id' => $hostId,
            'macro_order' => 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCommand(string $prefix, string $line = '$USER1$/check'): int
    {
        $this->connection->insert('command', ['command_name' => "{$prefix}-{$this->tag}", 'command_line' => $line, 'command_type' => 2]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimePeriod(string $prefix): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => "{$prefix}-{$this->tag}", 'tp_alias' => $prefix]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimezone(string $prefix): int
    {
        $this->connection->insert('timezone', [
            'timezone_name' => "{$prefix}-{$this->tag}",
            'timezone_offset' => '+00:00',
            'timezone_dst_offset' => '+00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(): int
    {
        $this->connection->insert('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $directoryId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img', ['img_name' => "icon-{$this->tag}", 'img_path' => "icon-{$this->tag}.png"]);
        $imageId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $directoryId, 'img_img_id' => $imageId]);

        return $imageId;
    }

    private function insertCategory(string $prefix, ?int $level): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => "{$prefix}-{$this->tag}", 'hc_alias' => $prefix, 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContact(string $prefix): int
    {
        $name = "{$prefix}-{$this->tag}";
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => "{$prefix}@example.com",
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContactGroup(string $prefix): int
    {
        $this->connection->insert('contactgroup', ['cg_name' => "{$prefix}-{$this->tag}", 'cg_alias' => $prefix, 'cg_type' => 'local', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceTemplate(string $prefix): int
    {
        $this->connection->insert('service', ['service_description' => "{$prefix}-{$this->tag}", 'service_register' => '0']);

        return (int) $this->connection->lastInsertId();
    }
}
