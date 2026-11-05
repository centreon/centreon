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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;
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
        self::assertSame(self::URI . '/' . self::asInt($body['id']), $body['@id']);
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

    public function testInnerSpacesBecomeUnderscores(): void
    {
        $this->login();

        $id = $this->create(['name' => "  my template {$this->tag} ", 'alias' => 'alias']);

        self::assertSame("my_template_{$this->tag}", $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$id]));
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

        yield 'an alias over 200' => [['alias' => str_repeat('a', 201)], 'alias'];
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

    public function testArgumentsWithoutACommandAreKept(): void
    {
        $this->login();

        $id = $this->create(['name' => "args-{$this->tag}", 'alias' => 'alias', 'check_options' => ['command_id' => null, 'args' => ['-w', '80']]]);

        $checkOptions = $this->request('GET', self::URI . '/' . $id, ['headers' => self::JSON])->toArray()['check_options'];
        self::assertSame(['args' => ['-w', '80'], 'macros' => []], $checkOptions);
    }

    public function testAMacroNamedLikeAStandardMacroIsAccepted(): void
    {
        $this->login();

        $id = $this->create(['name' => "macro-{$this->tag}", 'alias' => 'alias', 'check_options' => ['macros' => [['name' => 'HOSTADDRESS', 'value' => 'x']]]]);

        self::assertSame('$_HOSTHOSTADDRESS$', $this->connection->fetchOne('SELECT host_macro_name FROM on_demand_macro_host WHERE host_host_id = ?', [$id]));
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
        $this->login();
        $before = $this->connection->fetchFirstColumn("SELECT id FROM nagios_server WHERE updated = '1' ORDER BY id");

        $this->create(['name' => "no-flag-{$this->tag}", 'alias' => 'alias']);

        self::assertSame($before, $this->connection->fetchFirstColumn("SELECT id FROM nagios_server WHERE updated = '1' ORDER BY id"));
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
        self::assertArrayNotHasKey('host_address', $details);
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

    private function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
