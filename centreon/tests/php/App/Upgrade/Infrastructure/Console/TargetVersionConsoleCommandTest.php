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

use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Infrastructure\Console\TargetVersionConsoleCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

final class TargetVersionConsoleCommandTest extends TestCase
{
    public function testPrintsTheVersionThePlatformUpdateReaches(): void
    {
        $application = new Application();
        $application->addCommand(new TargetVersionConsoleCommand(new TargetVersionRecorder(new FakeUpdateRepository(), new FakeUpdateScriptFinder(), '26.12.0')));

        $tester = new CommandTester($application->find('centreon:upgrade:target-version'));

        self::assertSame(Command::SUCCESS, $tester->execute(['legacy-version' => '26.10.0']));
        self::assertSame("26.12.0\n", $tester->getDisplay());
    }
}
