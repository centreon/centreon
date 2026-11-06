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

namespace Tests\App\Upgrade\Infrastructure\Console;

use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Infrastructure\Doctrine\DoctrineMigrationRunner;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;

final class StatusConsoleCommandKernelTest extends KernelTestCase
{
    public function testTheContainerWiresTheDoctrineRunner(): void
    {
        self::assertInstanceOf(DoctrineMigrationRunner::class, self::getContainer()->get(MigrationRunner::class));
    }

    public function testTheStatusCommandRunsThroughTheKernel(): void
    {
        $kernel = self::bootKernel();
        $runner = new FakeMigrationRunner();
        $runner->sets = MigrationSet::webSets();
        self::getContainer()->set(MigrationRunner::class, $runner);
        self::getContainer()->set(ModuleRepository::class, new FakeModuleRepository());

        $tester = new CommandTester((new Application($kernel))->find('centreon:migrations:status'));
        $tester->execute([]);

        self::assertStringContainsString('"realtime" migration set', $tester->getDisplay());
        self::assertStringContainsString('"configuration" migration set', $tester->getDisplay());
        self::assertEquals(MigrationSet::webSets(), $runner->statusRequests);
    }
}
