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

use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\CreateHostTemplateProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\HostTemplateResourceBuilder;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\VaultInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;
use Tests\App\Shared\Double\FakeVault;
use Webmozart\Assert\Assert;

final class CreateHostTemplateProcessorTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    use InsertsMonitoringConnectorTemplates;
    private const URI = '/api/configuration/host_templates';
    private const JSON = ['accept' => 'application/json'];

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

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);
        parent::tearDown();
    }

    public function testItCreatesTheTemplateAndAnswersItsDetail(): void
    {
        $commandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $periodId = $this->insertRow('timeperiod', ['tp_name' => "period-{$this->tag}", 'tp_alias' => 'period']);
        $parentId = $this->insertTemplate('parent');
        $linkedId = $this->insertRow('service', ['service_description' => "linked-{$this->tag}", 'service_register' => '0']);
        $categoryId = $this->insertRow('hostcategories', ['hc_name' => "category-{$this->tag}", 'hc_alias' => 'category', 'hc_activate' => '1']);
        $contactId = $this->insertRow('contact', [
            'contact_name' => "contact-{$this->tag}",
            'contact_alias' => "contact-{$this->tag}",
            'contact_register' => '1',
            'contact_activate' => '1',
        ]);
        $this->login();

        $response = $this->request('POST', self::URI, ['headers' => self::JSON, 'json' => [
            'name' => "new {$this->tag}",
            'alias' => 'A template',
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'template_ids' => [$parentId],
            'category_ids' => [$categoryId],
            'linked_service_template_ids' => [$linkedId],
            'check_options' => [
                'command_id' => $commandId,
                'args' => ['-w', '80'],
                'macros' => [['name' => 'port', 'value' => '443', 'is_password' => false], ['name' => 'pwd', 'value' => 's3cr3t', 'is_password' => true]],
            ],
            'extended_informations' => ['note' => 'A note', 'note_url' => 'https://notes.example.com'],
            'scheduling_options' => ['check_timeperiod_id' => $periodId, 'max_check_attempts' => 3, 'active_check_enabled' => 'true'],
            'data_processing' => ['acknowledgment_timeout' => 15, 'low_flap_threshold' => 0, 'high_flap_threshold' => 100],
            'notifications' => ['enabled' => 'true', 'contacts' => [$contactId], 'options' => ['down', 'recovery'], 'interval' => 30],
        ]]);

        self::assertResponseStatusCodeSame(201);
        $created = $response->toArray();
        Assert::integer($created['id']);
        self::assertSame("new_{$this->tag}", $created['name']);
        self::assertSame('A template', $created['alias']);
        self::assertTrue($created['is_snmp_community_defined']);
        self::assertArrayNotHasKey('snmp_community', $created);
        self::assertSame([['id' => $parentId, 'name' => "parent-{$this->tag}"]], $created['templates']);
        self::assertSame([['id' => $linkedId, 'name' => "linked-{$this->tag}"]], $created['linked_service_templates']);
        self::assertSame([['id' => $categoryId, 'name' => "category-{$this->tag}"]], $created['categories']);
        $checkOptions = $created['check_options'];
        Assert::isArray($checkOptions);
        self::assertSame(['id' => $commandId, 'name' => "check-{$this->tag}"], $checkOptions['command']);
        self::assertSame(['-w', '80'], $checkOptions['args']);
        self::assertSame(['PORT', 'PWD'], array_column((array) $checkOptions['macros'], 'name'));
        Assert::isList($checkOptions['macros']);
        Assert::isArray($checkOptions['macros'][1]);
        self::assertArrayNotHasKey('value', $checkOptions['macros'][1]);

        $fetched = $this->request('GET', self::URI . '/' . $created['id'], ['headers' => self::JSON])->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame($created, $fetched);

        $this->request('GET', self::URI . '/' . $created['id']);
        self::assertMatchesResourceItemJsonSchema(HostTemplateResource::class);
    }

    public function testTheCreatedTemplateIsIdentifiedByItsDetailUri(): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => ['name' => "iri-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        $detailUri = self::URI . '/' . $this->asInt($body['id']);
        self::assertSame($detailUri, $body['@id']);
        self::assertSame($detailUri, $this->request('GET', $detailUri)->toArray()['@id']);
    }

    public function testItStoresAnActiveUnlockedTemplate(): void
    {
        $this->login();

        $id = $this->create(['name' => "plain-{$this->tag}", 'alias' => 'A template']);

        $row = $this->connection->fetchAssociative('SELECT host_register, host_activate, host_locked FROM host WHERE host_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame(['0', '1', 0], [$row['host_register'], $row['host_activate'], $this->asInt($row['host_locked'])]);
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAHostTemplateReadOnlyUser(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $response = $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('You are not allowed to create host templates', $response->toArray(false)['message']);
    }

    public function testAHostTemplateReadWriteUserCreatesIt(): void
    {
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testANameUsedByAHostIsAConflict(): void
    {
        $this->insertRow('host', ['host_name' => "taken-{$this->tag}", 'host_register' => '1']);
        $this->login();

        $this->request('POST', self::URI, ['json' => ['name' => "taken-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testANameUsedByATemplateOnceItsSpacesAreReplacedIsAConflict(): void
    {
        $this->insertTemplate('taken');
        $this->login();

        $this->request('POST', self::URI, ['json' => ['name' => " taken-{$this->tag} ", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testANameUsedByATemplateOnceItsInnerSpacesAreReplacedIsAConflict(): void
    {
        $this->insertRow('host', ['host_name' => "taken_x-{$this->tag}", 'host_register' => '0']);
        $this->login();

        $this->request('POST', self::URI, ['json' => ['name' => "taken x-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testInnerSpacesBecomeUnderscores(): void
    {
        $this->login();

        $id = $this->create(['name' => "  my template {$this->tag} ", 'alias' => 'alias']);

        self::assertSame("my_template_{$this->tag}", $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$id]));
    }

    public function testTheHostNameRulesDoNotApply(): void
    {
        $this->login();
        $name = "_Module_tpl\$~-{$this->tag}";

        $id = $this->create(['name' => $name, 'alias' => 'alias']);

        self::assertSame($name, $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$id]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideAliasesRefused')]
    public function testTheAliasIsMandatory(array $body): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => $body + ['name' => "tpl-{$this->tag}"]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('alias', $response);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideAliasesRefused(): iterable
    {
        yield 'missing' => [[]];

        yield 'null' => [['alias' => null]];

        yield 'blank' => [['alias' => '  ']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideNamesRefused')]
    public function testTheNameIsMandatory(array $body): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => $body + ['alias' => 'alias']]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('name', $response);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideNamesRefused(): iterable
    {
        yield 'missing' => [[]];

        yield 'null' => [['name' => null]];

        yield 'blank' => [['name' => '  ']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideRemovedKeys')]
    public function testAKeyTheTemplateDoesNotHaveIsABadRequest(array $body): void
    {
        $this->login();

        $this->request('POST', self::URI, ['json' => $body + ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', ["tpl-{$this->tag}"]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRemovedKeys(): iterable
    {
        yield 'address' => [['address' => '127.0.0.1']];

        yield 'poller_id' => [['poller_id' => 1]];

        yield 'activated' => [['activated' => true]];

        yield 'host_group_ids' => [['host_group_ids' => [1]]];

        yield 'parent_host_ids' => [['parent_host_ids' => [1]]];

        yield 'child_host_ids' => [['child_host_ids' => [1]]];

        yield 'create_services_linked_to_templates' => [['create_services_linked_to_templates' => false]];

        yield 'is_locked' => [['is_locked' => true]];

        yield 'extended_informations.geo_coordinates' => [['extended_informations' => ['geo_coordinates' => '48.8,2.3']]];
    }

    public function testAnUnknownParentOrAHostAsParentIsRefused(): void
    {
        $hostId = $this->insertRow('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1']);
        $this->login();

        foreach ([\PHP_INT_MAX >> 33, $hostId] as $parentId) {
            $response = $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias', 'template_ids' => [$parentId]]]);

            self::assertResponseStatusCodeSame(422);
            $this->assertRefusedOn('template_ids', $response);
        }
    }

    public function testAParentTheLicenseNoLongerAllowsIsRefused(): void
    {
        $parentId = $this->insertTemplate('connector');
        $this->insertMonitoringConnectorTemplates($this->connection, $parentId);
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias', 'template_ids' => [$parentId]]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('template_ids', $response);
    }

    public function testAnUnknownServiceTemplateOrAServiceIsRefused(): void
    {
        $serviceId = $this->insertRow('service', ['service_description' => "service-{$this->tag}", 'service_register' => '1']);
        $this->login();

        foreach ([\PHP_INT_MAX >> 33, $serviceId] as $linkedId) {
            $response = $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias', 'linked_service_template_ids' => [$linkedId]]]);

            self::assertResponseStatusCodeSame(422);
            $this->assertRefusedOn('linked_service_template_ids', $response);
        }
    }

    public function testAServiceTemplateTheLicenseNoLongerAllowsIsRefused(): void
    {
        $linkedId = $this->insertRow('service', ['service_description' => "connector-{$this->tag}", 'service_register' => '0']);
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $linkedId);
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => ['name' => "tpl-{$this->tag}", 'alias' => 'alias', 'linked_service_template_ids' => [$linkedId]]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('linked_service_template_ids', $response);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideValuesOutOfTheBounds')]
    public function testAValueOutOfTheBoundsIsRefusedOnItsField(array $body, string $field): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => $body + ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn($field, $response);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideValuesOutOfTheBounds(): iterable
    {
        yield 'max check attempts 0' => [['scheduling_options' => ['max_check_attempts' => 0]], 'scheduling_options.max_check_attempts'];

        yield 'normal check interval 0' => [['scheduling_options' => ['normal_check_interval' => 0]], 'scheduling_options.normal_check_interval'];

        yield 'retry check interval 0' => [['scheduling_options' => ['retry_check_interval' => 0]], 'scheduling_options.retry_check_interval'];

        yield 'max check attempts 2147483648' => [['scheduling_options' => ['max_check_attempts' => 2147483648]], 'scheduling_options.max_check_attempts'];

        yield 'acknowledgment timeout 0' => [['data_processing' => ['acknowledgment_timeout' => 0]], 'data_processing.acknowledgment_timeout'];

        yield 'freshness threshold -1' => [['data_processing' => ['freshness_threshold' => -1]], 'data_processing.freshness_threshold'];

        yield 'low flap threshold -1' => [['data_processing' => ['low_flap_threshold' => -1]], 'data_processing.low_flap_threshold'];

        yield 'high flap threshold 101' => [['data_processing' => ['high_flap_threshold' => 101]], 'data_processing.high_flap_threshold'];

        yield 'note 513' => [['extended_informations' => ['note' => str_repeat('n', 513)]], 'extended_informations.note'];

        yield 'note url 2049' => [['extended_informations' => ['note_url' => str_repeat('u', 2049)]], 'extended_informations.note_url'];

        yield 'action url 2049' => [['extended_informations' => ['action_url' => str_repeat('u', 2049)]], 'extended_informations.action_url'];

        yield 'alt icon 201' => [['extended_informations' => ['alt_icon' => str_repeat('a', 201)]], 'extended_informations.alt_icon'];

        yield 'comment 65536' => [['extended_informations' => ['comment' => str_repeat('c', 65536)]], 'extended_informations.comment'];

        yield 'notification interval -1' => [['notifications' => ['interval' => -1]], 'notifications.interval'];

        yield 'first notification delay -1' => [['notifications' => ['first_delay' => -1]], 'notifications.first_delay'];

        yield 'recovery notification delay -1' => [['notifications' => ['recovery_delay' => -1]], 'notifications.recovery_delay'];

        yield 'notification interval 2147483648' => [['notifications' => ['interval' => 2147483648]], 'notifications.interval'];

        yield 'arguments without a check command' => [['check_options' => ['command_id' => null, 'args' => ['-w', '80']]], 'check_options.args'];

        yield 'an argument with the delimiter' => [['check_options' => ['args' => ['a!b']]], 'check_options.args[0]'];

        yield 'an argument with an escape token' => [['check_options' => ['args' => ['a#BR#b']]], 'check_options.args[0]'];

        yield 'two macros of one name' => [
            ['check_options' => ['macros' => [['name' => 'port', 'value' => '1'], ['name' => 'PORT', 'value' => '2']]]],
            'check_options.macros',
        ];

        yield 'a macro value that is a vault reference' => [
            ['check_options' => ['macros' => [['name' => 'pwd', 'value' => 'secret::vault::monitoring/hosts/x::_HOSTPWD', 'is_password' => true]]]],
            'check_options.macros[0].value',
        ];

        yield 'a name over 200' => [['name' => str_repeat('n', 201)], 'name'];

        yield 'snmp community 256' => [['snmp_community' => str_repeat('s', 256)], 'snmp_community'];

        yield 'macro name 249' => [['check_options' => ['macros' => [['name' => str_repeat('m', 249), 'value' => 'x']]]], 'check_options.macros[0].name'];

        yield 'macro value 4097' => [['check_options' => ['macros' => [['name' => 'long', 'value' => str_repeat('v', 4097)]]]], 'check_options.macros[0].value'];

        yield 'arguments over 65535 bytes' => [['check_options' => ['args' => [str_repeat('a', 65536)]]], 'check_options.args'];

        yield 'an alias over 200' => [['alias' => str_repeat('a', 201)], 'alias'];

        yield 'a name with a line break' => [['name' => "tpl\nx"], 'name'];

        yield 'an snmp community that is a vault reference' => [
            ['snmp_community' => 'secret::vault::monitoring/hosts/x::_HOSTSNMPCOMMUNITY'],
            'snmp_community',
        ];

        yield 'a comment over 65535 bytes' => [['extended_informations' => ['comment' => str_repeat('é', 40000)]], 'extended_informations.comment'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideUnknownReferences')]
    public function testAnUnknownReferenceIsRefusedOnItsField(array $body, string $field): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => $body + ['name' => "tpl-{$this->tag}", 'alias' => 'alias']]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn($field, $response);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', ["tpl-{$this->tag}"]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideUnknownReferences(): iterable
    {
        $unknown = \PHP_INT_MAX >> 33;

        yield 'check command' => [['check_options' => ['command_id' => $unknown]], 'check_options.command_id'];

        yield 'check time period' => [['scheduling_options' => ['check_timeperiod_id' => $unknown]], 'scheduling_options.check_timeperiod_id'];

        yield 'notification time period' => [['notifications' => ['timeperiod_id' => $unknown]], 'notifications.timeperiod_id'];

        yield 'timezone' => [['timezone_id' => $unknown], 'timezone_id'];

        yield 'severity' => [['severity_id' => $unknown], 'severity_id'];

        yield 'category' => [['category_ids' => [$unknown]], 'category_ids'];

        yield 'contact' => [['notifications' => ['contacts' => [$unknown]]], 'notifications.contacts'];

        yield 'contact group' => [['notifications' => ['contact_groups' => [$unknown]]], 'notifications.contact_groups'];

        yield 'icon' => [['extended_informations' => ['icon_id' => $unknown]], 'extended_informations.icon_id'];

        yield 'event handler command' => [['data_processing' => ['event_handler_command_id' => $unknown]], 'data_processing.event_handler_command_id'];
    }

    public function testTheValuesOnTheBoundsAreAccepted(): void
    {
        $this->login();

        $id = $this->create([
            'name' => "bounds-{$this->tag}",
            'alias' => str_repeat('a', 200),
            'scheduling_options' => ['max_check_attempts' => 1, 'normal_check_interval' => 2147483647, 'retry_check_interval' => 1],
            'data_processing' => ['acknowledgment_timeout' => 1, 'freshness_threshold' => 0, 'low_flap_threshold' => 0, 'high_flap_threshold' => 100],
            'extended_informations' => [
                'note' => str_repeat('n', 512),
                'note_url' => str_repeat('u', 2048),
                'action_url' => str_repeat('u', 2048),
                'alt_icon' => str_repeat('a', 200),
                'comment' => str_repeat('c', 65535),
            ],
            'notifications' => ['interval' => 0, 'first_delay' => 0, 'recovery_delay' => 0],
        ]);

        $row = $this->connection->fetchAssociative('SELECT host_check_interval, host_high_flap_threshold FROM host WHERE host_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame([2147483647, 100], [$this->asInt($row['host_check_interval']), $this->asInt($row['host_high_flap_threshold'])]);
    }

    public function testAReservedMacroNameIsRefused(): void
    {
        if ($this->connection->fetchOne('SELECT 1 FROM nagios_macro WHERE macro_name = ?', ['$_HOSTSNMPCOMMUNITY$']) === false) {
            $this->connection->insert('nagios_macro', ['macro_name' => '$_HOSTSNMPCOMMUNITY$']);
        }
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => [
            'name' => "macro-{$this->tag}",
            'alias' => 'alias',
            'check_options' => ['macros' => [['name' => 'snmpcommunity', 'value' => 'x', 'is_password' => true]]],
        ]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('check_options.macros[0].name', $response);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', ["macro-{$this->tag}"]));
    }

    public function testAnInheritedMacroResentUnchangedIsNotStored(): void
    {
        $parentId = $this->insertTemplate('parent');
        $this->connection->insert('on_demand_macro_host', ['host_host_id' => $parentId, 'host_macro_name' => '$_HOSTPORT$', 'host_macro_value' => '80']);
        $macroId = (int) $this->connection->lastInsertId();
        $this->login();

        $id = $this->create([
            'name' => "inherits-{$this->tag}",
            'alias' => 'alias',
            'template_ids' => [$parentId],
            'check_options' => ['macros' => [['id' => $macroId, 'parent' => 'template', 'name' => 'PORT', 'value' => '80']]],
        ]);

        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM on_demand_macro_host WHERE host_host_id = ?', [$id]));
    }

    public function testAParentInsideAStoredLoopIsAccepted(): void
    {
        $first = $this->insertTemplate('loop-first');
        $second = $this->insertTemplate('loop-second');
        $this->connection->insert('host_template_relation', ['host_host_id' => $first, 'host_tpl_id' => $second, '`order`' => 1]);
        $this->connection->insert('host_template_relation', ['host_host_id' => $second, 'host_tpl_id' => $first, '`order`' => 1]);
        $this->login();

        $this->create(['name' => "on-loop-{$this->tag}", 'alias' => 'alias', 'template_ids' => [$first]]);
    }

    public function testNoPollerIsFlagged(): void
    {
        $pollerId = $this->insertRow('nagios_server', ['name' => 'poller-' . Uuid::v4()->toBase58(), 'ns_ip_address' => '127.0.0.1', 'uid' => random_int(1, \PHP_INT_MAX), 'updated' => '0']);
        $this->login();

        $this->create(['name' => "no-flag-{$this->tag}", 'alias' => 'alias']);

        self::assertSame('0', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    public function testAVaultFailureAnswers502AndSavesNothing(): void
    {
        $vault = new FakeVault();
        $vault->writeThrows = true;
        self::getContainer()->set(VaultInterface::class, $vault);
        $this->login();

        $this->request('POST', self::URI, ['headers' => self::JSON, 'json' => ['name' => "vault-{$this->tag}", 'alias' => 'alias', 'snmp_community' => 'public']]);

        self::assertResponseStatusCodeSame(502);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', ["vault-{$this->tag}"]));
    }

    public function testAMacroReferringToNothingInheritedIsRefusedOnCheckOptions(): void
    {
        $this->login();

        $response = $this->request('POST', self::URI, ['json' => [
            'name' => "macro-ref-{$this->tag}",
            'alias' => 'alias',
            'check_options' => ['macros' => [['id' => 2147483647, 'parent' => 'template', 'name' => 'X', 'value' => 'y']]],
        ]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn('check_options', $response);
    }

    public function testTheCreationIsLoggedWithItsDetails(): void
    {
        $this->login();
        $name = "logged-{$this->tag}";

        $this->create(['name' => $name, 'alias' => 'Logged', 'scheduling_options' => ['max_check_attempts' => 4]]);

        $actions = $this->realTimeConnection->fetchFirstColumn("SELECT action_type FROM log_action WHERE object_name = ? AND object_type = 'host'", [$name]);
        self::assertSame(['a'], $actions);
        $details = $this->realTimeConnection->fetchAllKeyValue(
            'SELECT lam.field_name, lam.field_value
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_name = ?',
            [$name],
        );
        self::assertSame('Logged', $details['host_alias'] ?? null);
        self::assertSame('4', $details['host_max_check_attempts'] ?? null);
        self::assertSame('2', $details['host_notifications_enabled'] ?? null);
        self::assertArrayNotHasKey('host_address', $details);
    }

    public function testOnACloudPlatformNoNotificationFieldIsLogged(): void
    {
        $this->forceCloudPlatform();
        $this->login();
        $name = "cloud-{$this->tag}";

        $this->create(['name' => $name, 'alias' => 'Cloud']);

        $fields = $this->realTimeConnection->fetchFirstColumn(
            'SELECT lam.field_name
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_name = ?',
            [$name],
        );
        self::assertContains('host_alias', $fields);
        self::assertNotContains('host_notifications_enabled', $fields);
    }

    public function testItGrantsNoRealTimeAclAccess(): void
    {
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        $id = $this->create(['name' => "no-acl-{$this->tag}", 'alias' => 'alias']);

        self::assertFalse($this->realTimeConnection->fetchOne('SELECT 1 FROM centreon_acl WHERE host_id = ?', [$id]));
    }

    public function testEveryFieldIsStoredInItsColumn(): void
    {
        $checkCommandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $handlerId = $this->insertRow('command', ['command_name' => "handler-{$this->tag}", 'command_line' => '$USER1$/handler', 'command_type' => 2]);
        $checkPeriodId = $this->insertRow('timeperiod', ['tp_name' => "check-period-{$this->tag}", 'tp_alias' => 'check']);
        $notificationPeriodId = $this->insertRow('timeperiod', ['tp_name' => "notification-period-{$this->tag}", 'tp_alias' => 'notification']);
        $timezoneId = $this->insertRow('timezone', ['timezone_name' => "zone-{$this->tag}", 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);
        $severityId = $this->insertRow('hostcategories', ['hc_name' => "severity-{$this->tag}", 'hc_alias' => 'severity', 'hc_activate' => '1', 'level' => 1]);
        $directoryId = $this->insertRow('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $iconId = $this->insertRow('view_img', ['img_name' => "icon-{$this->tag}", 'img_path' => "icon-{$this->tag}.png"]);
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $directoryId, 'img_img_id' => $iconId]);
        $contactGroupId = $this->insertRow('contactgroup', ['cg_name' => "group-{$this->tag}", 'cg_alias' => 'group', 'cg_type' => 'local', 'cg_activate' => '1']);
        $this->login();

        $id = $this->create([
            'name' => "mapped-{$this->tag}",
            'alias' => 'Mapped',
            'snmp_version' => '3',
            'timezone_id' => $timezoneId,
            'severity_id' => $severityId,
            'check_options' => ['command_id' => $checkCommandId, 'args' => ['-c', '95']],
            'extended_informations' => [
                'note_url' => 'https://notes.example.com',
                'note' => 'A note',
                'action_url' => 'https://action.example.com',
                'icon_id' => $iconId,
                'alt_icon' => 'An icon',
                'comment' => 'A comment',
            ],
            'scheduling_options' => [
                'check_timeperiod_id' => $checkPeriodId,
                'max_check_attempts' => 3,
                'normal_check_interval' => 5,
                'retry_check_interval' => 7,
                'active_check_enabled' => 'true',
                'passive_check_enabled' => 'false',
            ],
            'data_processing' => [
                'check_freshness' => 'false',
                'freshness_threshold' => 11,
                'event_handler_enabled' => 'true',
                'event_handler_command_id' => $handlerId,
                'event_handler_args' => ['restart'],
                'acknowledgment_timeout' => 13,
                'flap_detection_enabled' => 'true',
                'low_flap_threshold' => 17,
                'high_flap_threshold' => 19,
            ],
            'notifications' => [
                'enabled' => 'false',
                'contact_groups' => [$contactGroupId],
                'options' => ['unreachable', 'flapping', 'downtime_scheduled'],
                'interval' => 23,
                'timeperiod_id' => $notificationPeriodId,
                'first_delay' => 29,
                'recovery_delay' => 31,
            ],
        ]);

        $host = $this->connection->fetchAssociative(
            'SELECT host_alias, host_snmp_version, host_location, command_command_id, command_command_id_arg1,
                    timeperiod_tp_id, host_max_check_attempts, host_check_interval, host_retry_check_interval,
                    host_active_checks_enabled, host_passive_checks_enabled, host_check_freshness, host_freshness_threshold,
                    host_event_handler_enabled, command_command_id2, command_command_id_arg2, host_acknowledgement_timeout,
                    host_flap_detection_enabled, host_low_flap_threshold, host_high_flap_threshold, host_comment,
                    host_notifications_enabled, host_notification_options, host_notification_interval, timeperiod_tp_id2,
                    host_first_notification_delay, host_recovery_notification_delay
             FROM host WHERE host_id = ?',
            [$id],
        );
        self::assertIsArray($host);
        self::assertSame(
            [
                'host_alias' => 'Mapped',
                'host_snmp_version' => '3',
                'host_location' => (string) $timezoneId,
                'command_command_id' => (string) $checkCommandId,
                'command_command_id_arg1' => '!-c!95',
                'timeperiod_tp_id' => (string) $checkPeriodId,
                'host_max_check_attempts' => '3',
                'host_check_interval' => '5',
                'host_retry_check_interval' => '7',
                'host_active_checks_enabled' => '1',
                'host_passive_checks_enabled' => '0',
                'host_check_freshness' => '0',
                'host_freshness_threshold' => '11',
                'host_event_handler_enabled' => '1',
                'command_command_id2' => (string) $handlerId,
                'command_command_id_arg2' => '!restart',
                'host_acknowledgement_timeout' => '13',
                'host_flap_detection_enabled' => '1',
                'host_low_flap_threshold' => '17',
                'host_high_flap_threshold' => '19',
                'host_comment' => 'A comment',
                'host_notifications_enabled' => '0',
                'host_notification_options' => 'u,f,s',
                'host_notification_interval' => '23',
                'timeperiod_tp_id2' => (string) $notificationPeriodId,
                'host_first_notification_delay' => '29',
                'host_recovery_notification_delay' => '31',
            ],
            array_map($this->asNullableString(...), $host),
        );

        $extended = $this->connection->fetchAssociative(
            'SELECT ehi_notes_url, ehi_notes, ehi_action_url, ehi_icon_image, ehi_icon_image_alt FROM extended_host_information WHERE host_host_id = ?',
            [$id],
        );
        self::assertIsArray($extended);
        self::assertSame(
            ['https://notes.example.com', 'A note', 'https://action.example.com', (string) $iconId, 'An icon'],
            array_map($this->asNullableString(...), array_values($extended)),
        );
        self::assertSame($severityId, $this->asInt($this->connection->fetchOne('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ?', [$id])));
        self::assertSame($contactGroupId, $this->asInt($this->connection->fetchOne('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', [$id])));
    }

    /**
     * IS_CLOUD_PLATFORM is fixed for the whole kernel: the processor is rebuilt with the flag forced.
     */
    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();
        /** @var CommandBus $commandBus */
        $commandBus = $container->get(CommandBus::class);
        /** @var Security $security */
        $security = $container->get(Security::class);
        /** @var HostTemplateRepository $repository */
        $repository = $container->get(HostTemplateRepository::class);
        /** @var HostTemplateResourceBuilder $resourceBuilder */
        $resourceBuilder = $container->get(HostTemplateResourceBuilder::class);

        $container->set(
            CreateHostTemplateProcessor::class,
            new CreateHostTemplateProcessor($commandBus, $security, $repository, $resourceBuilder, isCloudPlatform: true),
        );
    }

    private function assertRefusedOn(string $field, ResponseInterface $response): void
    {
        $message = $response->toArray(false)['message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString("[{$field}]", $message);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body): int
    {
        $response = $this->request('POST', self::URI, ['headers' => self::JSON, 'json' => $body]);
        self::assertResponseStatusCodeSame(201, $response->getContent(false));
        $id = $response->toArray()['id'];
        Assert::integer($id);

        return $id;
    }

    private function insertTemplate(string $prefix): int
    {
        return $this->insertRow('host', ['host_name' => "{$prefix}-{$this->tag}", 'host_register' => '0', 'host_activate' => '1']);
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertRow(string $table, array $columns): int
    {
        $this->connection->insert($table, $columns);

        return (int) $this->connection->lastInsertId();
    }

    private function asNullableString(mixed $value): ?string
    {
        self::assertTrue($value === null || is_scalar($value), 'Expected a scalar column value.');

        return $value === null ? null : (string) $value;
    }

    private function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
