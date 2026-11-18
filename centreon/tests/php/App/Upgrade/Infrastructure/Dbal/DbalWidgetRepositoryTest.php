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

use App\Upgrade\Infrastructure\Dbal\DbalWidgetRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class DbalWidgetRepositoryTest extends TestCase
{
    private Connection&MockObject $configConnection;

    private Filesystem $filesystem;

    private string $widgetsDir;

    private DbalWidgetRepository $repository;

    protected function setUp(): void
    {
        $this->configConnection = $this->createMock(Connection::class);
        $this->filesystem = new Filesystem();

        $baseTmpDir = sys_get_temp_dir() . '/centreon-widget-test-' . uniqid();
        $this->widgetsDir = $baseTmpDir . '/widgets';

        $this->filesystem->mkdir([$this->widgetsDir]);

        $this->repository = new DbalWidgetRepository(
            $this->configConnection,
            $this->widgetsDir,
            new NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(dirname($this->widgetsDir));
    }

    public function testUpdateAllNoInstalledWidgets(): void
    {
        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->configConnection->expects(self::never())->method('executeStatement');

        $this->repository->updateAll();
    }

    public function testUpdateAllAlreadyUpToDate(): void
    {
        $this->createWidgetConfigFile('my-widget', '1.0.0');

        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['widget_model_id' => '1', 'directory' => 'my-widget', 'version' => '1.0.0'],
            ]);

        $this->configConnection->expects(self::never())->method('executeStatement');

        $this->repository->updateAll();
    }

    public function testUpdateAllUpgradesOutdatedWidget(): void
    {
        $this->createWidgetConfigFile('my-widget', '2.0.0');

        $fetchCallCount = 0;
        $this->configConnection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function () use (&$fetchCallCount): array {
                $fetchCallCount++;
                if ($fetchCallCount === 1) {
                    return [
                        ['widget_model_id' => '1', 'directory' => 'my-widget', 'version' => '1.0.0'],
                    ];
                }

                return [];
            });

        $executedStatements = [];
        $this->configConnection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 1;
            });

        $this->repository->updateAll();

        self::assertTrue(
            $this->containsQuery($executedStatements, 'UPDATE `widget_models`'),
            'Expected widget_models update'
        );
    }

    private function createWidgetConfigFile(string $widgetName, string $version): void
    {
        $widgetDir = $this->widgetsDir . '/' . $widgetName;
        $this->filesystem->mkdir($widgetDir);

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <configs>
                <title>Test Widget</title>
                <description>A test widget</description>
                <author>Test</author>
                <url>./widgets/{$widgetName}/index.php</url>
                <version>{$version}</version>
                <autoRefresh>30</autoRefresh>
            </configs>
            XML;

        file_put_contents($widgetDir . '/configs.xml', $xml);
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
