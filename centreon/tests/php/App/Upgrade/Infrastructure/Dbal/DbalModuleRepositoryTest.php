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

namespace Tests\App\Upgrade\Infrastructure\Dbal;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use App\Upgrade\Infrastructure\Dbal\DbalModuleRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class DbalModuleRepositoryTest extends TestCase
{
    private Connection&MockObject $configConnection;

    private Connection&MockObject $realtimeConnection;

    private Filesystem $filesystem;

    private string $modulesDir;

    private DbalModuleRepository $repository;

    protected function setUp(): void
    {
        $this->configConnection = $this->createMock(Connection::class);
        $this->realtimeConnection = $this->createMock(Connection::class);
        $this->filesystem = new Filesystem();

        $baseTmpDir = sys_get_temp_dir() . '/centreon-module-test-' . uniqid();
        $this->modulesDir = $baseTmpDir . '/modules';

        $this->filesystem->mkdir([$this->modulesDir]);

        $this->repository = new DbalModuleRepository(
            $this->configConnection,
            $this->realtimeConnection,
            $this->modulesDir,
            new ConnectionConfig('localhost', 'centreon', 'password', 'centreon', 'centreon_storage'),
            '/usr/share/centreon/',
            new NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(dirname($this->modulesDir));
    }

    public function testUpdateAllNoInstalledModules(): void
    {
        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->configConnection->expects(self::never())->method('executeStatement');

        $this->repository->updateAll();
    }

    public function testUpdateAllAlreadyUpToDate(): void
    {
        $this->createModuleConfFile('my-module', '1.0.0');

        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'my-module', 'mod_release' => '1.0.0'],
            ]);

        $this->configConnection->expects(self::never())->method('executeStatement');
        $this->configConnection->expects(self::never())->method('fetchOne');

        $this->repository->updateAll();
    }

    public function testUpdateAllUpgradesOutdatedModule(): void
    {
        $this->createModuleConfFile('my-module', '2.0.0');

        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'my-module', 'mod_release' => '1.0.0'],
            ]);

        $this->configConnection
            ->method('fetchOne')
            ->willReturn(42);

        $executedStatements = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 1;
            });

        $this->repository->updateAll();

        self::assertNotEmpty($executedStatements, 'Expected SQL statements to be executed');
        self::assertTrue(
            $this->containsQuery($executedStatements, 'UPDATE `modules_informations` SET `name`'),
            'Expected metadata update'
        );
        self::assertTrue(
            $this->containsQuery($executedStatements, 'UPDATE `modules_informations` SET `mod_release`'),
            'Expected version update'
        );
    }

    public function testUpdateAllRunsUpgradeScriptsInOrder(): void
    {
        $this->createModuleConfFile('my-module', '3.0.0');
        $this->createModuleUpgradeDir('my-module', '2.0.0');
        $this->createModuleUpgradeDir('my-module', '3.0.0');
        $this->createModuleUpgradeDir('my-module', '1.5.0');

        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'my-module', 'mod_release' => '1.0.0'],
            ]);

        $this->configConnection
            ->method('fetchOne')
            ->willReturn(42);

        $versionUpdates = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$versionUpdates): int {
                if (str_contains($sql, '`mod_release` = :version') && isset($params['version'])) {
                    $versionUpdates[] = $params['version'];
                }

                return 1;
            });

        $this->repository->updateAll();

        self::assertSame(['1.5.0', '2.0.0', '3.0.0', '3.0.0'], $versionUpdates);
    }

    public function testUpdateAllSkipsOlderVersions(): void
    {
        $this->createModuleConfFile('my-module', '2.0.0');
        $this->createModuleUpgradeDir('my-module', '1.0.0');
        $this->createModuleUpgradeDir('my-module', '2.0.0');

        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'my-module', 'mod_release' => '1.5.0'],
            ]);

        $this->configConnection
            ->method('fetchOne')
            ->willReturn(42);

        $versionUpdates = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$versionUpdates): int {
                if (str_contains($sql, '`mod_release` = :version') && isset($params['version'])) {
                    $versionUpdates[] = $params['version'];
                }

                return 1;
            });

        $this->repository->updateAll();

        self::assertSame(['2.0.0', '2.0.0'], $versionUpdates);
    }

    private function createModuleConfFile(string $moduleName, string $version): void
    {
        $moduleDir = $this->modulesDir . '/' . $moduleName;
        $this->filesystem->mkdir($moduleDir);

        $content = <<<PHP
            <?php
            \$module_conf['{$moduleName}'] = [
                'name' => '{$moduleName}',
                'rname' => 'Test Module',
                'mod_release' => '{$version}',
                'is_removeable' => '1',
                'infos' => '',
                'author' => 'Test',
                'svc_tools' => '0',
                'host_tools' => '0',
            ];
            PHP;

        file_put_contents($moduleDir . '/conf.php', $content);
    }

    private function createModuleUpgradeDir(string $moduleName, string $version): void
    {
        $this->filesystem->mkdir(
            $this->modulesDir . '/' . $moduleName . '/upgrade/' . $version
        );
    }

    /**
     * @param string[] $statements
     */
    private function containsQuery(array $statements, string $needle): bool
    {
        foreach ($statements as $statement) {
            if (str_contains($statement, $needle)) {
                return true;
            }
        }

        return false;
    }
}
