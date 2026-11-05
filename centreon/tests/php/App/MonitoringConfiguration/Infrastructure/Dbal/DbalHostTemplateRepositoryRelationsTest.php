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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateRelationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
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

final class DbalHostTemplateRepositoryRelationsTest extends KernelTestCase
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

    public function testItReplacesTheRelationsAndKeepsTheSeverity(): void
    {
        $id = $this->insertTemplate();
        [$firstParent, $secondParent, $oldParent] = [$this->insertTemplate(), $this->insertTemplate(), $this->insertTemplate()];
        $this->connection->insert('host_template_relation', ['host_host_id' => $id, 'host_tpl_id' => $oldParent, '`order`' => 1]);
        $oldCategory = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias']);
        $newCategory = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias']);
        $severity = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias', 'level' => 1]);
        foreach ([$oldCategory, $severity] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $contact = $this->insert('contact', ['contact_name' => $this->uniqueName(), 'contact_alias' => $this->uniqueName(), 'contact_register' => '1']);
        $group = $this->insert('contactgroup', ['cg_name' => $this->uniqueName(), 'cg_alias' => 'alias']);
        $oldLinked = $this->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);
        $newLinked = $this->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);
        $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $oldLinked]);
        $template = $this->findOne($id);

        $this->repository->replaceRelations($template->withRelations(new HostTemplateRelationsChange(
            parentTemplateIds: new Collection([new HostTemplateId($secondParent), new HostTemplateId($firstParent)], HostTemplateId::class),
            categoryIds: new Collection([new HostCategoryId($newCategory)], HostCategoryId::class),
            linkedServiceTemplateIds: new Collection([new ServiceTemplateId($newLinked)], ServiceTemplateId::class),
            contactIds: new Collection([new NotificationContactId($contact)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId($group)], ContactGroupId::class),
        )));

        self::assertSame(
            [[$secondParent, 1], [$firstParent, 2]],
            array_map(
                fn (array $row): array => [$this->asInt($row['host_tpl_id']), $this->asInt($row['order'])],
                $this->connection->fetchAllAssociative('SELECT host_tpl_id, `order` FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`', [$id]),
            ),
        );
        self::assertSame([$newCategory, $severity], $this->column('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', $id));
        self::assertSame([$newLinked], $this->column('SELECT service_service_id FROM host_service_relation WHERE host_host_id = ?', $id));
        self::assertSame([$contact], $this->column('SELECT contact_id FROM contact_host_relation WHERE host_host_id = ?', $id));
        self::assertSame([$group], $this->column('SELECT contactgroup_cg_id FROM contactgroup_host_relation WHERE host_host_id = ?', $id));
    }

    public function testItNeverReplacesTheRelationsOfAHost(): void
    {
        $hostId = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '1']);
        $templateId = $this->insertTemplate();
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => 1]);

        $this->repository->replaceRelations(new HostTemplate(new HostTemplateId($hostId), new \App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName($this->uniqueName())));

        self::assertSame([$templateId], $this->column('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ?', $hostId));
    }

    public function testItWritesTheMacrosInPlace(): void
    {
        $id = $this->insertTemplate();
        $otherId = $this->insertTemplate();
        $kept = $this->insertMacro($id, '$_HOSTPORT$', '80');
        $removed = $this->insertMacro($id, '$_HOSTOLD$', 'x');
        $foreign = $this->insertMacro($otherId, '$_HOSTFOREIGN$', 'y');

        $this->repository->replaceMacros($this->findOne($id)->withMacros(new Collection([
            new HostTemplateMacro(new HostTemplateMacroName('PORT'), '443', false, new HostTemplateMacroId($kept)),
            new HostTemplateMacro(new HostTemplateMacroName('NEW'), 's', true, new HostTemplateMacroId($foreign)),
        ], HostTemplateMacro::class)));

        $rows = $this->connection->fetchAllAssociative('SELECT host_macro_id, host_macro_name, host_macro_value, is_password FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY host_macro_id', [$id]);
        self::assertCount(2, $rows);
        self::assertSame([$kept, '$_HOSTPORT$', '443', null], [$this->asInt($rows[0]['host_macro_id']), $rows[0]['host_macro_name'], $rows[0]['host_macro_value'], $rows[0]['is_password']]);
        self::assertSame(['$_HOSTNEW$', 's', 1], [$rows[1]['host_macro_name'], $rows[1]['host_macro_value'], $this->asInt($rows[1]['is_password'])]);
        self::assertNotSame($removed, $this->asInt($rows[1]['host_macro_id']));
        self::assertSame(['$_HOSTFOREIGN$', 'y'], array_values((array) $this->connection->fetchAssociative('SELECT host_macro_name, host_macro_value FROM on_demand_macro_host WHERE host_macro_id = ?', [$foreign])));
    }

    public function testTheParentEdgesAreThoseBetweenTemplatesInactiveOnesIncluded(): void
    {
        $child = $this->insertTemplate();
        $inactiveParent = $this->insertTemplate('0');
        $hostId = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '1']);
        $this->connection->insert('host_template_relation', ['host_host_id' => $child, 'host_tpl_id' => $inactiveParent, '`order`' => 1]);
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $child, '`order`' => 1]);
        $this->connection->insert('host_template_relation', ['host_host_id' => $child, 'host_tpl_id' => $hostId, '`order`' => 2]);

        $edges = $this->repository->findTemplateParentIds();

        self::assertSame([$inactiveParent], $edges[$child] ?? null);
        self::assertArrayNotHasKey($hostId, $edges);
    }

    private function findOne(int $id): HostTemplate
    {
        $template = $this->repository->findOne(new HostTemplateId($id));
        self::assertInstanceOf(HostTemplate::class, $template);

        return $template;
    }

    private function insertTemplate(string $activate = '1'): int
    {
        return $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0', 'host_activate' => $activate]);
    }

    private function insertMacro(int $hostId, string $name, string $value): int
    {
        return $this->insert('on_demand_macro_host', ['host_host_id' => $hostId, 'host_macro_name' => $name, 'host_macro_value' => $value]);
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insert(string $table, array $columns): int
    {
        $this->connection->insert($table, $columns);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<int>
     */
    private function column(string $sql, int $id): array
    {
        return array_map($this->asInt(...), $this->connection->fetchFirstColumn($sql, [$id]));
    }

    private function uniqueName(): string
    {
        return 'ht-' . Uuid::v4()->toBase58();
    }

    private function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
