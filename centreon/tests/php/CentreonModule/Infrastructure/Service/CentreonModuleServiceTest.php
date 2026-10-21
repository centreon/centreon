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

namespace CentreonModule\Infrastructure\Service;

use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Source\ModuleSource;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class CentreonModuleServiceTest extends TestCase
{
    public function testAModuleIsOutdatedWhenItsSourceSaysItIsNotUpToDate(): void
    {
        $this->assertTrue(CentreonModuleService::isOutdated($this->module('bam', updated: false)));
        $this->assertFalse(CentreonModuleService::isOutdated($this->module('bam', updated: true)));

        $notInstalled = $this->module('bam', updated: false);
        $notInstalled->setInstalled(false);
        $this->assertFalse(CentreonModuleService::isOutdated($notInstalled), 'A module that is not installed is never outdated');
    }

    public function testTheOutdatedModulesAreTheInstalledOnesTheSourceListsAsNotUpToDate(): void
    {
        $source = $this->createMock(ModuleSource::class);
        $source->expects($this->once())->method('getList')->with(null, true, false)->willReturn([$this->module('bam', updated: false)]);

        $this->assertSame(['bam'], array_map(static fn (Module $module): string => $module->getId(), $this->service($source)->getOutdated(ModuleSource::TYPE)));
        $this->assertSame([], $this->service($source)->getOutdated('unknown'));
    }

    public function testUpdateAllSkipsTheModulesAlreadyUpdatedAsADependency(): void
    {
        $source = $this->createMock(ModuleSource::class);
        $source->method('getList')->willReturn([$this->module('bam', updated: false), $this->module('license-manager', updated: false)]);
        // Updating bam brought its license-manager dependency up to date.
        $source->method('getDetail')->willReturnMap([
            ['bam', $this->module('bam', updated: false)],
            ['license-manager', $this->module('license-manager', updated: true)],
        ]);
        $source->expects($this->once())->method('update')->with('bam')->willReturn($this->module('bam', updated: true));

        $updated = $this->service($source)->updateAll(ModuleSource::TYPE);

        $this->assertSame(['bam'], array_map(static fn (Module $module): string => $module->getId(), $updated));
    }

    public function testUpdateAllAttemptsEveryModuleThenReportsTheFailures(): void
    {
        $source = $this->createMock(ModuleSource::class);
        $source->method('getList')->willReturn([$this->module('bam', updated: false), $this->module('map', updated: false)]);
        $source->method('getDetail')->willReturnCallback(fn (string $id): Module => $this->module($id, updated: false));
        $updatedModules = [];
        $source->expects($this->exactly(2))->method('update')->willReturnCallback(function (string $id) use (&$updatedModules): Module {
            if ($id === 'bam') {
                throw new \Exception('upgrade.sql failed');
            }
            $updatedModules[] = $id;

            return $this->module($id, updated: true);
        });

        try {
            $this->service($source)->updateAll(ModuleSource::TYPE);
            $this->fail('The failure must be reported');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Some updates failed. bam: upgrade.sql failed', $exception->getMessage());
        }
        $this->assertSame(['map'], $updatedModules, 'A failing module does not stop the others');
    }

    public function testUpdateAllIgnoresUnknownTypes(): void
    {
        $this->assertSame([], $this->service($this->createMock(ModuleSource::class))->updateAll('unknown'));
    }

    private function service(ModuleSource $source): CentreonModuleService
    {
        return new class ($this->createMock(ContainerInterface::class), $source) extends CentreonModuleService {
            public function __construct(ContainerInterface $services, private readonly ModuleSource $moduleSource)
            {
                parent::__construct($services);
            }

            protected function initSources(ContainerInterface $services): void
            {
                $this->sources = [ModuleSource::TYPE => $this->moduleSource];
            }
        };
    }

    private function module(string $id, bool $updated): Module
    {
        $module = new Module();
        $module->setId($id);
        $module->setInstalled(true);
        $module->setUpdated($updated);

        return $module;
    }
}
