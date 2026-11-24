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

namespace Tests\CentreonLegacy\Core\Utils;

use Centreon\Test\Mock;
use Centreon\Test\Mock\DependencyInjector\ServiceContainer;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonLegacy\Core\Utils\ExecutedQueries;
use CentreonLegacy\Core\Utils\ExecutedQueriesChangedException;
use CentreonLegacy\Core\Utils\Utils;
use CentreonLegacy\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Psr11\Container;
use VirtualFileSystem\FileSystem;
use VirtualFileSystem\Structure\File;

/**
 * @group CentreonLegacy
 * @group CentreonLegacy\Utils
 */
class UtilsTest extends TestCase
{
    /** @var FileSystem */
    public $fileSystem;

    /** @var ServiceContainer */
    public $container;

    /** @var Utils */
    public $service;

    public function setUp(): void
    {
        // mount VFS
        $this->fileSystem = new FileSystem();
        $this->fileSystem->createDirectory('/tmp');

        $this->container = new ServiceContainer();
        $this->container[ServiceProvider::CONFIGURATION] = $this->createMock(Configuration::class);
        $this->container['configuration_db'] = new Mock\CentreonDB();
        $this->container['configuration_db']->addResultSet("SELECT 'OK';", []);

        $this->service = new Utils(new Container($this->container));
    }

    public function tearDown(): void
    {
        $this->container->terminate();
        $this->container = null;
    }

    /**
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
     */
    public function testObjectIntoArray(): void
    {
        $object = new \stdClass();
        $object->message = 'test';
        $object->subMessage = ['test'];

        $value = [
            'message' => 'test',
            'subMessage' => [
                'test',
            ],
        ];

        $result = $this->service->objectIntoArray($object);

        $this->assertEquals($result, $value);
    }

    /**
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
     */
    public function testObjectIntoArrayWithSkippedKeys(): void
    {
        $object = new \stdClass();
        $object->message = 'test';
        $object->subMessage = ['test'];

        $value = [
            'message' => 'test',
        ];

        $result = $this->service->objectIntoArray($object, ['subMessage']);

        $this->assertEquals($result, $value);
    }

    /**
     * @covers \CentreonLegacy\Core\Utils\Utils::objectIntoArray
     */
    public function testObjectIntoArrayWithEmptyObject(): void
    {
        $result = $this->service->objectIntoArray(new \stdClass());

        $this->assertEmpty($result);
    }

    public function testBuildPath(): void
    {
        $endPath = '.';

        $result = $this->service->buildPath($endPath);

        $this->assertStringEndsWith('www', $result);
    }

    public function testRequireConfiguration(): void
    {
        $configurationFile = '';
        $type = '';

        $result = $this->service->requireConfiguration($configurationFile, $type);

        $this->assertEmpty($result);
    }

    /**
     * Unable to find the wrapper "vfs" can't be tested
     *
     * @todo the method must be refactored
     */
    public function testExecutePhpFileWithUnexistsFile(): void
    {
        $fileName = $this->fileSystem->path('/tmp/conf2.php');
        $this->expectException(\Exception::class);
        $this->service->executePhpFile($fileName);
    }

    public function testAPhpFileCannotRunTwiceInTheSameProcess(): void
    {
        $this->container['realtime_db'] = new Mock\CentreonDB();
        $fileName = tempnam(sys_get_temp_dir(), 'upgrade_');
        file_put_contents($fileName, '<?php $GLOBALS["upgrade_runs"] = ($GLOBALS["upgrade_runs"] ?? 0) + 1;');

        try {
            $this->service->executePhpFile($fileName);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('already ran in this process');
            $this->service->executePhpFile($fileName);
        } finally {
            $this->assertSame(1, $GLOBALS['upgrade_runs']);
            unlink($fileName);
        }
    }

    public function testExecuteSqlFile(): void
    {
        $file = $this->fileSystem->createFile('/tmp/conf.sql', "SELECT 'OK';");
        $this->assertInstanceOf(File::class, $file);
        $fileName = $this->fileSystem->path('/tmp/conf.sql');
        $this->assertIsString($fileName);
        try {
            $isException = false;
            $this->service->executeSqlFile($fileName);
        } catch (\Exception) {
            $isException = true;
        }
        $this->assertFalse($isException);
    }

    public function testExecuteSqlFileSkipsTheQueriesAlreadyExecutedAndReportsProgress(): void
    {
        $content = "SELECT 1;\nSELECT 2;\nSELECT 3;\n";
        $alreadyExecuted = $this->executedQueries($content, 1);
        $this->fileSystem->createFile('/tmp/upgrade.sql', $content);
        // The first query is not registered: executing it again would throw.
        $this->container['configuration_db']->addResultSet('SELECT 2;', []);
        $this->container['configuration_db']->addResultSet('SELECT 3;', []);

        $this->assertSame([2, 3], $this->executeAndReportCounts($alreadyExecuted));
    }

    public function testExecuteSqlFileReportsOnlyTheQueriesThatPassed(): void
    {
        $this->fileSystem->createFile('/tmp/upgrade.sql', "SELECT 1;\nSELECT 2;\nSELECT 3;\n");
        $this->container['configuration_db'] = new class () extends Mock\CentreonDB {
            public function query($queryString, $parameters = null, ...$parametersArgs): Mock\CentreonDBStatement|false
            {
                if ($queryString === 'SELECT 2;') {
                    throw new \PDOException('Duplicate column name');
                }

                return parent::query($queryString, $parameters, ...$parametersArgs);
            }
        };
        $this->container['configuration_db']->addResultSet('SELECT 1;', []);
        $this->container['configuration_db']->addResultSet('SELECT 3;', []);

        $executed = [];
        try {
            $this->executeAndReportCounts(null, $executed);
            $this->fail('The failing query must propagate');
        } catch (\PDOException $exception) {
            $this->assertSame('Duplicate column name', $exception->getMessage());
        }

        $this->assertSame([1], $executed);
    }

    public function testExecuteSqlFileCountsADelimitedBlockAsASingleQueryWhenSkipping(): void
    {
        $content = "-- A comment; not a query\nSELECT 1;\nDELIMITER \$\nCREATE PROCEDURE p() BEGIN\n  SELECT 2;\nEND\$\nDELIMITER ;\nSELECT 3;\n";
        $alreadyExecuted = $this->executedQueries($content, 2);
        $this->fileSystem->createFile('/tmp/upgrade.sql', $content);
        // Only the last query is registered: executing one of the first two again would throw.
        $this->container['configuration_db']->addResultSet('SELECT 3;', []);

        $this->assertSame([3], $this->executeAndReportCounts($alreadyExecuted));
    }

    public function testExecuteSqlFileResumesAfterAFixOfTheFailedQuery(): void
    {
        $alreadyExecuted = $this->executedQueries("SELECT 1;\nSELECT 2 FROM;\n", 1);
        $this->fileSystem->createFile('/tmp/upgrade.sql', "SELECT 1;\nSELECT 2;\n");
        $this->container['configuration_db']->addResultSet('SELECT 2;', []);

        $this->assertSame([2], $this->executeAndReportCounts($alreadyExecuted));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideChangedExecutedQueries(): iterable
    {
        yield 'an executed query changed' => ["SELECT 10;\nSELECT 2;\nSELECT 3;\n"];

        yield 'an executed query removed' => ["SELECT 2;\nSELECT 3;\n"];

        yield 'fewer queries than executed' => ["SELECT 1;\n"];
    }

    #[DataProvider('provideChangedExecutedQueries')]
    public function testExecuteSqlFileRefusesToResumeWhenTheExecutedQueriesChanged(string $content): void
    {
        $alreadyExecuted = $this->executedQueries("SELECT 1;\nSELECT 2;\nSELECT 3;\n", 2);
        $this->fileSystem->createFile('/tmp/upgrade.sql', $content);

        $executed = [];
        try {
            $this->executeAndReportCounts($alreadyExecuted, $executed);
            $this->fail('A changed file must not be resumed');
        } catch (ExecutedQueriesChangedException $exception) {
            $this->assertStringContainsString('The first 2 queries of', $exception->getMessage());
        }

        $this->assertSame([], $executed);
    }

    public function testExecuteSqlFileWithWithUnexistsFileAndRealtimeDb(): void
    {
        $fileName = $this->fileSystem->path('/tmp/conf2.sql');
        $result = null;

        try {
            $this->service->executeSqlFile($fileName, [], true);
        } catch (\Exception $ex) {
            $result = $ex;
        }

        $this->assertInstanceOf(\Exception::class, $result);
    }

    /**
     * @param list<int> $executed the counts reported so far, kept when the execution fails
     *
     * @return list<int>
     */
    private function executeAndReportCounts(?ExecutedQueries $alreadyExecuted, array &$executed = []): array
    {
        $this->service->executeSqlFile(
            $this->fileSystem->path('/tmp/upgrade.sql'),
            [],
            false,
            $alreadyExecuted,
            static function (ExecutedQueries $progress) use (&$executed): void {
                $executed[] = $progress->count;
            }
        );

        return $executed;
    }

    /**
     * What a previous attempt recorded after the first $count queries of $content.
     */
    private function executedQueries(string $content, int $count): ExecutedQueries
    {
        $this->fileSystem->createFile('/tmp/previous.sql', $content);
        $acceptingAll = new class () extends Mock\CentreonDB {
            public function query($queryString, $parameters = null, ...$parametersArgs): Mock\CentreonDBStatement|false
            {
                return false;
            }
        };
        $container = new ServiceContainer();
        $container[ServiceProvider::CONFIGURATION] = $this->createMock(Configuration::class);
        $container['configuration_db'] = $acceptingAll;

        $recorded = null;
        (new Utils(new Container($container)))->executeSqlFile(
            $this->fileSystem->path('/tmp/previous.sql'),
            [],
            false,
            null,
            static function (ExecutedQueries $progress) use ($count, &$recorded): void {
                if ($progress->count === $count) {
                    $recorded = $progress;
                }
            }
        );
        $container->terminate();
        $this->assertInstanceOf(ExecutedQueries::class, $recorded);

        return $recorded;
    }
}
