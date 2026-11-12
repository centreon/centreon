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

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The lists of a service partial update, each with three keys of which one only can be sent: replaced, added to or
 * removed from.
 */
final class PatchServiceRelationsProcessorTest extends PatchServiceProcessorTestCase
{
    private int $hostId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostId = $this->insertHost($this->insertPoller());
        $this->serviceId = $this->insertService('ping', $this->hostId, ['command_command_id' => $this->insertCommand()]);
    }

    /**
     * @param \Closure(self): array{string, list<int|string>} $seed links two values to the service, and gives the
     *                                                              key of the list and a third value
     * @param \Closure(self): list<int|string> $read
     */
    #[DataProvider('listProvider')]
    public function testEachListIsReplacedAddedToAndRemovedFrom(\Closure $seed, \Closure $read, ?string $section): void
    {
        [$key, [$first, $second, $third]] = $seed($this);
        $this->login();
        $send = function (string $listKey, array $values) use ($section): void {
            $this->patch($this->serviceId, $section === null ? [$listKey => $values] : [$section => [$listKey => $values]]);
            self::assertResponseStatusCodeSame(204);
        };

        $send($key . '_to_add', [$third, $first]);
        self::assertEqualsCanonicalizing([$first, $second, $third], $read($this));

        $send($key . '_to_remove', [$first]);
        self::assertEqualsCanonicalizing([$second, $third], $read($this));

        $send($key, [$first]);
        self::assertEqualsCanonicalizing([$first], $read($this));

        $send($key, []);
        self::assertSame([], $read($this));

        self::assertSame(['mc', 'mc', 'mc', 'mc'], array_column($this->activityLogs($this->serviceId), 'action_type'));
    }

    /**
     * @return iterable<string, array{\Closure(self): array{string, list<int|string>}, \Closure(self): list<int|string>, ?string}>
     */
    public static function listProvider(): iterable
    {
        yield 'service groups' => [
            static function (self $test): array {
                $ids = [$test->insertServiceGroup(), $test->insertServiceGroup(), $test->insertServiceGroup()];
                foreach ([$ids[0], $ids[1]] as $id) {
                    $test->connection->insert('servicegroup_relation', ['host_host_id' => $test->hostId, 'service_service_id' => $test->serviceId, 'servicegroup_sg_id' => $id]);
                }

                return ['service_group_ids', $ids];
            },
            static fn (self $test): array => $test->ids('SELECT servicegroup_sg_id FROM servicegroup_relation WHERE service_service_id = ?', $test->serviceId),
            null,
        ];

        yield 'categories' => [
            static function (self $test): array {
                $ids = [$test->insertServiceCategory(), $test->insertServiceCategory(), $test->insertServiceCategory()];
                foreach ([$ids[0], $ids[1]] as $id) {
                    $test->connection->insert('service_categories_relation', ['sc_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['category_ids', $ids];
            },
            static fn (self $test): array => $test->ids('SELECT scr.sc_id FROM service_categories_relation scr JOIN service_categories sc ON sc.sc_id = scr.sc_id WHERE sc.level IS NULL AND scr.service_service_id = ?', $test->serviceId),
            null,
        ];

        yield 'traps' => [
            static function (self $test): array {
                $ids = [$test->insertTrap(), $test->insertTrap(), $test->insertTrap()];
                foreach ([$ids[0], $ids[1]] as $id) {
                    $test->connection->insert('traps_service_relation', ['traps_id' => $id, 'service_id' => $test->serviceId]);
                }

                return ['trap_ids', $ids];
            },
            static fn (self $test): array => $test->ids('SELECT traps_id FROM traps_service_relation WHERE service_id = ?', $test->serviceId),
            null,
        ];

        yield 'contacts' => [
            static function (self $test): array {
                $ids = [$test->insertContact(), $test->insertContact(), $test->insertContact()];
                foreach ([$ids[0], $ids[1]] as $id) {
                    $test->connection->insert('contact_service_relation', ['contact_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['contacts', $ids];
            },
            static fn (self $test): array => $test->ids('SELECT contact_id FROM contact_service_relation WHERE service_service_id = ?', $test->serviceId),
            'notifications',
        ];

        yield 'contact groups' => [
            static function (self $test): array {
                $ids = [$test->insertContactGroup(), $test->insertContactGroup(), $test->insertContactGroup()];
                foreach ([$ids[0], $ids[1]] as $id) {
                    $test->connection->insert('contactgroup_service_relation', ['contactgroup_cg_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['contact_groups', $ids];
            },
            static fn (self $test): array => $test->ids('SELECT contactgroup_cg_id FROM contactgroup_service_relation WHERE service_service_id = ?', $test->serviceId),
            'notifications',
        ];

        yield 'notification options' => [
            static function (self $test): array {
                $test->connection->update('service', ['service_notification_options' => 'w,u'], ['service_id' => $test->serviceId]);

                return ['options', ['warning', 'unknown', 'critical']];
            },
            static fn (self $test): array => self::options($test, 'service_notification_options', ['w' => 'warning', 'u' => 'unknown', 'c' => 'critical']),
            'notifications',
        ];

        yield 'stalking options' => [
            static function (self $test): array {
                $test->connection->update('service', ['service_stalking_options' => 'o,w'], ['service_id' => $test->serviceId]);

                return ['stalking_options', ['ok', 'warning', 'critical']];
            },
            static fn (self $test): array => self::options($test, 'service_stalking_options', ['o' => 'ok', 'w' => 'warning', 'c' => 'critical']),
            'advanced',
        ];
    }

    /**
     * A restricted actor's replacement keeps what they cannot see, and their removal leaves it alone. They see one
     * of the two linked values only.
     *
     * @param \Closure(self, int): array{string, int, int} $seed links a visible and a hidden value to the service
     *                                                           (granted to the access group given), and gives the key
     * @param \Closure(self): list<int> $read
     */
    #[DataProvider('scopedListProvider')]
    public function testARestrictedActorKeepsWhatTheyCannotSee(\Closure $seed, \Closure $read, ?string $section): void
    {
        ['groupId' => $groupId] = $this->loginAsNonAdmin();
        $this->grantPair($groupId, $this->hostId, $this->serviceId);
        [$key, $visible, $hidden] = $seed($this, $groupId);

        $this->patch($this->serviceId, $section === null ? [$key => []] : [$section => [$key => []]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$hidden], $read($this));

        $this->patch($this->serviceId, $section === null ? [$key . '_to_remove' => [$hidden]] : [$section => [$key . '_to_remove' => [$hidden]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$hidden], $read($this));

        $this->patch($this->serviceId, $section === null ? [$key . '_to_add' => [$hidden]] : [$section => [$key . '_to_add' => [$hidden]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertNotContains($visible, $read($this));
    }

    /**
     * @return iterable<string, array{\Closure(self, int): array{string, int, int}, \Closure(self): list<int>, ?string}>
     */
    public static function scopedListProvider(): iterable
    {
        yield 'service groups' => [
            static function (self $test, int $groupId): array {
                [$visible, $hidden] = [$test->insertServiceGroup(), $test->insertServiceGroup()];
                $test->grantResources($groupId, serviceGroupIds: [$visible]);
                foreach ([$visible, $hidden] as $id) {
                    $test->connection->insert('servicegroup_relation', ['host_host_id' => $test->hostId, 'service_service_id' => $test->serviceId, 'servicegroup_sg_id' => $id]);
                }

                return ['service_group_ids', $visible, $hidden];
            },
            static fn (self $test): array => $test->ids('SELECT servicegroup_sg_id FROM servicegroup_relation WHERE service_service_id = ?', $test->serviceId),
            null,
        ];

        yield 'categories' => [
            static function (self $test, int $groupId): array {
                [$visible, $hidden] = [$test->insertServiceCategory(), $test->insertServiceCategory()];
                $test->grantResources($groupId, categoryIds: [$visible]);
                foreach ([$visible, $hidden] as $id) {
                    $test->connection->insert('service_categories_relation', ['sc_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['category_ids', $visible, $hidden];
            },
            static fn (self $test): array => $test->ids('SELECT sc_id FROM service_categories_relation WHERE service_service_id = ?', $test->serviceId),
            null,
        ];

        yield 'contacts' => [
            static function (self $test, int $groupId): array {
                [$visible, $hidden] = [$test->insertContact(), $test->insertContact()];
                $test->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => $visible]);
                foreach ([$visible, $hidden] as $id) {
                    $test->connection->insert('contact_service_relation', ['contact_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['contacts', $visible, $hidden];
            },
            static fn (self $test): array => $test->ids('SELECT contact_id FROM contact_service_relation WHERE service_service_id = ?', $test->serviceId),
            'notifications',
        ];

        yield 'contact groups' => [
            static function (self $test, int $groupId): array {
                [$visible, $hidden] = [$test->insertContactGroup(), $test->insertContactGroup()];
                $test->connection->insert('acl_group_contactgroups_relations', ['acl_group_id' => $groupId, 'cg_cg_id' => $visible]);
                foreach ([$visible, $hidden] as $id) {
                    $test->connection->insert('contactgroup_service_relation', ['contactgroup_cg_id' => $id, 'service_service_id' => $test->serviceId]);
                }

                return ['contact_groups', $visible, $hidden];
            },
            static fn (self $test): array => $test->ids('SELECT contactgroup_cg_id FROM contactgroup_service_relation WHERE service_service_id = ?', $test->serviceId),
            'notifications',
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('twoKeysProvider')]
    public function testTwoKeysOfOneListAreRefused(array $body, string $path): void
    {
        $this->login();

        $response = $this->patch($this->serviceId, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertMessageStartsWith('[' . $path . ']', $response);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function twoKeysProvider(): iterable
    {
        yield 'service groups' => [['service_group_ids' => [], 'service_group_ids_to_add' => []], 'service_group_ids_to_add'];

        yield 'categories' => [['category_ids_to_add' => [], 'category_ids_to_remove' => []], 'category_ids_to_remove'];

        yield 'traps' => [['trap_ids' => [], 'trap_ids_to_remove' => []], 'trap_ids_to_remove'];

        yield 'contacts' => [['notifications' => ['contacts' => [], 'contacts_to_add' => []]], 'notifications.contacts_to_add'];

        yield 'contact groups' => [['notifications' => ['contact_groups' => [], 'contact_groups_to_remove' => []]], 'notifications.contact_groups_to_remove'];

        yield 'notification options' => [['notifications' => ['options' => [], 'options_to_add' => []]], 'notifications.options_to_add'];

        yield 'stalking options' => [['advanced' => ['stalking_options' => [], 'stalking_options_to_remove' => []]], 'advanced.stalking_options_to_remove'];

        yield 'macros' => [['check_options' => ['macros' => [], 'macros_to_remove' => []]], 'check_options.macros_to_remove'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('refusedListProvider')]
    public function testARefusedListChangesNothing(array $body): void
    {
        $this->login();

        $this->patch($this->serviceId, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->activityLogs($this->serviceId));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedListProvider(): iterable
    {
        yield 'a list sent as null' => [['category_ids' => null]];

        yield 'an id that is not an integer' => [['trap_ids_to_add' => ['abc']]];

        yield 'an id that is not positive' => [['service_group_ids' => [0]]];

        yield 'a service group that does not exist' => [['service_group_ids_to_add' => [999999999]]];

        yield 'a category that does not exist' => [['category_ids' => [999999999]]];

        yield 'a trap that does not exist' => [['trap_ids_to_add' => [999999999]]];

        yield 'a contact that does not exist' => [['notifications' => ['contacts' => [999999999]]]];

        yield 'a contact group that does not exist' => [['notifications' => ['contact_groups_to_add' => [999999999]]]];

        yield 'none added with another option' => [['notifications' => ['options_to_add' => ['none', 'warning']]]];
    }

    public function testNoneCannotBeAddedToAServiceWhoseOptionsAreOtherwise(): void
    {
        $this->connection->update('service', ['service_notification_options' => 'w'], ['service_id' => $this->serviceId]);
        $this->login();

        $this->patch($this->serviceId, ['notifications' => ['options_to_add' => ['none']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('w', $this->column('service_notification_options', $this->serviceId));
    }

    /**
     * Categories and severities share one table: a change of either leaves the other alone.
     */
    public function testTheCategoriesAndTheSeverityAreKeptApart(): void
    {
        $category = $this->insertServiceCategory();
        $severity = $this->insertServiceCategory(level: 1);
        $this->connection->insert('service_categories_relation', ['sc_id' => $severity, 'service_service_id' => $this->serviceId]);
        $this->login();

        $this->patch($this->serviceId, ['category_ids' => [$category]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$category, $severity], $this->ids('SELECT sc_id FROM service_categories_relation WHERE service_service_id = ?', $this->serviceId));

        $this->patch($this->serviceId, ['severity_id' => null]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([$category], $this->ids('SELECT sc_id FROM service_categories_relation WHERE service_service_id = ?', $this->serviceId));
    }

    /**
     * @param array<string, string> $letters
     *
     * @return list<string>
     */
    private static function options(self $test, string $column, array $letters): array
    {
        $stored = $test->text($test->column($column, $test->serviceId));

        return $stored === null || $stored === ''
            ? []
            : array_map(static fn (string $letter): string => $letters[$letter], explode(',', $stored));
    }
}
