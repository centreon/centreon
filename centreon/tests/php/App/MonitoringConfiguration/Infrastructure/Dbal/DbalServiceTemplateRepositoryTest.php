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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTemplateTransformer;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalServiceTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalServiceTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // constructed directly from the always-public DBAL connection, as the sibling Dbal tests do
        $this->repository = new DbalServiceTemplateRepository(
            $this->connection,
            new ServiceTemplateTransformer(),
            new DbalModuleRepository($this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded service templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack_service');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack');

        parent::tearDown();
    }

    public function testItListsTemplatesOnlyIncludingLockedAndDisabled(): void
    {
        $this->insertService("tpl-{$this->tag}");
        $this->insertService("tpl-locked-{$this->tag}", locked: true);
        $this->insertService("tpl-disabled-{$this->tag}", activate: '0');
        $this->insertService("svc-{$this->tag}", register: '1');
        $this->insertService("meta-{$this->tag}", register: '2');
        $this->insertService("ba-{$this->tag}", register: '3');

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(
            ["tpl-{$this->tag}", "tpl-locked-{$this->tag}", "tpl-disabled-{$this->tag}"],
            $names,
            'Only service templates (register 0) are listed, locked and disabled ones included.',
        );
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertService("tpl-{$this->tag}");
        $nullNameId = $this->insertService(null);
        $emptyNameId = $this->insertService('');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll());

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A template with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A template with an empty name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertService("b-{$this->tag}");
        $this->insertService("a-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertService("prefix-match-{$this->tag}-suffix");
        $this->insertService("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new ServiceTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertService("pg-{$this->tag}-A");
        $this->insertService("pg-{$this->tag}-B");
        $this->insertService("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            (new ServiceTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testItExcludesTheRequestedIds(): void
    {
        $keptName = "kept-{$this->tag}";
        $this->insertService($keptName);
        $excludedId = $this->insertService("excluded-{$this->tag}");

        $names = $this->names($this->repository->findAll(
            (new ServiceTemplateCriteria())->withName($this->tag)->withExcludedIds(new Collection([new ServiceTemplateId($excludedId)], ServiceTemplateId::class))
        ));

        self::assertSame([$keptName], $names);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsTheConnectorTemplatesAndTheirChildren(): void
    {
        [$paidId, $paidChildId, $paidGrandChildId, $freeId, $standaloneId] = $this->insertMonitoringConnectors();

        $ids = $this->monitoringConnectorTemplateIds(['base-generic']);

        self::assertContains($paidId, $ids);
        self::assertContains($paidChildId, $ids);
        self::assertContains($paidGrandChildId, $ids);
        self::assertNotContains($freeId, $ids);
        self::assertNotContains($standaloneId, $ids);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsEveryConnectorTemplateWithoutExcludedConnectors(): void
    {
        [$paidId, , , $freeId, $standaloneId] = $this->insertMonitoringConnectors();

        $ids = $this->monitoringConnectorTemplateIds([]);

        self::assertContains($paidId, $ids);
        self::assertContains($freeId, $ids);
        self::assertNotContains($standaloneId, $ids);
    }

    public function testFindMonitoringConnectorTemplateIdsReturnsNothingWithoutThePluginPackManager(): void
    {
        $this->insertMonitoringConnectors(pluginPackManagerInstalled: false);

        self::assertSame([], $this->monitoringConnectorTemplateIds([]));
    }

    /**
     * The plugin pack manager tables belong to its module, absent from the test database: they are
     * created as temporary tables, which neither commit the test's transaction nor outlive it.
     *
     * @return array{int, int, int, int, int} a paid connector's template, a template inheriting from it,
     *                                        a template inheriting from that child, a free connector's
     *                                        template, a template of no connector
     */
    private function insertMonitoringConnectors(bool $pluginPackManagerInstalled = true): array
    {
        // The test database may already list the module: only the requested state is kept.
        $this->connection->executeStatement("DELETE FROM modules_informations WHERE name = 'centreon-pp-manager'");
        if ($pluginPackManagerInstalled) {
            $this->connection->insert('modules_informations', ['name' => 'centreon-pp-manager']);
        }
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack (pluginpack_id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(255) NOT NULL)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack_service (pluginpack_id INT NOT NULL, svc_id INT NOT NULL)'
        );

        $paidId = $this->insertService("paid-{$this->tag}");
        $paidChildId = $this->insertService("paid-custom-{$this->tag}", parentId: $paidId);
        $paidGrandChildId = $this->insertService("paid-custom-custom-{$this->tag}", parentId: $paidChildId);
        $freeId = $this->insertService("free-{$this->tag}");
        $standaloneId = $this->insertService("standalone-{$this->tag}");
        $this->insertMonitoringConnector('cloud-aws-ec2-' . $this->tag, $paidId);
        $this->insertMonitoringConnector('base-generic', $freeId);
        // A service using a paid template is not a template: it is never returned.
        $this->insertService("svc-{$this->tag}", register: '1', parentId: $paidId);

        return [$paidId, $paidChildId, $paidGrandChildId, $freeId, $standaloneId];
    }

    private function insertMonitoringConnector(string $slug, int $templateId): void
    {
        $this->connection->insert('mod_ppm_pluginpack', ['slug' => $slug]);
        $this->connection->insert('mod_ppm_pluginpack_service', [
            'pluginpack_id' => (int) $this->connection->lastInsertId(),
            'svc_id' => $templateId,
        ]);
    }

    /**
     * @param list<string> $excludedConnectorSlugs
     *
     * @return list<int>
     */
    private function monitoringConnectorTemplateIds(array $excludedConnectorSlugs): array
    {
        return array_values(array_map(
            static fn (ServiceTemplateId $id): int => $id->value,
            $this->repository->findMonitoringConnectorTemplateIds($excludedConnectorSlugs)->toArray(),
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): string => $serviceTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, ServiceTemplate>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (ServiceTemplate $serviceTemplate): int => $serviceTemplate->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertService(
        ?string $name,
        string $register = '0',
        bool $locked = false,
        string $activate = '1',
        ?int $parentId = null,
    ): int {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => $register,
            'service_locked' => $locked ? 1 : 0,
            'service_activate' => $activate,
            'service_template_model_stm_id' => $parentId,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
