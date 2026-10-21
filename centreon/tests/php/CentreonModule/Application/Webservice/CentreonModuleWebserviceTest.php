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

namespace CentreonModule\Application\Webservice;

use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class CentreonModuleWebserviceTest extends TestCase
{
    private const array BOTH_CLEARS = ['bin/console cache:clear', 'bin/console.new cache:clear'];

    /** @var CacheClearer&object{commands: list<string>} */
    private CacheClearer $cacheClearer;

    private CentreonModuleService&MockObject $moduleService;

    private CentreonModuleWebservice $webservice;

    protected function setUp(): void
    {
        $this->cacheClearer = new class () extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
            }
        };
        $this->moduleService = $this->createMock(CentreonModuleService::class);

        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $this->moduleService;
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $this->cacheClearer;

        $this->webservice = new class () extends CentreonModuleWebservice {
            // The parent constructor opens a database connection the actions under test do not use.
            public function __construct()
            {
            }
        };
        $this->webservice->setDi($container);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testUpdatingAModuleClearsTheCachesOnceForItAndItsDependencies(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module'];
        // Each upgrader (the module and a dependency) asks for a clear.
        $this->moduleService->method('update')->willReturnCallback(function (): Module {
            $this->cacheClearer->clear();
            $this->cacheClearer->clear();

            return $this->module('bam');
        });

        $response = $this->webservice->postUpdate()->jsonSerialize();

        $this->assertTrue($response['status']);
        $this->assertSame(self::BOTH_CLEARS, $this->cacheClearer->commands);
    }

    public function testUpdatingAModuleCanLeaveTheCachesToTheCaller(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module', 'clearCache' => '0'];
        $this->moduleService->method('update')->willReturnCallback(function (): Module {
            $this->cacheClearer->clear();

            return $this->module('bam');
        });

        $this->assertTrue($this->webservice->postUpdate()->jsonSerialize()['status']);
        $this->assertSame([], $this->cacheClearer->commands);
    }

    public function testUpdatingAWidgetDoesNotClearTheCaches(): void
    {
        $_GET = ['id' => 'engine-status', 'type' => 'widget'];
        $this->moduleService->method('update')->willReturn($this->module('engine-status'));

        $this->assertTrue($this->webservice->postUpdate()->jsonSerialize()['status']);
        $this->assertSame([], $this->cacheClearer->commands);
    }

    public function testAFailingUpdateReportsTheErrorAndStillClearsTheCachesForTheUpgradedDependencies(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module'];
        $this->moduleService->method('update')->willThrowException(new \Exception('upgrade.sql failed'));

        $response = $this->webservice->postUpdate()->jsonSerialize();

        $this->assertFalse($response['status']);
        $this->assertSame('upgrade.sql failed', $response['result']->jsonSerialize()['message']);
        $this->assertSame(self::BOTH_CLEARS, $this->cacheClearer->commands);
    }

    public function testASuccessfulUpdateIsReportedAsFailedWhenTheCacheCannotBeCleared(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module'];
        $this->moduleService->method('update')->willReturn($this->module('bam'));
        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $this->moduleService;
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = new class () extends CacheClearer {
            protected function run(array $command): void
            {
                throw new \Symfony\Component\Process\Exception\RuntimeException('cache:clear failed');
            }
        };
        $this->webservice->setDi($container);

        $response = $this->webservice->postUpdate()->jsonSerialize();

        $this->assertFalse($response['status']);
        $this->assertSame('The module was updated, but the cache could not be cleared: cache:clear failed', $response['result']->jsonSerialize()['message']);
    }

    public function testAnUpdateFailingWithAnErrorStillClearsTheCaches(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module'];
        $this->moduleService->method('update')->willThrowException(new \TypeError('upgrade.php failed'));

        $response = $this->webservice->postUpdate()->jsonSerialize();

        $this->assertFalse($response['status']);
        $this->assertSame('upgrade.php failed', $response['result']->jsonSerialize()['message']);
        $this->assertSame(self::BOTH_CLEARS, $this->cacheClearer->commands);
    }

    public function testAFailingUpdateOfABatchLeavesTheCachesToTheCaller(): void
    {
        $_GET = ['id' => 'bam', 'type' => 'module', 'clearCache' => '0'];
        $this->moduleService->method('update')->willThrowException(new \Exception('upgrade.sql failed'));

        $this->assertFalse($this->webservice->postUpdate()->jsonSerialize()['status']);
        $this->assertSame([], $this->cacheClearer->commands);
    }

    public function testClearingTheCachesEndsABatch(): void
    {
        $response = $this->webservice->postClearCache()->jsonSerialize();

        $this->assertTrue($response['status']);
        $this->assertSame(self::BOTH_CLEARS, $this->cacheClearer->commands);
    }

    public function testACacheClearFailureIsReported(): void
    {
        $container = new Container();
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = new class () extends CacheClearer {
            protected function run(array $command): void
            {
                throw new \Symfony\Component\Process\Exception\RuntimeException('cache:clear failed');
            }
        };
        $this->webservice->setDi($container);

        $response = $this->webservice->postClearCache()->jsonSerialize();

        $this->assertFalse($response['status']);
        $this->assertSame('cache:clear failed', $response['result']->jsonSerialize()['message']);
    }

    private function module(string $id): Module
    {
        $module = new Module();
        $module->setId($id);
        $module->setName($id);

        return $module;
    }
}
