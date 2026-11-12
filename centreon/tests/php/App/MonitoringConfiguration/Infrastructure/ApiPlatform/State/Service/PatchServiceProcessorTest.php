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

use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The HTTP contract of the service partial update: route, authentication and access gate, input validation before
 * the service is looked up, the fields and their side effects (activity log, poller and ACL flags).
 */
final class PatchServiceProcessorTest extends PatchServiceProcessorTestCase
{
    #[DataProvider('invalidIds')]
    public function testAnIdThatIsNotAPositiveIntegerIsNotRouted(string $id): void
    {
        // Unauthenticated on purpose: the router answers before the firewall.
        $this->patch($id, ['activated' => false]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'zero' => ['0'];

        yield 'non-numeric' => ['abc'];

        yield 'leading zero' => ['007'];
    }

    public function testItRequiresAuthentication(): void
    {
        $this->patch('1', ['activated' => false]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAReadOnlyUserIsForbidden(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        $this->loginAsNonAdmin(self::READ_ONLY);

        $this->patch($serviceId, ['name' => 'pong']);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to update services']);
        self::assertSame('ping', $this->column('service_description', $serviceId));
    }

    #[DataProvider('invalidBodies')]
    public function testAnInvalidBodyIsRefusedBeforeTheServiceIsLookedUp(string $body, string $message): void
    {
        $this->login();

        $this->patchRaw('999999999', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonEquals(['code' => 422, 'message' => $message]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'null activation' => ['{"activated": null}', "[activated] This value cannot be null.\n"];

        yield 'activation not a boolean' => ['{"activated": "x"}', "[activated] This value should be of type bool|null.\n"];

        yield 'nested field out of its bounds' => ['{"monitoring_engine": {"max_check_attempts": 0}}', "[monitoring_engine.max_check_attempts] This value should be greater than or equal to 1.\n"];

        yield 'reference that does not exist' => ['{"severity_id": 999999999}', "[severity_id] This service severity does not exist or is not accessible.\n"];
    }

    public function testAnotherContentTypeIsUnsupportedBeforeTheAccessGate(): void
    {
        // A read-only user: the content type is negotiated before the write right is checked.
        $this->loginAsNonAdmin(self::READ_ONLY);

        $this->request('PATCH', self::BASE_ENDPOINT . '/1', ['headers' => ['Content-Type' => 'application/json'], 'body' => '{"activated": false}']);

        self::assertResponseStatusCodeSame(415);
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        $this->login();

        $this->patch('999999999', ['activated' => false]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFound(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        // Deliberately not granted to the user's access group in the real-time ACL cache.
        $this->loginAsNonAdmin();

        $this->patch($serviceId, ['activated' => false]);

        // Out of scope reads as not found (no existence leak, not a 403), and nothing is changed.
        self::assertResponseStatusCodeSame(404);
        self::assertSame('1', $this->column('service_activate', $serviceId));
    }

    public function testItDisablesTheServiceWithItsSideEffects(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $serviceId = $this->insertService('ping', $hostId);
        $aclResourceId = $this->insertUnchangedAclResource();
        $this->login();

        $response = $this->patch($serviceId, ['activated' => false]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertSame('0', $this->column('service_activate', $serviceId));
        self::assertSame(
            [['action_type' => 'disable', 'object_name' => 'ping', 'details' => ['host_id' => (string) $hostId, 'service_name' => 'ping']]],
            $this->activityLogs($serviceId),
        );
        // An admin flags every ACL resource; the poller of the enabled host is flagged.
        self::assertSame(1, $this->aclResourceChanged($aclResourceId));
        self::assertSame('1', $this->pollerUpdated($pollerId));
    }

    /**
     * Only the keys sent are written: everything else keeps its stored value. The change is one mass-change line,
     * named after the service and carrying its whole form.
     */
    public function testItUpdatesOnlyTheSentFieldsAndLogsOneMassChangeLine(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $commandId = $this->insertCommand();
        $serviceId = $this->insertService('ping', $hostId, [
            'command_command_id' => $commandId,
            'command_command_id_arg' => '!80',
            'service_normal_check_interval' => 5,
            'service_comment' => 'kept comment',
        ]);
        $aclResourceId = $this->insertUnchangedAclResource();
        $this->login();

        $this->patch($serviceId, [
            'name' => ' pong ',
            'monitoring_engine' => ['max_check_attempts' => 4, 'note' => 'a note'],
            'notifications' => ['interval' => 0],
            'advanced' => ['low_flap_threshold' => 0, 'high_flap_threshold' => 100],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['pong', (string) $commandId, '!80', '4', '5', 'kept comment', '0', '0', '100'],
            array_map($this->text(...), $this->connection->fetchNumeric(
                'SELECT service_description, command_command_id, command_command_id_arg, service_max_check_attempts, service_normal_check_interval,
                        service_comment, service_notification_interval, service_low_flap_threshold, service_high_flap_threshold
                    FROM service WHERE service_id = ?',
                [$serviceId],
            ) ?: []),
        );
        self::assertSame('a note', $this->connection->fetchOne('SELECT esi_notes FROM extended_service_information WHERE service_service_id = ?', [$serviceId]));

        $logs = $this->activityLogs($serviceId);
        self::assertCount(1, $logs);
        self::assertSame(['mc', 'pong'], [$logs[0]['action_type'], $logs[0]['object_name']]);
        self::assertSame(
            ['pong', (string) $hostId, '!80', '4', '5', 'a note', 'kept comment'],
            [
                $logs[0]['details']['service_description'] ?? null,
                $logs[0]['details']['host_id'] ?? null,
                $logs[0]['details']['command_command_id_arg'] ?? null,
                $logs[0]['details']['service_max_check_attempts'] ?? null,
                $logs[0]['details']['service_normal_check_interval'] ?? null,
                $logs[0]['details']['esi_notes'] ?? null,
                $logs[0]['details']['service_comment'] ?? null,
            ],
        );
        self::assertSame(1, $this->aclResourceChanged($aclResourceId));
        self::assertSame('1', $this->pollerUpdated($pollerId));
    }

    /**
     * Null clears a value of each family: a text, a reference, a number, a tri-state and a list.
     */
    public function testNullClearsAValueOfEachFamily(): void
    {
        $commandId = $this->insertCommand();
        $templateId = $this->insertServiceTemplate($commandId);
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), [
            'command_command_id' => $commandId,
            'command_command_id_arg' => '!80',
            'service_template_model_stm_id' => $templateId,
            'service_max_check_attempts' => 3,
            'service_is_volatile' => '1',
        ]);
        $this->connection->update('extended_service_information', ['esi_notes' => 'a note'], ['service_service_id' => $serviceId]);
        $this->login();

        $this->patch($serviceId, [
            'service_template_id' => null,
            'check_options' => ['args' => []],
            'monitoring_engine' => ['max_check_attempts' => null, 'is_volatile' => null, 'note' => null],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            [null, null, null, '2', (string) $commandId],
            array_map($this->text(...), $this->connection->fetchNumeric(
                'SELECT service_template_model_stm_id, command_command_id_arg, service_max_check_attempts, service_is_volatile, command_command_id
                    FROM service WHERE service_id = ?',
                [$serviceId],
            ) ?: []),
        );
        self::assertNull($this->connection->fetchOne('SELECT esi_notes FROM extended_service_information WHERE service_service_id = ?', [$serviceId]));
    }

    /**
     * Nothing to change: no row, no log line, no poller flag, no ACL flag.
     */
    #[DataProvider('unchangingBodyProvider')]
    public function testABodyThatChangesNothingWritesNothing(string $body): void
    {
        $pollerId = $this->insertPoller();
        $serviceId = $this->insertService('ping', $this->insertHost($pollerId), ['service_max_check_attempts' => 3]);
        $aclResourceId = $this->insertUnchangedAclResource();
        $this->login();

        $this->patchRaw($serviceId, $body);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->activityLogs($serviceId));
        self::assertSame('0', $this->pollerUpdated($pollerId));
        self::assertSame(0, $this->aclResourceChanged($aclResourceId));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unchangingBodyProvider(): iterable
    {
        yield 'an empty body' => ['{}'];

        yield 'the stored values' => ['{"name": "ping", "activated": true, "monitoring_engine": {"max_check_attempts": 3}, "service_group_ids": [], "check_options": {"args": []}}'];
    }

    public function testActivatingWithAnotherChangeLogsOneMassChangeLine(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['service_activate' => '0']);
        $this->login();

        $this->patch($serviceId, ['activated' => true, 'name' => 'pong']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->column('service_activate', $serviceId));
        self::assertSame(['mc'], array_column($this->activityLogs($serviceId), 'action_type'));
    }

    /**
     * A non-admin flags their own access groups, not every ACL resource.
     */
    public function testARestrictedActorsGroupsAreFlagged(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $serviceId = $this->insertService('ping', $hostId);
        $aclResourceId = $this->insertUnchangedAclResource();
        ['groupId' => $groupId] = $this->loginAsNonAdmin();
        $this->grantPair($groupId, $hostId, $serviceId);

        $this->patch($serviceId, ['name' => 'pong']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->text($this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$groupId])));
        self::assertSame(0, $this->aclResourceChanged($aclResourceId));
    }

    /**
     * A non-admin moving a service: the poller of each host is flagged, and the service is granted at once on its
     * new host to the mover's groups, so it stays visible to them.
     */
    public function testMovingAServiceFlagsBothPollersAndKeepsItVisibleToItsMover(): void
    {
        $previousPollerId = $this->insertPoller();
        $previousHostId = $this->insertHost($previousPollerId);
        $newPollerId = $this->insertPoller();
        $newHostId = $this->insertHost($newPollerId);
        $serviceId = $this->insertService('ping', $previousHostId);
        ['groupId' => $groupId] = $this->loginAsNonAdmin();
        $this->grantPair($groupId, $previousHostId, $serviceId);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $groupId, 'host_id' => $newHostId, 'service_id' => null]);

        $this->patch($serviceId, ['host_id' => $newHostId]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([$newHostId], $this->ids('SELECT host_host_id FROM host_service_relation WHERE service_service_id = ?', $serviceId));
        self::assertSame(['1', '1'], [$this->pollerUpdated($previousPollerId), $this->pollerUpdated($newPollerId)]);
        self::assertSame([$groupId], $this->groupsGrantedThePair($newHostId, $serviceId));
        self::assertSame(['mc'], array_column($this->activityLogs($serviceId), 'action_type'));
    }

    public function testMovingAServiceOffADisabledHostFlagsOnlyTheNewPoller(): void
    {
        $previousPollerId = $this->insertPoller();
        $previousHostId = $this->insertHost($previousPollerId, activated: false);
        $newPollerId = $this->insertPoller();
        $serviceId = $this->insertService('ping', $previousHostId);
        $this->login();

        $this->patch($serviceId, ['host_id' => $this->insertHost($newPollerId)]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['0', '1'], [$this->pollerUpdated($previousPollerId), $this->pollerUpdated($newPollerId)]);
    }

    /**
     * A service linked to several hosts is read with the one of lowest id: a saved update keeps only that one.
     */
    public function testASavedUpdateKeepsOnlyTheHostTheServiceIsReadWith(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $otherHostId = $this->insertHost($pollerId);
        $serviceId = $this->insertService('ping', $hostId);
        $this->connection->insert('host_service_relation', ['host_host_id' => $otherHostId, 'service_service_id' => $serviceId]);
        $this->login();

        $this->patch($serviceId, ['monitoring_engine' => ['max_check_attempts' => 5]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([$hostId], $this->ids('SELECT host_host_id FROM host_service_relation WHERE service_service_id = ?', $serviceId));
    }

    public function testANameUsedOnTheHostIsAConflict(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $this->insertService('pong', $hostId);
        $serviceId = $this->insertService('ping', $hostId);
        $this->login();

        $this->patch($serviceId, ['name' => 'PONG']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('ping', $this->column('service_description', $serviceId));
    }

    public function testMovingOntoAHostWhereTheNameIsUsedIsAConflict(): void
    {
        $pollerId = $this->insertPoller();
        $otherHostId = $this->insertHost($pollerId);
        $this->insertService('ping', $otherHostId);
        $serviceId = $this->insertService('ping', $this->insertHost($pollerId));
        $this->login();

        $this->patch($serviceId, ['host_id' => $otherHostId]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testItAcceptsTheCurrentName(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        $this->login();

        $this->patch($serviceId, ['name' => 'ping', 'monitoring_engine' => ['max_check_attempts' => 2]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('2', $this->text($this->column('service_max_check_attempts', $serviceId)));
    }

    /**
     * A Centreon Monitoring Agent check command imposes its freshness over the values sent with it.
     */
    public function testACentreonMonitoringAgentCommandImposesTheFreshness(): void
    {
        $agentCommandId = $this->insertCommand(name: $this->uniqueName('Centreon-Monitoring-Agent'));
        $this->connection->update('command', ['command_locked' => 1], ['command_id' => $agentCommandId]);
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->login();

        $this->patch($serviceId, [
            'check_options' => ['command_id' => $agentCommandId],
            'advanced' => ['check_freshness' => 'false', 'freshness_threshold' => 30],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['1', '500'], [$this->text($this->column('service_check_freshness', $serviceId)), $this->text($this->column('service_freshness_threshold', $serviceId))]);
    }

    /**
     * A service ending up notifying the contacts of its host only has none of its own, those sent with the switch
     * included.
     */
    public function testSwitchingToTheHostContactsClearsTheContacts(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        $this->connection->insert('contact_service_relation', ['contact_id' => $this->insertContact(), 'service_service_id' => $serviceId]);
        $this->login();

        $this->patch($serviceId, ['notifications' => ['use_only_contacts_from_host' => true, 'contacts_to_add' => [$this->insertContact()]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->column('service_use_only_contacts_from_host', $serviceId));
        self::assertSame([], $this->ids('SELECT contact_id FROM contact_service_relation WHERE service_service_id = ?', $serviceId));
    }

    /**
     * Removing the check command clears its arguments; the template's command is then used.
     */
    public function testRemovingTheCheckCommandClearsItsArguments(): void
    {
        $commandId = $this->insertCommand();
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), [
            'command_command_id' => $commandId,
            'command_command_id_arg' => '!80',
            'service_template_model_stm_id' => $this->insertServiceTemplate($commandId),
        ]);
        $this->login();

        $this->patch($serviceId, ['check_options' => ['command_id' => null]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([null, null], [$this->column('command_command_id', $serviceId), $this->column('command_command_id_arg', $serviceId)]);
    }

    /**
     * Removing the event handler keeps its arguments, which then need one from the template chain.
     */
    public function testRemovingTheEventHandlerKeepsItsArguments(): void
    {
        $handlerId = $this->insertCommand(self::MISCELLANEOUS);
        $columns = ['command_command_id' => $this->insertCommand(), 'command_command_id2' => $handlerId, 'command_command_id_arg2' => '!restart'];
        $hostId = $this->insertHost($this->insertPoller());
        $withoutTemplate = $this->insertService('ping', $hostId, $columns);
        $withTemplate = $this->insertService('pong', $hostId, [...$columns, 'service_template_model_stm_id' => $this->insertServiceTemplate(eventHandlerCommandId: $handlerId)]);
        $this->login();

        $this->patch($withoutTemplate, ['advanced' => ['event_handler_command_id' => null]]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['message' => "[advanced] Event handler arguments require an event handler, set on the service or provided by its service template.\n"]);

        $this->patch($withTemplate, ['advanced' => ['event_handler_command_id' => null]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([null, '!restart'], [$this->column('command_command_id2', $withTemplate), $this->column('command_command_id_arg2', $withTemplate)]);
    }

    public function testAServiceLeftWithoutAnyCheckCommandIsRefused(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->login();

        $this->patch($serviceId, ['check_options' => ['command_id' => null]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['message' => "[check_options] A check command is required when no service template is set.\n"]);
        self::assertNotNull($this->column('command_command_id', $serviceId));
    }

    /**
     * On a Cloud platform, the fields only an on-premise platform has are refused; the service template cannot be
     * removed.
     *
     * @param array<string, mixed> $body
     */
    #[DataProvider('onPremiseOnlyProvider')]
    public function testACloudPlatformRefusesTheOnPremiseFields(array $body, string $path): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->login();
        self::getContainer()->set(WhenPlatformValidator::class, new WhenPlatformValidator(isCloudPlatform: true));

        $response = $this->patch($serviceId, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertMessageStartsWith('[' . $path . ']', $response);
    }

    public function testACloudPlatformAcceptsAnUpdateLeavingTheTemplateOut(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->login();
        self::getContainer()->set(WhenPlatformValidator::class, new WhenPlatformValidator(isCloudPlatform: true));

        $this->patch($serviceId, ['name' => 'pong']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('pong', $this->column('service_description', $serviceId));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function onPremiseOnlyProvider(): iterable
    {
        yield 'no service template' => [['service_template_id' => null], 'service_template_id'];

        yield 'traps added' => [['trap_ids_to_add' => [1]], 'trap_ids_to_add'];

        yield 'a graph template' => [['graph_template_id' => 1], 'graph_template_id'];

        yield 'notifications' => [['notifications' => ['interval' => 1]], 'notifications'];

        yield 'a volatile switch' => [['monitoring_engine' => ['is_volatile' => 'true']], 'monitoring_engine.is_volatile'];

        yield 'stalking options added' => [['advanced' => ['stalking_options_to_add' => ['ok']]], 'advanced.stalking_options_to_add'];

        yield 'a comment' => [['advanced' => ['comment' => 'x']], 'advanced.comment'];
    }

    /**
     * @param array<string, mixed> $body
     * @param non-empty-string $message
     */
    #[DataProvider('refusedBodyProvider')]
    public function testARefusedBodyChangesNothing(array $body, string $message): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->login();

        $response = $this->patch($serviceId, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertMessageStartsWith($message, $response);
        self::assertSame([], $this->activityLogs($serviceId));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, non-empty-string}>
     */
    public static function refusedBodyProvider(): iterable
    {
        foreach (['name', 'host_id', 'activated'] as $key) {
            yield "{$key} sent as null" => [[$key => null], "[{$key}] This value cannot be null.\n"];
        }

        foreach (['check_options', 'monitoring_engine', 'notifications', 'advanced'] as $key) {
            yield "{$key} sent as null" => [[$key => null], "[{$key}] This value cannot be null.\n"];
        }

        foreach (['use_only_contacts_from_host', 'contact_additive_inheritance', 'contact_group_additive_inheritance'] as $key) {
            yield "notifications.{$key} sent as null" => [['notifications' => [$key => null]], "[notifications.{$key}] This value should be of type bool.\n"];
        }

        yield 'a list sent as null' => [['check_options' => ['args' => null]], "[check_options.args] This value should be of type array.\n"];

        yield 'a blank name' => [['name' => '  '], "[name] This value should not be blank.\n"];

        yield 'a blank note' => [['monitoring_engine' => ['note' => ' ']], "[monitoring_engine.note] This value should not be blank.\n"];

        yield 'a blank comment' => [['advanced' => ['comment' => ' ']], "[advanced.comment] This value should not be blank.\n"];

        yield 'a name with a forbidden character' => [['name' => 'ping!'], '[name] This value must not contain any of the following characters'];

        yield 'a name too long' => [['name' => str_repeat('n', 201)], "[name] This value is too long. It should have 200 characters or less.\n"];

        yield 'a check attempt below 1' => [['monitoring_engine' => ['max_check_attempts' => 0]], '[monitoring_engine.max_check_attempts] This value should be greater than or equal to 1.'];

        yield 'a negative notification interval' => [['notifications' => ['interval' => -1]], '[notifications.interval] This value should be either positive or zero.'];

        yield 'a flap threshold above 100' => [['advanced' => ['high_flap_threshold' => 101]], '[advanced.high_flap_threshold] This value should be between 0 and 100.'];

        yield 'an acknowledgment timeout of 0' => [['advanced' => ['acknowledgment_timeout' => 0]], '[advanced.acknowledgment_timeout] This value should be positive.'];

        yield 'a note too long' => [['monitoring_engine' => ['note' => str_repeat('n', 513)]], '[monitoring_engine.note] This value is too long. It should have 512 characters or less.'];

        yield 'an unknown tri-state' => [['monitoring_engine' => ['is_volatile' => 'maybe']], '[monitoring_engine.is_volatile]'];

        yield 'partly filled arguments' => [['check_options' => ['args' => ['80', '']]], '[check_options.args] Either fill every argument or leave them all empty.'];

        yield 'an unknown host' => [['host_id' => 999999999], '[host_id]'];

        yield 'an unknown service template' => [['service_template_id' => 999999999], '[service_template_id]'];

        yield 'an unknown graph template' => [['graph_template_id' => 999999999], '[graph_template_id]'];

        yield 'an unknown check command' => [['check_options' => ['command_id' => 999999999]], '[check_options.command_id]'];

        yield 'an unknown check period' => [['monitoring_engine' => ['check_timeperiod_id' => 999999999]], '[monitoring_engine.check_timeperiod_id]'];

        yield 'an unknown icon' => [['monitoring_engine' => ['icon_id' => 999999999]], '[monitoring_engine.icon_id]'];

        yield 'an unknown notification period' => [['notifications' => ['timeperiod_id' => 999999999]], '[notifications.timeperiod_id]'];

        yield 'an unknown event handler' => [['advanced' => ['event_handler_command_id' => 999999999]], '[advanced.event_handler_command_id]'];

        yield 'an unknown stalking option' => [['advanced' => ['stalking_options_to_add' => ['down']]], '[advanced.stalking_options_to_add[0]]'];
    }
}
