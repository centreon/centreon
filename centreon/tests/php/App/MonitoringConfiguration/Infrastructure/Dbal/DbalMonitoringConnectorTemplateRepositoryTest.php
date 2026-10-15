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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalMonitoringConnectorTemplateRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The plugin pack manager tables belong to its module, absent from the test database: they are
 * created as temporary tables, which neither commit the test's transaction nor outlive it.
 */
final class DbalMonitoringConnectorTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private string $tag;

    private int $paidTemplateId;

    private int $paidChildTemplateId;

    private int $freeTemplateId;

    private int $standaloneTemplateId;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();

        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack (pluginpack_id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(255) NOT NULL)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE mod_ppm_pluginpack_host (pluginpack_id INT NOT NULL, host_id INT NOT NULL)'
        );

        $this->paidTemplateId = $this->insertHostTemplate('paid');
        $this->paidChildTemplateId = $this->insertHostTemplate('paid-custom', parentId: $this->paidTemplateId);
        $this->freeTemplateId = $this->insertHostTemplate('free');
        $this->standaloneTemplateId = $this->insertHostTemplate('standalone');
        $this->insertConnector('cloud-aws-ec2-' . $this->tag, $this->paidTemplateId);
        $this->insertConnector('base-generic', $this->freeTemplateId);
        // A host using a paid template is not a template: it is never returned.
        $this->insertHost('host', templateId: $this->paidTemplateId);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack_host');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack');

        parent::tearDown();
    }

    public function testItReturnsTheConnectorTemplatesAndTheTemplatesInheritingFromThem(): void
    {
        $this->installPluginPackManager();

        $ids = $this->findTemplateIds(['base-generic']);

        self::assertContains($this->paidTemplateId, $ids);
        self::assertContains($this->paidChildTemplateId, $ids);
        self::assertNotContains($this->freeTemplateId, $ids);
        self::assertNotContains($this->standaloneTemplateId, $ids);
    }

    public function testItReturnsEveryConnectorTemplateWithoutExcludedConnectors(): void
    {
        $this->installPluginPackManager();

        $ids = $this->findTemplateIds([]);

        self::assertContains($this->paidTemplateId, $ids);
        self::assertContains($this->freeTemplateId, $ids);
        self::assertNotContains($this->standaloneTemplateId, $ids);
    }

    public function testItReturnsNothingWithoutThePluginPackManager(): void
    {
        $this->installPluginPackManager(installed: false);

        self::assertSame([], $this->findTemplateIds([]));
    }

    private function installPluginPackManager(bool $installed = true): void
    {
        // The test database may already list it: only the requested state is kept.
        $this->connection->executeStatement("DELETE FROM modules_informations WHERE name = 'centreon-pp-manager'");
        if ($installed) {
            $this->connection->insert('modules_informations', ['name' => 'centreon-pp-manager']);
        }
    }

    /**
     * @param list<string> $excludedSlugs
     *
     * @return list<int>
     */
    private function findTemplateIds(array $excludedSlugs): array
    {
        $repository = new DbalMonitoringConnectorTemplateRepository($this->connection, new DbalModuleRepository($this->connection));

        return array_values(array_map(
            static fn (HostTemplateId $id): int => $id->value,
            $repository->findTemplateIds($excludedSlugs)->toArray(),
        ));
    }

    private function insertHostTemplate(string $name, ?int $parentId = null): int
    {
        $this->connection->insert('host', ['host_name' => "{$name}-{$this->tag}", 'host_register' => '0', 'host_activate' => '1']);
        $id = (int) $this->connection->lastInsertId();
        if ($parentId !== null) {
            $this->connection->insert('host_template_relation', ['host_host_id' => $id, 'host_tpl_id' => $parentId, '`order`' => 1]);
        }

        return $id;
    }

    private function insertHost(string $name, int $templateId): void
    {
        $this->connection->insert('host', ['host_name' => "{$name}-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $this->connection->insert('host_template_relation', [
            'host_host_id' => (int) $this->connection->lastInsertId(),
            'host_tpl_id' => $templateId,
            '`order`' => 1,
        ]);
    }

    private function insertConnector(string $slug, int $templateId): void
    {
        $this->connection->insert('mod_ppm_pluginpack', ['slug' => $slug]);
        $this->connection->insert('mod_ppm_pluginpack_host', [
            'pluginpack_id' => (int) $this->connection->lastInsertId(),
            'host_id' => $templateId,
        ]);
    }
}
