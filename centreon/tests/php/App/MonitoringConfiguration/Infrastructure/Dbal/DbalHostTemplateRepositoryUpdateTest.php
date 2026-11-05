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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptionsChange;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostTemplateRepositoryUpdateTest extends KernelTestCase
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

    public function testItSavesTheFieldsAndKeepsTheActivationAndTheLock(): void
    {
        $id = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0', 'host_activate' => '0', 'host_locked' => 0]);
        $template = $this->findOne($id);
        $name = $this->uniqueName();

        $template = $template->withChange(new HostTemplateChange(
            name: new HostTemplateName($name),
            alias: new HostTemplateAlias('New alias'),
            extendedInformations: new HostTemplateExtendedInformationsChange(note: ' a note ', comment: 'A comment'),
            schedulingOptions: new HostTemplateSchedulingOptionsChange(maxCheckAttempts: 4),
        ));
        $this->repository->update($template);

        $row = $this->connection->fetchAssociative('SELECT host_name, host_alias, host_activate, host_register, host_comment, host_max_check_attempts FROM host WHERE host_id = ?', [$id]);
        self::assertSame(
            ['host_name' => $name, 'host_alias' => 'New alias', 'host_activate' => '0', 'host_register' => '0', 'host_comment' => 'A comment', 'host_max_check_attempts' => 4],
            [...(array) $row, 'host_max_check_attempts' => $this->asInt($row['host_max_check_attempts'] ?? null)],
        );
        self::assertSame(' a note ', $this->connection->fetchOne('SELECT ehi_notes FROM extended_host_information WHERE host_host_id = ?', [$id]));
    }

    public function testItUpdatesTheExtendedInformationRowWhenThereIsOne(): void
    {
        $id = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0']);
        $this->insert('extended_host_information', ['host_host_id' => $id, 'ehi_notes' => 'old']);
        $template = $this->findOne($id);

        $template = $template->withChange(new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(note: 'new')));

        $this->repository->update($template);

        self::assertSame(['new'], $this->connection->fetchFirstColumn('SELECT ehi_notes FROM extended_host_information WHERE host_host_id = ?', [$id]));
    }

    public function testItReplacesTheSeverityAndKeepsTheCategories(): void
    {
        $id = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0']);
        $categoryId = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias']);
        $oldSeverityId = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias', 'level' => 1]);
        $newSeverityId = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias', 'level' => 2]);
        foreach ([$categoryId, $oldSeverityId] as $hcId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $hcId]);
        }
        $template = $this->findOne($id);

        $template = $template->withChange(new HostTemplateChange(severityId: new HostSeverityId($newSeverityId)));

        $this->repository->update($template);

        $stored = array_map($this->asInt(...), $this->connection->fetchFirstColumn('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id', [$id]));
        self::assertSame([$categoryId, $newSeverityId], $stored);
    }

    public function testItNeverWritesTheHostRowOfAHost(): void
    {
        $hostName = $this->uniqueName();
        $hostId = $this->insert('host', ['host_name' => $hostName, 'host_register' => '1']);

        $this->repository->update(new HostTemplate(new HostTemplateId($hostId), new HostTemplateName($this->uniqueName())));

        self::assertSame($hostName, $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testTheTemplateBeingRenamedDoesNotCountAsUsingItsOwnName(): void
    {
        $name = $this->uniqueName();
        $id = $this->insert('host', ['host_name' => $name, 'host_register' => '0']);
        $otherId = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0']);

        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($name)));
        self::assertFalse($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($name), new HostTemplateId($id)));
        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostTemplateName($name), new HostTemplateId($otherId)));
    }

    private function findOne(int $id): HostTemplate
    {
        $template = $this->repository->findOne(new HostTemplateId($id));
        self::assertInstanceOf(HostTemplate::class, $template);

        return $template;
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insert(string $table, array $columns): int
    {
        $this->connection->insert($table, $columns);

        return (int) $this->connection->lastInsertId();
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
