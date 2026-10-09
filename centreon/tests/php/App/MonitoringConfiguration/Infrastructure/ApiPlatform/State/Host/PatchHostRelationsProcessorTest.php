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

final class PatchHostRelationsProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    /** @var array{headers: array{Content-Type: string}} */
    private const PATCH_HEADERS = ['headers' => ['Content-Type' => 'application/merge-patch+json']];

    private Connection $connection;

    private int $pollerId;

    private int $hostId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->login();
        $this->connection->insert('nagios_server', [
            'name' => $this->uniqueName('poller'),
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);
        $this->pollerId = (int) $this->connection->lastInsertId();
        $this->hostId = $this->insertHost($this->uniqueName('host'));
    }

    public function testTheHostGroupsAreReplacedAddedToAndRemovedFrom(): void
    {
        $first = $this->insertHostGroup();
        $second = $this->insertHostGroup();
        $third = $this->insertHostGroup();
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $first, 'host_host_id' => $this->hostId]);

        $this->patch(['host_group_ids' => [$second, $third]]);
        self::assertSame([$second, $third], $this->hostGroupIds());

        $this->patch(['host_group_ids_to_add' => [$first]]);
        self::assertSame([$first, $second, $third], $this->hostGroupIds());

        $this->patch(['host_group_ids_to_remove' => [$second]]);
        self::assertSame([$first, $third], $this->hostGroupIds());

        $this->patch(['host_group_ids' => []]);
        self::assertSame([], $this->hostGroupIds());
    }

    public function testTheCategoriesAndTheSeverityAreKeptApart(): void
    {
        $category = $this->insertHostCategory(null);
        $otherCategory = $this->insertHostCategory(null);
        $severity = $this->insertHostCategory(1);
        $this->connection->insert('hostcategories_relation', ['hostcategories_hc_id' => $severity, 'host_host_id' => $this->hostId]);

        $this->patch(['category_ids' => [$category]]);
        $this->patch(['category_ids_to_add' => [$otherCategory]]);

        self::assertSame(
            [$category, $otherCategory, $severity],
            $this->ids('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', $this->hostId),
        );
    }

    public function testTheTemplatesKeepTheOrderGiven(): void
    {
        $first = $this->insertHostTemplate();
        $second = $this->insertHostTemplate();

        $this->patch(['template_ids' => [$second, $first]]);

        self::assertSame([$second, $first], $this->templateIds());

        $third = $this->insertHostTemplate();
        $this->patch(['template_ids_to_add' => [$third]]);
        self::assertSame([$second, $first, $third], $this->templateIds());
    }

    public function testRemovingATemplateDeletesTheServicesOnlyItProvides(): void
    {
        $kept = $this->insertHostTemplate();
        $lost = $this->insertHostTemplate();
        $keptServiceTemplate = $this->insertServiceTemplate($kept);
        $lostServiceTemplate = $this->insertServiceTemplate($lost);
        $this->connection->insert('host_template_relation', ['host_host_id' => $this->hostId, 'host_tpl_id' => $kept, '`order`' => 0]);
        $this->connection->insert('host_template_relation', ['host_host_id' => $this->hostId, 'host_tpl_id' => $lost, '`order`' => 1]);

        $keptService = $this->insertServiceOfHost($keptServiceTemplate);
        $lostService = $this->insertServiceOfHost($lostServiceTemplate);

        $this->patch(['template_ids_to_remove' => [$lost]]);

        self::assertSame([$kept], $this->templateIds());
        self::assertSame(1, $this->serviceCount($keptService));
        self::assertSame(0, $this->serviceCount($lostService));
    }

    public function testTheParentsAndTheChildrenAreChanged(): void
    {
        $parent = $this->insertHost($this->uniqueName('parent'));
        $child = $this->insertHost($this->uniqueName('child'));

        $this->patch(['parent_host_ids' => [$parent], 'child_host_ids_to_add' => [$child]]);

        self::assertSame([$parent], $this->ids('SELECT host_parent_hp_id FROM host_hostparent_relation WHERE host_host_id = ?', $this->hostId));
        self::assertSame([$child], $this->ids('SELECT host_host_id FROM host_hostparent_relation WHERE host_parent_hp_id = ?', $this->hostId));
    }

    public function testALoopBetweenHostsIsRefused(): void
    {
        $parent = $this->insertHost($this->uniqueName('parent'));
        $this->connection->insert('host_hostparent_relation', ['host_host_id' => $parent, 'host_parent_hp_id' => $this->hostId]);

        // The parent already depends on this host: this host cannot depend on it.
        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => [
            'parent_host_ids' => [$parent],
            'child_host_ids' => [$parent],
        ]]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheContactsContactGroupsAndOptionsAreChanged(): void
    {
        $contact = $this->insertContact();
        $otherContact = $this->insertContact();
        $contactGroup = $this->insertContactGroup();

        $this->patch(['notifications' => [
            'contacts' => [$contact],
            'contact_groups_to_add' => [$contactGroup],
            'options' => ['down'],
        ]]);
        $this->patch(['notifications' => ['contacts_to_add' => [$otherContact], 'options_to_add' => ['recovery']]]);

        self::assertSame([$contact, $otherContact], $this->ids('SELECT contact_id FROM contact_host_relation WHERE host_host_id = ? ORDER BY contact_id', $this->hostId));
        self::assertSame([$contactGroup], $this->ids('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', $this->hostId));
        self::assertSame('d,r', $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$this->hostId]));

        $this->patch(['notifications' => ['options_to_remove' => ['down']]]);
        self::assertSame('r', $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$this->hostId]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('refusedBodyProvider')]
    public function testItRefusesWhatTheContractForbids(array $body): void
    {
        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedBodyProvider(): iterable
    {
        yield 'two forms of the host groups' => [['host_group_ids' => [1], 'host_group_ids_to_add' => [2]]];

        yield 'two forms of the contacts' => [['notifications' => ['contacts' => [1], 'contacts_to_remove' => [2]]]];

        yield 'two forms of the options' => [['notifications' => ['options' => ['down'], 'options_to_add' => ['recovery']]]];

        yield 'a list sent as null' => [['category_ids' => null]];

        yield 'an id that is not an integer' => [['template_ids_to_add' => ['abc']]];

        yield 'an id that is not positive' => [['parent_host_ids' => [0]]];

        yield 'none added with another option' => [['notifications' => ['options_to_add' => ['none', 'down']]]];

        yield 'a host group that does not exist' => [['host_group_ids' => [99999999]]];

        yield 'a template that does not exist' => [['template_ids' => [99999999]]];

        yield 'a category that does not exist' => [['category_ids_to_add' => [99999999]]];

        yield 'a parent that does not exist' => [['parent_host_ids' => [99999999]]];

        yield 'a contact that does not exist' => [['notifications' => ['contacts' => [99999999]]]];

        yield 'a negative id' => [['parent_host_ids' => [-1]]];
    }

    public function testNoneCannotBeAddedToAHostWhoseOptionsAreOtherwise(): void
    {
        $this->patch(['notifications' => ['options' => ['down']]]);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => ['notifications' => ['options_to_add' => ['none']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('d', $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$this->hostId]));
    }

    public function testAHostCannotBeItsOwnParent(): void
    {
        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => ['parent_host_ids_to_add' => [$this->hostId]]]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(array $body): void
    {
        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return list<int>
     */
    private function ids(string $sql, int $parameter): array
    {
        /** @var list<int|string> $values */
        $values = $this->connection->fetchFirstColumn($sql, [$parameter]);

        return array_map(static fn (int|string $value): int => (int) $value, $values);
    }

    private function serviceCount(int $serviceId): int
    {
        /** @var int|string $count */
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM service WHERE service_id = ?', [$serviceId]);

        return (int) $count;
    }

    /**
     * @return list<int>
     */
    private function hostGroupIds(): array
    {
        return $this->ids('SELECT hostgroup_hg_id FROM hostgroup_relation WHERE host_host_id = ? ORDER BY hostgroup_hg_id', $this->hostId);
    }

    /**
     * @return list<int>
     */
    private function templateIds(): array
    {
        return $this->ids('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`', $this->hostId);
    }

    private function uniqueName(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(6));
    }

    private function insertHost(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $this->pollerId]);

        return $hostId;
    }

    private function insertHostGroup(): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $this->uniqueName('group')]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostCategory(?int $level): int
    {
        $name = $this->uniqueName('category');
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name, 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostTemplate(): int
    {
        $this->connection->insert('host', ['host_name' => $this->uniqueName('template'), 'host_register' => '0', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceTemplate(int $hostTemplateId): int
    {
        $this->connection->insert('service', ['service_description' => $this->uniqueName('service-template'), 'service_register' => '0']);
        $serviceTemplateId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostTemplateId, 'service_service_id' => $serviceTemplateId]);

        return $serviceTemplateId;
    }

    private function insertServiceOfHost(int $serviceTemplateId): int
    {
        $this->connection->insert('service', [
            'service_description' => $this->uniqueName('service'),
            'service_register' => '1',
            'service_template_model_stm_id' => $serviceTemplateId,
        ]);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $this->hostId, 'service_service_id' => $serviceId]);

        return $serviceId;
    }

    private function insertContact(): int
    {
        $name = $this->uniqueName('contact');
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContactGroup(): int
    {
        $name = $this->uniqueName('contact-group');
        $this->connection->insert('contactgroup', ['cg_name' => $name, 'cg_alias' => $name, 'cg_type' => 'local', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }
}
