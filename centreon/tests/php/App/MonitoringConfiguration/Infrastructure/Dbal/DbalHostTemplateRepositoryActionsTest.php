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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostTemplateRepositoryActionsTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostTemplateRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        $this->repository = new DbalHostTemplateRepository(
            $this->connection,
            new HostTemplateTransformer(),
            new AccessScopeResolver(new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection))),
            new DbalModuleRepository($this->connection),
        );
    }

    public function testFindOneHydratesWhatTheActionsNeed(): void
    {
        $parentB = $this->insertTemplate();
        $parentA = $this->insertTemplate();
        $id = $this->insertTemplate(['host_alias' => 'An alias', 'host_locked' => 1, 'host_snmp_community' => 'public']);
        $this->linkToTemplate($id, $parentA, 1);
        $this->linkToTemplate($id, $parentB, 2);
        $this->insertMacro($id, '$_HOSTUSER$', 'admin', false);

        $template = $this->repository->findOne(new HostTemplateId($id));

        self::assertInstanceOf(HostTemplate::class, $template);
        self::assertSame('An alias', $template->alias?->value);
        self::assertTrue($template->isLocked);
        self::assertSame('public', $template->snmpCommunity?->value);
        self::assertSame([$parentA, $parentB], array_map(static fn (HostTemplateId $parent): int => $parent->value, $template->parentTemplateIds->toArray()));
        self::assertSame(['USER'], array_map(static fn (HostTemplateMacro $macro): string => $macro->name->value, $template->checkOptions->macros->toArray()));
    }

    public function testFindOneIgnoresAHostAndAnUnknownId(): void
    {
        self::assertNull($this->repository->findOne(new HostTemplateId($this->insertHost())));
        self::assertNull($this->repository->findOne(new HostTemplateId(\PHP_INT_MAX >> 33)));
    }

    public function testANameIsUsedByAHostOrATemplate(): void
    {
        $hostName = $this->uniqueName();
        $this->insertHost(['host_name' => $hostName]);
        $templateName = $this->uniqueName();
        $this->insertTemplate(['host_name' => $templateName]);

        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($hostName)));
        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($templateName)));
        self::assertFalse($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($this->uniqueName())));
    }

    public function testACopyTakesEveryColumnAndRelationOfItsSource(): void
    {
        $pollerId = $this->insertPoller();
        $parentA = $this->insertTemplate();
        $parentB = $this->insertTemplate();
        $serviceTemplateId = $this->insertServiceTemplate();
        $categoryId = $this->insertCategory(null);
        $severityId = $this->insertCategory(1);
        $contactGroupId = $this->insertContactGroup();
        $contactId = $this->anyContactId();
        $sourceId = $this->insertTemplate([
            'host_alias' => 'alias',
            'host_max_check_attempts' => 7,
            'host_comment' => 'a comment',
            'host_snmp_community' => 'source-community',
            'host_activate' => '1',
        ]);
        $this->linkToTemplate($sourceId, $parentB, 1);
        $this->linkToTemplate($sourceId, $parentA, 2);
        $this->connection->insert('host_service_relation', ['host_host_id' => $sourceId, 'service_service_id' => $serviceTemplateId]);
        $this->connection->insert('extended_host_information', ['host_host_id' => $sourceId, 'ehi_notes' => 'notes', 'ehi_2d_coords' => '1,2']);
        $this->connection->insert('hostcategories_relation', ['host_host_id' => $sourceId, 'hostcategories_hc_id' => $categoryId]);
        $this->connection->insert('hostcategories_relation', ['host_host_id' => $sourceId, 'hostcategories_hc_id' => $severityId]);
        $this->connection->insert('contact_host_relation', ['host_host_id' => $sourceId, 'contact_id' => $contactId]);
        $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $sourceId, 'contactgroup_cg_id' => $contactGroupId]);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $sourceId, 'nagios_server_id' => $pollerId]);
        $this->insertMacro($sourceId, '$_HOSTUSER$', 'admin', false, 'the user', 2);
        $this->insertMacro($sourceId, '$_HOSTPWD$', 'secret::vault::monitoring/hosts/source::_HOSTPWD', true, null, 1);
        $serviceCount = $this->rowCount('service');

        $copy = new HostTemplate(
            null,
            new HostTemplateName($this->uniqueName()),
            new HostTemplateCheckOptions(macros: new Collection([new HostTemplateMacro(new HostMacroName('PWD'), 'secret::vault::monitoring/hosts/copy::_HOSTPWD', isPassword: true)], HostTemplateMacro::class)),
            snmpCommunity: new SnmpCommunity('copy-community'),
        );
        $this->repository->addCopyOf($copy, new HostTemplateId($sourceId));
        $copyId = $copy->id()->value;

        $copyRow = $this->row('SELECT * FROM host WHERE host_id = ?', [$copyId]);
        $sourceRow = $this->row('SELECT * FROM host WHERE host_id = ?', [$sourceId]);
        self::assertSame($copy->name->value, $copyRow['host_name']);
        self::assertSame('copy-community', $copyRow['host_snmp_community']);
        unset($copyRow['host_id'], $copyRow['host_name'], $copyRow['host_snmp_community'], $sourceRow['host_id'], $sourceRow['host_name'], $sourceRow['host_snmp_community']);
        self::assertSame($sourceRow, $copyRow);

        self::assertSame(
            [[$parentB, 1], [$parentA, 2]],
            $this->pairs('SELECT host_tpl_id, `order` FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`', [$copyId]),
        );
        self::assertSame([$serviceTemplateId], $this->column('SELECT service_service_id FROM host_service_relation WHERE host_host_id = ?', [$copyId]));
        self::assertSame($serviceCount, $this->rowCount('service'));
        self::assertSame(
            ['notes', '1,2'],
            array_values($this->row('SELECT ehi_notes, ehi_2d_coords FROM extended_host_information WHERE host_host_id = ?', [$copyId])),
        );
        self::assertEqualsCanonicalizing([$categoryId, $severityId], $this->column('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ?', [$copyId]));
        self::assertSame([$contactId], $this->column('SELECT contact_id FROM contact_host_relation WHERE host_host_id = ?', [$copyId]));
        self::assertSame([$contactGroupId], $this->column('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', [$copyId]));
        self::assertSame([$pollerId], $this->column('SELECT nagios_server_id FROM ns_host_relation WHERE host_host_id = ?', [$copyId]));
        self::assertSame(
            [
                ['$_HOSTPWD$', 'secret::vault::monitoring/hosts/copy::_HOSTPWD', 1, null, 1],
                ['$_HOSTUSER$', 'admin', null, 'the user', 2],
            ],
            array_map(
                static fn (array $macro): array => [$macro['host_macro_name'], $macro['host_macro_value'], $macro['is_password'] === null ? null : self::asInt($macro['is_password']), $macro['description'], self::asInt($macro['macro_order'])],
                $this->connection->fetchAllAssociative('SELECT * FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY macro_order', [$copyId]),
            ),
        );
        self::assertSame(
            'secret::vault::monitoring/hosts/source::_HOSTPWD',
            $this->connection->fetchOne('SELECT host_macro_value FROM on_demand_macro_host WHERE host_host_id = ? AND host_macro_name = ?', [$sourceId, '$_HOSTPWD$']),
        );
    }

    public function testACopyOfAVanishedSourceIsNotFound(): void
    {
        $this->insertTemplate();
        $copy = new HostTemplate(null, new HostTemplateName($this->uniqueName()));

        try {
            $this->repository->addCopyOf($copy, new HostTemplateId($this->insertHost()));
            self::fail('A source that is not a host template must not be copied.');
        } catch (HostTemplateNotFoundException) {
            self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', [$copy->name->value]));
        }
    }

    public function testACopyLeavesOutHostOnlyRelations(): void
    {
        $sourceId = $this->insertTemplate();
        $hostId = $this->insertHost();
        $this->connection->insert('host_hostparent_relation', ['host_parent_hp_id' => $hostId, 'host_host_id' => $sourceId]);
        $this->connection->insert('hostgroup', ['hg_name' => $this->uniqueName()]);
        $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => (int) $this->connection->lastInsertId(), 'host_host_id' => $sourceId]);

        $copy = new HostTemplate(null, new HostTemplateName($this->uniqueName()));
        $this->repository->addCopyOf($copy, new HostTemplateId($sourceId));

        self::assertSame([], $this->column('SELECT host_parent_hp_id FROM host_hostparent_relation WHERE host_host_id = ?', [$copy->id()->value]));
        self::assertSame([], $this->column('SELECT hostgroup_hg_id FROM hostgroup_relation WHERE host_host_id = ?', [$copy->id()->value]));
    }

    public function testThePollersOfTheHostsInheritingTheTemplateDirectlyOrNot(): void
    {
        [$direct, $transitive, $ofDisabled, $own] = [$this->insertPoller(), $this->insertPoller(), $this->insertPoller(), $this->insertPoller()];
        $templateId = $this->insertTemplate();
        $childTemplateId = $this->insertTemplate();
        $this->linkToTemplate($childTemplateId, $templateId, 1);
        $this->linkToTemplate($this->insertHost([], $direct), $templateId, 1);
        $this->linkToTemplate($this->insertHost([], $transitive), $childTemplateId, 1);
        $this->linkToTemplate($this->insertHost(['host_activate' => '0'], $ofDisabled), $templateId, 1);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $templateId, 'nagios_server_id' => $own]);

        self::assertSame([$direct, $transitive], $this->pollerIdsOf($templateId));
    }

    public function testThePollerLookupSurvivesATemplateLoopEnteredFromInside(): void
    {
        $pollerId = $this->insertPoller();
        $templateA = $this->insertTemplate();
        $templateB = $this->insertTemplate();
        $this->linkToTemplate($templateA, $templateB, 1);
        $this->linkToTemplate($templateB, $templateA, 1);
        $this->linkToTemplate($this->insertHost([], $pollerId), $templateB, 1);

        self::assertSame([$pollerId], $this->pollerIdsOf($templateA));
        self::assertSame([$pollerId], $this->pollerIdsOf($templateB));
    }

    public function testRemoveDeletesTheTemplateAndDetachesItsInheritors(): void
    {
        $templateId = $this->insertTemplate();
        $hostId = $this->insertHost();
        $this->linkToTemplate($hostId, $templateId, 1);
        $serviceTemplateId = $this->insertServiceTemplate();
        $this->connection->insert('host_service_relation', ['host_host_id' => $templateId, 'service_service_id' => $serviceTemplateId]);

        $this->repository->remove($this->template($templateId));

        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_id = ?', [$templateId]));
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_id = ?', [$hostId]));
        self::assertSame([], $this->column('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ?', [$hostId]));
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM service WHERE service_id = ?', [$serviceTemplateId]));
    }

    public function testRemoveDetachesAChildTemplateAndTakesItsOwnRowsAlong(): void
    {
        $templateId = $this->insertTemplate();
        $childTemplateId = $this->insertTemplate();
        $this->linkToTemplate($childTemplateId, $templateId, 1);
        $this->insertMacro($templateId, '$_HOSTUSER$', 'admin', false);
        $this->connection->insert('extended_host_information', ['host_host_id' => $templateId, 'ehi_notes' => 'notes']);
        $this->connection->insert('hostcategories_relation', ['host_host_id' => $templateId, 'hostcategories_hc_id' => $this->insertCategory(null)]);
        $this->connection->insert('contact_host_relation', ['host_host_id' => $templateId, 'contact_id' => $this->anyContactId()]);
        $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $templateId, 'contactgroup_cg_id' => $this->insertContactGroup()]);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $templateId, 'nagios_server_id' => $this->insertPoller()]);
        $this->linkToTemplate($templateId, $this->insertTemplate(), 1);
        $ownedTables = ['on_demand_macro_host', 'extended_host_information', 'hostcategories_relation', 'contact_host_relation', 'contactgroup_host_relation', 'ns_host_relation', 'host_template_relation'];
        foreach ($ownedTables as $table) {
            self::assertSame(1, $this->rowCount($table, $templateId), "{$table} must hold a row of the template before the delete");
        }

        $this->repository->remove($this->template($templateId));

        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_id = ?', [$childTemplateId]));
        self::assertSame([], $this->column('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ?', [$childTemplateId]));
        foreach ($ownedTables as $table) {
            self::assertSame(0, $this->rowCount($table, $templateId), "{$table} keeps rows of the deleted template");
        }
    }

    public function testRemoveNeverDeletesAHost(): void
    {
        $hostId = $this->insertHost();

        $this->repository->remove($this->template($hostId));

        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testRemoveDeletesTheHostDependenciesItLeavesEmpty(): void
    {
        $templateId = $this->insertTemplate();
        $otherHostId = $this->insertHost();
        $soleParent = $this->insertDependency('dependency_hostParent_relation', [$templateId]);
        $soleChild = $this->insertDependency('dependency_hostChild_relation', [$templateId]);
        $sharedParent = $this->insertDependency('dependency_hostParent_relation', [$templateId, $otherHostId]);

        $this->repository->remove($this->template($templateId));

        self::assertFalse($this->dependencyExists($soleParent));
        self::assertFalse($this->dependencyExists($soleChild));
        self::assertTrue($this->dependencyExists($sharedParent));
    }

    public function testRemoveKeepsADependencyOnALinkedServiceTemplate(): void
    {
        $templateId = $this->insertTemplate();
        $serviceTemplateId = $this->insertServiceTemplate();
        $this->connection->insert('host_service_relation', ['host_host_id' => $templateId, 'service_service_id' => $serviceTemplateId]);
        $this->connection->insert('dependency', ['dep_name' => $this->uniqueName()]);

        $dependencyId = (int) $this->connection->lastInsertId();
        $this->connection->insert('dependency_serviceParent_relation', ['dependency_dep_id' => $dependencyId, 'service_service_id' => $serviceTemplateId]);

        $this->repository->remove($this->template($templateId));

        self::assertTrue($this->dependencyExists($dependencyId));
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertTemplate(array $columns = []): int
    {
        $this->connection->insert('host', $columns + ['host_name' => $this->uniqueName(), 'host_register' => '0', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertHost(array $columns = [], ?int $pollerId = null): int
    {
        $this->connection->insert('host', $columns + ['host_name' => $this->uniqueName(), 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        if ($pollerId !== null) {
            $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);
        }

        return $hostId;
    }

    private function insertPoller(): int
    {
        $this->connection->insert('nagios_server', ['name' => $this->uniqueName(), 'ns_ip_address' => '127.0.0.1', 'uid' => random_int(1, \PHP_INT_MAX)]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceTemplate(): int
    {
        $this->connection->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCategory(?int $level): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContactGroup(): int
    {
        $this->connection->insert('contactgroup', ['cg_name' => $this->uniqueName(), 'cg_alias' => 'alias', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function anyContactId(): int
    {
        $contactId = $this->connection->fetchOne('SELECT MIN(contact_id) FROM contact');
        self::assertNotFalse($contactId);

        return self::asInt($contactId);
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword, ?string $description = null, int $order = 0): void
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_host_id' => $hostId,
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'description' => $description,
            'macro_order' => $order,
        ]);
    }

    private function linkToTemplate(int $hostId, int $templateId, int $order): void
    {
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => $order]);
    }

    /**
     * @param non-empty-string $relationTable
     * @param list<int> $hostIds
     */
    private function insertDependency(string $relationTable, array $hostIds): int
    {
        $this->connection->insert('dependency', ['dep_name' => $this->uniqueName()]);
        $dependencyId = (int) $this->connection->lastInsertId();
        foreach ($hostIds as $hostId) {
            $this->connection->insert($relationTable, ['dependency_dep_id' => $dependencyId, 'host_host_id' => $hostId]);
        }

        return $dependencyId;
    }

    private function dependencyExists(int $dependencyId): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM dependency WHERE dep_id = ?', [$dependencyId]) !== false;
    }

    private function template(int $id): HostTemplate
    {
        return new HostTemplate(new HostTemplateId($id), new HostTemplateName('to-remove'));
    }

    /**
     * @return list<int>
     */
    private function pollerIdsOf(int $templateId): array
    {
        return array_values(array_map(
            static fn (PollerId $pollerId): int => $pollerId->value,
            $this->repository->findPollerIdsOfInheritingHosts(new HostTemplateId($templateId))->toArray(),
        ));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return array<string, mixed>
     */
    private function row(string $sql, array $parameters): array
    {
        $row = $this->connection->fetchAssociative($sql, $parameters);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return list<int>
     */
    private function column(string $sql, array $parameters): array
    {
        return array_map(self::asInt(...), $this->connection->fetchFirstColumn($sql, $parameters));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return list<list<int>>
     */
    private function pairs(string $sql, array $parameters): array
    {
        return array_map(
            static fn (array $row): array => array_map(self::asInt(...), array_values($row)),
            $this->connection->fetchAllAssociative($sql, $parameters),
        );
    }

    private function rowCount(string $table, ?int $hostId = null): int
    {
        if ($hostId !== null) {
            return self::asInt($this->connection->fetchOne("SELECT COUNT(*) FROM {$table} WHERE host_host_id = ?", [$hostId]));
        }

        return self::asInt($this->connection->fetchOne("SELECT COUNT(*) FROM {$table}"));
    }

    private function uniqueName(): string
    {
        return 'ht-' . Uuid::v4()->toBase58();
    }

    private static function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
