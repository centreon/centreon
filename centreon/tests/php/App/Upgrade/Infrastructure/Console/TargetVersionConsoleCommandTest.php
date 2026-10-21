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

use App\Upgrade\Application\Query\GetUpgradeTargetVersionQuery;
use App\Upgrade\Infrastructure\Console\TargetVersionConsoleCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\App\Shared\Double\QueryBusSpy;

final class TargetVersionConsoleCommandTest extends TestCase
{
    public function testPrintsTheVersionThePlatformUpdateReaches(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->result = '26.12.0';

        $tester = $this->tester($queryBus);

        self::assertSame(Command::SUCCESS, $tester->execute(['legacy-version' => '26.10.0']));
        self::assertEquals([new GetUpgradeTargetVersionQuery('26.10.0')], $queryBus->asked);
        self::assertSame("26.12.0\n", $tester->getDisplay());
    }

    public function testReportsAFailureWithoutPrintingAVersion(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->exception = new \RuntimeException('Cannot read the install directory');

        $tester = $this->tester($queryBus);

        self::assertSame(Command::FAILURE, $tester->execute(['legacy-version' => '26.10.0']));
        self::assertStringContainsString('[ERROR] Cannot read the install directory', $tester->getDisplay());
    }

    private function tester(QueryBusSpy $queryBus): CommandTester
    {
        $application = new Application();
        $application->addCommand(new TargetVersionConsoleCommand($queryBus));

        return new CommandTester($application->find('centreon:upgrade:target-version'));
    }
}
