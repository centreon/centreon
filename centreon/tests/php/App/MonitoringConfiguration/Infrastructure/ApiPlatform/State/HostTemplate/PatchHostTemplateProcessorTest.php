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

use App\Shared\Domain\VaultInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\Shared\ApiTestCase;
use Tests\App\Shared\Double\FakeVault;

final class PatchHostTemplateProcessorTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    private const URI = '/api/configuration/host_templates';
    private const MERGE_PATCH = 'application/merge-patch+json';

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

    public function testItSavesOnlyTheSentKeysAndAnswersNoContent(): void
    {
        $id = $this->insertTemplate('saved', ['host_alias' => 'Old alias', 'host_snmp_community' => 'public', 'host_max_check_attempts' => 3, 'host_check_interval' => 5]);
        $this->login();

        $response = $this->patch($id, ['alias' => 'New alias', 'scheduling_options' => ['max_check_attempts' => 4]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        $detail = $this->request('GET', $this->uri($id), ['headers' => ['accept' => 'application/json']])->toArray();
        self::assertSame("saved-{$this->tag}", $detail['name']);
        self::assertSame('New alias', $detail['alias']);
        self::assertTrue($detail['is_snmp_community_defined']);
        self::assertIsArray($detail['scheduling_options']);
        self::assertSame(4, $detail['scheduling_options']['max_check_attempts']);
        self::assertSame(5, $detail['scheduling_options']['normal_check_interval']);
        self::assertSame('public', $this->connection->fetchOne('SELECT host_snmp_community FROM host WHERE host_id = ?', [$id]));
    }

    public function testItRequiresAuthentication(): void
    {
        $id = $this->insertTemplate('anonymous');

        $this->patch($id, ['alias' => 'alias']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAHostTemplateReadOnlyUser(): void
    {
        $id = $this->insertTemplate('read-only');
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $response = $this->patch($id, ['alias' => 'alias']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('You are not allowed to update host templates', $response->toArray(false)['message']);
    }

    public function testAHostTemplateReadWriteUserSavesIt(): void
    {
        $id = $this->insertTemplate('read-write');
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        $this->patch($id, ['alias' => 'New alias']);

        self::assertResponseStatusCodeSame(204);
    }

    #[DataProvider('provideIdsThatAreNoTemplate')]
    public function testAnIdThatIsNoTemplateIsNotFound(string $id): void
    {
        $this->login();

        $this->request('PATCH', self::URI . '/' . $id, ['headers' => ['Content-Type' => self::MERGE_PATCH], 'json' => ['alias' => 'alias']]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIdsThatAreNoTemplate(): iterable
    {
        yield 'unknown' => ['2147483647'];

        yield 'zero' => ['0'];

        yield 'not a number' => ['abc'];
    }

    public function testAHostIsNotFound(): void
    {
        $hostId = $this->insertRow('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_alias' => 'Host']);
        $this->login();

        $this->patch($hostId, ['alias' => 'alias']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Host', $this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testAPlainJsonBodyIsAnUnsupportedMediaType(): void
    {
        $id = $this->insertTemplate('plain-json');
        $this->login();

        $this->request('PATCH', $this->uri($id), ['headers' => ['Content-Type' => 'application/json'], 'json' => ['alias' => 'alias']]);

        self::assertResponseStatusCodeSame(415);
    }

    public function testALockedTemplateIsRefused(): void
    {
        $id = $this->insertTemplate('locked', ['host_alias' => 'Locked', 'host_locked' => 1]);
        $this->login();

        $this->patch($id, ['alias' => 'Changed']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Locked', $this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$id]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideRemovedKeys')]
    public function testAKeyTheTemplateDoesNotHaveIsABadRequest(array $body): void
    {
        $id = $this->insertTemplate('removed-key', ['host_alias' => 'Kept']);
        $this->login();

        $this->patch($id, $body + ['alias' => 'Changed']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Kept', $this->connection->fetchOne('SELECT host_alias FROM host WHERE host_id = ?', [$id]));
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

        yield 'is_locked' => [['is_locked' => false]];

        yield 'extended_informations.geo_coordinates' => [['extended_informations' => ['geo_coordinates' => '48.8,2.3']]];
    }

    #[DataProvider('provideFieldsThatCannotBeCleared')]
    public function testANullOnAFieldThatCannotBeClearedIsRefused(string $field): void
    {
        $id = $this->insertTemplate('not-clearable', ['host_alias' => 'Kept']);
        $this->login();

        $response = $this->patch($id, [$field => null]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn($field, $response);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFieldsThatCannotBeCleared(): iterable
    {
        foreach (['name', 'alias', 'check_options', 'extended_informations', 'scheduling_options', 'data_processing', 'notifications'] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('provideAdditiveInheritanceFlags')]
    public function testANullAdditiveInheritanceFlagIsRefused(string $flag): void
    {
        $id = $this->insertTemplate('additive-null');
        $this->login();

        $response = $this->patch($id, ['notifications' => [$flag => null]]);

        self::assertResponseStatusCodeSame(422);
        $this->assertRefusedOn("notifications.{$flag}", $response);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAdditiveInheritanceFlags(): iterable
    {
        foreach (['contact_additive_inheritance', 'contact_group_additive_inheritance'] as $flag) {
            yield $flag => [$flag];
        }
    }

    public function testNullClearsTheClearableFields(): void
    {
        $timezoneId = $this->insertRow('timezone', ['timezone_name' => "Zone/{$this->tag}", 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);
        $severityId = $this->insertRow('hostcategories', ['hc_name' => "severity-{$this->tag}", 'hc_alias' => 'severity', 'hc_activate' => '1', 'level' => 1]);
        $categoryId = $this->insertRow('hostcategories', ['hc_name' => "category-{$this->tag}", 'hc_alias' => 'category', 'hc_activate' => '1']);
        $id = $this->insertTemplate('cleared', [
            'host_snmp_version' => '2c',
            'host_location' => $timezoneId,
            'host_max_check_attempts' => 3,
            'host_notification_interval' => 30,
        ]);
        $this->insertRow('extended_host_information', ['host_host_id' => $id, 'ehi_notes' => 'A note', 'ehi_notes_url' => 'https://notes.example.com']);
        foreach ([$severityId, $categoryId] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $this->login();

        $this->patch($id, [
            'snmp_version' => null,
            'timezone_id' => null,
            'severity_id' => null,
            'extended_informations' => ['note' => null],
            'scheduling_options' => ['max_check_attempts' => null],
            'notifications' => ['interval' => null],
        ]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_snmp_version, host_location, host_max_check_attempts, host_notification_interval FROM host WHERE host_id = ?', [$id]);
        self::assertSame(['host_snmp_version' => null, 'host_location' => null, 'host_max_check_attempts' => null, 'host_notification_interval' => null], $row);
        self::assertSame(
            ['ehi_notes' => null, 'ehi_notes_url' => 'https://notes.example.com'],
            $this->connection->fetchAssociative('SELECT ehi_notes, ehi_notes_url FROM extended_host_information WHERE host_host_id = ?', [$id]),
        );
        self::assertSame([$categoryId], $this->categoryIds($id));
    }

    public function testANullTriStateMeansTheDefault(): void
    {
        $id = $this->insertTemplate('tri-state', ['host_active_checks_enabled' => '1', 'host_notifications_enabled' => '0']);
        $this->login();

        $this->patch($id, ['scheduling_options' => ['active_check_enabled' => null], 'notifications' => ['enabled' => null]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['host_active_checks_enabled' => '2', 'host_notifications_enabled' => '2'],
            $this->connection->fetchAssociative('SELECT host_active_checks_enabled, host_notifications_enabled FROM host WHERE host_id = ?', [$id]),
        );
    }

    public function testTheActivationIsNeverWritten(): void
    {
        $id = $this->insertTemplate('inactive', ['host_activate' => '0']);
        $this->login();

        $this->patch($id, ['alias' => 'New alias']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('0', $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$id]));
    }

    public function testAnUnchangedSaveLogsNothingAndFlagsNoPoller(): void
    {
        $id = $this->insertTemplate('unchanged', ['host_alias' => 'Same', 'host_max_check_attempts' => 3]);
        $this->insertRow('extended_host_information', ['host_host_id' => $id, 'ehi_notes' => ' padded note ']);
        $pollerId = $this->insertPoller();
        $this->insertHostOn($id, $pollerId);
        $this->login();

        $this->patch($id, [
            'name' => "unchanged-{$this->tag}",
            'alias' => 'Same',
            'extended_informations' => ['note' => ' padded note '],
            'scheduling_options' => ['max_check_attempts' => 3],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->logRowCount($id));
        self::assertSame('0', $this->pollerUpdated($pollerId));
    }

    public function testAnUnchangedSaveOfEveryKeyLogsNothingAndFlagsNoPoller(): void
    {
        $commandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $eventHandlerId = $this->insertRow('command', ['command_name' => "handler-{$this->tag}", 'command_line' => '$USER1$/handle', 'command_type' => 3]);
        $periodId = $this->insertRow('timeperiod', ['tp_name' => "period-{$this->tag}", 'tp_alias' => 'period']);
        $timezoneId = $this->insertRow('timezone', ['timezone_name' => "Zone/{$this->tag}", 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);
        $severityId = $this->insertRow('hostcategories', ['hc_name' => "severity-{$this->tag}", 'hc_alias' => 'severity', 'hc_activate' => '1', 'level' => 1]);
        $iconId = $this->insertMedia("icon-{$this->tag}.png");
        $id = $this->insertTemplate('every-key', [
            'host_alias' => 'Every key',
            'host_snmp_version' => '2c',
            'host_location' => $timezoneId,
            'host_comment' => 'A comment',
            'command_command_id' => $commandId,
            'command_command_id_arg1' => '!-w!80',
            'timeperiod_tp_id' => $periodId,
            'host_max_check_attempts' => 3,
            'host_check_interval' => 5,
            'host_retry_check_interval' => 1,
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_check_freshness' => '1',
            'host_freshness_threshold' => 60,
            'host_flap_detection_enabled' => '2',
            'host_low_flap_threshold' => 10,
            'host_high_flap_threshold' => 90,
            'host_event_handler_enabled' => '0',
            'command_command_id2' => $eventHandlerId,
            'command_command_id_arg2' => '!a!b',
            'host_acknowledgement_timeout' => 15,
            'host_notifications_enabled' => '1',
            'host_notification_interval' => 30,
            'timeperiod_tp_id2' => $periodId,
            'host_first_notification_delay' => 2,
            'host_recovery_notification_delay' => 3,
            'contact_additive_inheritance' => 0,
            'cg_additive_inheritance' => 0,
        ]);
        $this->insertRow('extended_host_information', [
            'host_host_id' => $id,
            'ehi_notes' => 'A note',
            'ehi_notes_url' => 'https://notes.example.com',
            'ehi_action_url' => 'https://action.example.com',
            'ehi_icon_image' => $iconId,
            'ehi_icon_image_alt' => 'An icon',
        ]);
        $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $severityId]);
        $pollerId = $this->insertPoller();
        $this->insertHostOn($id, $pollerId);
        $this->login();

        $this->patch($id, [
            'name' => "every-key-{$this->tag}",
            'alias' => 'Every key',
            'snmp_version' => '2c',
            'timezone_id' => $timezoneId,
            'severity_id' => $severityId,
            'check_options' => ['command_id' => $commandId, 'args' => ['-w', '80']],
            'extended_informations' => [
                'icon_id' => $iconId,
                'note' => 'A note',
                'note_url' => 'https://notes.example.com',
                'action_url' => 'https://action.example.com',
                'alt_icon' => 'An icon',
                'comment' => 'A comment',
            ],
            'scheduling_options' => [
                'check_timeperiod_id' => $periodId,
                'max_check_attempts' => 3,
                'normal_check_interval' => 5,
                'retry_check_interval' => 1,
                'active_check_enabled' => 'true',
                'passive_check_enabled' => 'false',
            ],
            'data_processing' => [
                'check_freshness' => 'true',
                'freshness_threshold' => 60,
                'flap_detection_enabled' => 'use_default',
                'low_flap_threshold' => 10,
                'high_flap_threshold' => 90,
                'event_handler_enabled' => 'false',
                'event_handler_command_id' => $eventHandlerId,
                'event_handler_args' => ['a', 'b'],
                'acknowledgment_timeout' => 15,
            ],
            'notifications' => [
                'enabled' => 'true',
                'interval' => 30,
                'timeperiod_id' => $periodId,
                'first_delay' => 2,
                'recovery_delay' => 3,
                'contact_additive_inheritance' => false,
                'contact_group_additive_inheritance' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->logRowCount($id));
        self::assertSame('0', $this->pollerUpdated($pollerId));
    }

    public function testTheExtendedTextsAreStoredTrimmed(): void
    {
        $id = $this->insertTemplate('trimmed');
        $this->login();

        $this->patch($id, ['extended_informations' => [
            'note' => ' a note ',
            'note_url' => ' https://notes.example.com ',
            'action_url' => ' https://action.example.com ',
            'alt_icon' => ' an icon ',
            'comment' => ' a comment ',
        ]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['ehi_notes' => 'a note', 'ehi_notes_url' => 'https://notes.example.com', 'ehi_action_url' => 'https://action.example.com', 'ehi_icon_image_alt' => 'an icon'],
            $this->connection->fetchAssociative('SELECT ehi_notes, ehi_notes_url, ehi_action_url, ehi_icon_image_alt FROM extended_host_information WHERE host_host_id = ?', [$id]),
        );
        self::assertSame('a comment', $this->connection->fetchOne('SELECT host_comment FROM host WHERE host_id = ?', [$id]));
    }

    public function testAConfigurationChangeIsLoggedAndFlagsThePollersOfEveryEnabledInheritingHost(): void
    {
        $id = $this->insertTemplate('changed', ['host_alias' => 'Old']);
        $childId = $this->insertTemplate('child');
        $this->connection->insert('host_template_relation', ['host_host_id' => $childId, 'host_tpl_id' => $id, '`order`' => 1]);
        $directPollerId = $this->insertPoller();
        $indirectPollerId = $this->insertPoller();
        $disabledPollerId = $this->insertPoller();
        $this->insertHostOn($id, $directPollerId);
        $this->insertHostOn($childId, $indirectPollerId);
        $this->insertHostOn($id, $disabledPollerId, '0');
        $this->login();

        $this->patch($id, ['alias' => 'New', 'scheduling_options' => ['max_check_attempts' => 4]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['c'], $this->realTimeConnection->fetchFirstColumn("SELECT action_type FROM log_action WHERE object_id = ? AND object_type = 'host'", [$id]));
        $details = $this->realTimeConnection->fetchAllKeyValue(
            "SELECT lam.field_name, lam.field_value
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_id = ? AND la.object_type = 'host'",
            [$id],
        );
        self::assertSame('New', $details['host_alias'] ?? null);
        self::assertSame('4', $details['host_max_check_attempts'] ?? null);
        self::assertSame(['1', '1', '0'], [$this->pollerUpdated($directPollerId), $this->pollerUpdated($indirectPollerId), $this->pollerUpdated($disabledPollerId)]);
    }

    public function testRemovingTheCommandWhileSendingArgumentsIsRefused(): void
    {
        $commandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $id = $this->insertTemplate('arguments', ['command_command_id' => $commandId, 'command_command_id_arg1' => '!-w!80']);
        $this->login();

        $this->patch($id, ['check_options' => ['command_id' => null, 'args' => ['-w', '80']]]);

        self::assertResponseStatusCodeSame(422);
        $row = $this->connection->fetchAssociative('SELECT command_command_id, command_command_id_arg1 FROM host WHERE host_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame([$commandId, '!-w!80'], [$this->asInt($row['command_command_id']), $row['command_command_id_arg1']]);
    }

    public function testRemovingOnlyTheCommandClearsTheStoredArguments(): void
    {
        $commandId = $this->insertRow('command', ['command_name' => "check-{$this->tag}", 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $id = $this->insertTemplate('command-only', ['command_command_id' => $commandId, 'command_command_id_arg1' => '!-w!80']);
        $this->login();

        $this->patch($id, ['check_options' => ['command_id' => null]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['command_command_id' => null, 'command_command_id_arg1' => null],
            $this->connection->fetchAssociative('SELECT command_command_id, command_command_id_arg1 FROM host WHERE host_id = ?', [$id]),
        );
    }

    public function testArgumentsWithoutACommandAreRefused(): void
    {
        $id = $this->insertTemplate('arguments-alone');
        $this->login();

        $this->patch($id, ['check_options' => ['args' => ['-w', '80']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->connection->fetchOne('SELECT command_command_id_arg1 FROM host WHERE host_id = ?', [$id]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideValuesOutOfTheBounds')]
    public function testAValueOutOfTheBoundsIsRefusedOnItsField(array $body, string $field): void
    {
        $id = $this->insertTemplate('bounds');
        $this->login();

        $response = $this->patch($id, $body);

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

        yield 'arguments over 65535 bytes' => [['check_options' => ['args' => [str_repeat('a', 65536)]]], 'check_options.args'];

        yield 'snmp community 256' => [['snmp_community' => str_repeat('s', 256)], 'snmp_community'];

        yield 'a name over 200' => [['name' => str_repeat('n', 201)], 'name'];

        yield 'an alias over 200' => [['alias' => str_repeat('a', 201)], 'alias'];

        yield 'an unknown timezone' => [['timezone_id' => 2147483647], 'timezone_id'];
    }

    public function testTheValuesOnTheBoundsAreAccepted(): void
    {
        $id = $this->insertTemplate('on-bounds');
        $this->login();

        $this->patch($id, [
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

        self::assertResponseStatusCodeSame(204);
        self::assertSame(2147483647, $this->asInt($this->connection->fetchOne('SELECT host_check_interval FROM host WHERE host_id = ?', [$id])));
    }

    public function testTheSnmpCommunityIsKeptClearedOrReplaced(): void
    {
        $id = $this->insertTemplate('snmp', ['host_snmp_community' => 'public']);
        $this->login();

        $this->patch($id, ['alias' => 'New alias']);
        self::assertSame('public', $this->snmpCommunity($id));

        $this->patch($id, ['snmp_community' => ' private ']);
        self::assertSame('private', $this->snmpCommunity($id));

        $this->patch($id, ['snmp_community' => null]);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->snmpCommunity($id));
    }

    public function testANullSeverityKeepsOneTheRestrictedUserCannotSee(): void
    {
        $visibleCategoryId = $this->insertRow('hostcategories', ['hc_name' => "visible-{$this->tag}", 'hc_alias' => 'visible', 'hc_activate' => '1']);
        $severityId = $this->insertRow('hostcategories', ['hc_name' => "severity-{$this->tag}", 'hc_alias' => 'severity', 'hc_activate' => '1', 'level' => 1]);
        $id = $this->insertTemplate('scoped');
        $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $severityId]);
        $aclGroupId = $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);
        $aclResourceId = $this->insertRow('acl_resources', ['acl_res_name' => "res-{$this->tag}", 'acl_res_alias' => "res-{$this->tag}", 'acl_res_activate' => '1']);
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $aclResourceId, 'acl_group_id' => $aclGroupId]);
        $this->connection->insert('acl_resources_hc_relations', ['acl_res_id' => $aclResourceId, 'hc_id' => $visibleCategoryId]);

        $this->patch($id, ['severity_id' => null, 'alias' => 'New alias']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([$severityId], $this->categoryIds($id));
    }

    public function testTheCurrentNameIsAccepted(): void
    {
        $id = $this->insertTemplate('current');
        $this->login();

        $this->patch($id, ['name' => "current-{$this->tag}", 'alias' => 'New alias']);

        self::assertResponseStatusCodeSame(204);
    }

    #[DataProvider('provideNameOwners')]
    public function testANameUsedByAHostOrAnotherTemplateIsAConflict(string $register): void
    {
        $this->insertRow('host', ['host_name' => "taken-{$this->tag}", 'host_register' => $register]);
        $id = $this->insertTemplate('renamed');
        $this->login();

        $this->patch($id, ['name' => " taken-{$this->tag} "]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame("renamed-{$this->tag}", $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$id]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNameOwners(): iterable
    {
        yield 'a host' => ['1'];

        yield 'a template' => ['0'];
    }

    public function testACaseOnlyRenameIsSaved(): void
    {
        $id = $this->insertTemplate('case');
        $this->login();

        $this->patch($id, ['name' => 'CASE-' . mb_strtoupper($this->tag)]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('CASE-' . mb_strtoupper($this->tag), $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$id]));
    }

    public function testAVaultFailureAnswers502AndSavesNothing(): void
    {
        $vault = new FakeVault();
        $vault->writeThrows = true;
        self::getContainer()->set(VaultInterface::class, $vault);
        $id = $this->insertTemplate('vault', ['host_snmp_community' => 'public']);
        $this->login();

        $this->patch($id, ['alias' => 'New alias', 'snmp_community' => 'private']);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(
            ['host_alias' => 'vault', 'host_snmp_community' => 'public'],
            $this->connection->fetchAssociative('SELECT host_alias, host_snmp_community FROM host WHERE host_id = ?', [$id]),
        );
    }

    public function testAMonitoringAgentCommandForcesTheFreshnessCheck(): void
    {
        $commandId = $this->insertRow('command', ['command_name' => "check-Centreon-Monitoring-Agent-{$this->tag}", 'command_line' => 'cma', 'command_type' => 2, 'command_locked' => 1]);
        $id = $this->insertTemplate('agent', ['host_check_freshness' => '0', 'host_freshness_threshold' => 60]);
        $this->login();

        $this->patch($id, ['check_options' => ['command_id' => $commandId]]);

        self::assertResponseStatusCodeSame(204);
        $row = $this->connection->fetchAssociative('SELECT host_check_freshness, host_freshness_threshold FROM host WHERE host_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame(['1', 500], [$row['host_check_freshness'], $this->asInt($row['host_freshness_threshold'])]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(int $id, array $body): ResponseInterface
    {
        return $this->request('PATCH', $this->uri($id), ['headers' => ['Content-Type' => self::MERGE_PATCH], 'json' => $body]);
    }

    private function uri(int $id): string
    {
        return self::URI . '/' . $id;
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
        return $this->insertRow('host', $columns + ['host_name' => "{$prefix}-{$this->tag}", 'host_alias' => $prefix, 'host_register' => '0', 'host_activate' => '1']);
    }

    private function insertMedia(string $name): int
    {
        $directoryId = $this->insertRow('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $imageId = $this->insertRow('view_img', ['img_name' => $name, 'img_path' => $name]);
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $directoryId, 'img_img_id' => $imageId]);

        return $imageId;
    }

    private function insertPoller(): int
    {
        return $this->insertRow('nagios_server', ['name' => 'poller-' . Uuid::v4()->toBase58(), 'ns_ip_address' => '127.0.0.1', 'uid' => random_int(1, \PHP_INT_MAX), 'updated' => '0']);
    }

    private function insertHostOn(int $templateId, int $pollerId, string $activate = '1'): void
    {
        $hostId = $this->insertRow('host', ['host_name' => 'host-' . Uuid::v4()->toBase58(), 'host_register' => '1', 'host_activate' => $activate]);
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => 1]);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);
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

    private function snmpCommunity(int $id): mixed
    {
        return $this->connection->fetchOne('SELECT host_snmp_community FROM host WHERE host_id = ?', [$id]);
    }

    /**
     * @return list<int>
     */
    private function categoryIds(int $id): array
    {
        return array_map(
            $this->asInt(...),
            $this->connection->fetchFirstColumn('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', [$id]),
        );
    }

    private function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
