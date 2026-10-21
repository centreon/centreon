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

use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Upgrade\Application\Command\MigrateCommand;
use App\Upgrade\Application\Command\RollbackMigrationCommand;
use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Infrastructure\Console\GenerateConsoleCommand;
use App\Upgrade\Infrastructure\Console\MigrateConsoleCommand;
use App\Upgrade\Infrastructure\Console\RollbackConsoleCommand;
use App\Upgrade\Infrastructure\Console\StatusConsoleCommand;
use App\Upgrade\Infrastructure\Doctrine\DdlMigration;
use App\Upgrade\Infrastructure\Doctrine\DmlMigration;
use App\Upgrade\Infrastructure\Doctrine\MigrationGenerator;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class MigrationConsoleCommandsTest extends TestCase
{
    private string $modulesDir;

    protected function setUp(): void
    {
        $this->modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->modulesDir);
    }

    public function testMigrateDispatchesTheCoreMigrationByDefault(): void
    {
        $bus = $this->commandBus(['Centreon\Migrations\Configuration\Version1']);
        $tester = $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals([new MigrateCommand()], $bus->dispatched);
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
        self::assertStringContainsString('1 migration(s) executed.', $tester->getDisplay());
    }

    public function testMigrateDispatchesASingleSet(): void
    {
        $bus = $this->commandBus([]);
        $tester = $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate');

        self::assertSame(Command::SUCCESS, $tester->execute(['--module' => 'centreon-open-tickets']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--set' => 'configuration']));
        self::assertEquals([new MigrateCommand(MigrationSet::module('centreon-open-tickets')), new MigrateCommand(MigrationSet::core('configuration'))], $bus->dispatched);
        self::assertStringContainsString('No migration to execute.', $tester->getDisplay());
    }

    public function testASetAndAModuleAreExclusive(): void
    {
        $bus = $this->commandBus([]);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Choose either --set or --module.');

        try {
            $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate')->execute(['--set' => 'configuration', '--module' => 'centreon-open-tickets']);
        } finally {
            self::assertSame([], $bus->dispatched);
        }
    }

    public function testRejectsAnUnknownCoreSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown core migration set "module:centreon-open-tickets".');

        $this->tester(new MigrateConsoleCommand($this->commandBus([])), 'centreon:migrations:migrate')->execute(['--set' => 'module:centreon-open-tickets']);
    }

    public function testRollbackDispatchesTheSetAndTheTargetVersion(): void
    {
        $bus = $this->commandBus(['Centreon\Migrations\Configuration\Version2']);
        $tester = $this->tester(new RollbackConsoleCommand($bus), 'centreon:migrations:rollback');

        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false]));
        self::assertEquals([new RollbackMigrationCommand(MigrationSet::core('configuration'), 'prev')], $bus->dispatched);
        self::assertStringContainsString('1 migration(s) reverted.', $tester->getDisplay());
    }

    public function testRollbackAsksForAConfirmation(): void
    {
        $bus = $this->commandBus(['Centreon\Migrations\Configuration\Version2']);
        $tester = $this->tester(new RollbackConsoleCommand($bus), 'centreon:migrations:rollback');

        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'first', '--set' => 'configuration']));
        self::assertSame([], $bus->dispatched, 'A declined rollback reverts nothing');
        self::assertStringContainsString('Rollback cancelled.', $tester->getDisplay());

        $tester->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'first', '--set' => 'configuration']));
        self::assertEquals([new RollbackMigrationCommand(MigrationSet::core('configuration'), 'first')], $bus->dispatched);
    }

    public function testRollbackRequiresASet(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Choose a migration set with --set or --module.');

        $this->tester(new RollbackConsoleCommand($this->commandBus([])), 'centreon:migrations:rollback')->execute(['version' => 'prev'], ['interactive' => false]);
    }

    public function testRollbackReportsWhenThereIsNothingToRevert(): void
    {
        $tester = $this->tester(new RollbackConsoleCommand($this->commandBus([])), 'centreon:migrations:rollback');

        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false]));
        self::assertStringContainsString('No migration to revert.', $tester->getDisplay());
    }

    public function testGenerateRefusesToOverwriteAMigration(): void
    {
        $generator = new MigrationGenerator(new MigrationSetRegistry('/nonexistent', $this->modulesDir), new Filesystem());
        $now = new \DateTimeImmutable('2026-10-13 10:00:00', new \DateTimeZone('UTC'));
        $generator->generate(MigrationSet::module('generated'), true, $now);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');

        $generator->generate(MigrationSet::module('generated'), false, $now);
    }

    public function testStatusRendersEverySet(): void
    {
        $queryBus = new class () implements QueryBus {
            /** @var list<object> */
            public array $dispatched = [];

            /**
             * @return list<MigrationSetStatus>
             */
            public function ask(object $query): array
            {
                $this->dispatched[] = $query;

                return [
                    new MigrationSetStatus(MigrationSet::core('realtime'), ['Centreon\Migrations\Realtime\Version1'], []),
                    new MigrationSetStatus(MigrationSet::core('configuration'), [], ['Centreon\Migrations\Configuration\Version1']),
                ];
            }
        };
        $tester = $this->tester(new StatusConsoleCommand($queryBus), 'centreon:migrations:status');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals([new GetMigrationStatusQuery()], $queryBus->dispatched);
        self::assertStringContainsString('Centreon\Migrations\Realtime\Version1', $tester->getDisplay());
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
    }

    public function testGenerateWritesALoadableMigrationOfTheRequestedKind(): void
    {
        $tester = $this->generateTester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--module' => 'generated-ddl', '--ddl' => true]));
        self::assertSame(Command::SUCCESS, $this->generateTester()->execute(['--module' => 'generated-dml', '--dml' => true]));

        $ddl = glob($this->modulesDir . '/generated-ddl/migrations/Version*.php') ?: [];
        $dml = glob($this->modulesDir . '/generated-dml/migrations/Version*.php') ?: [];
        self::assertCount(1, $ddl);
        self::assertCount(1, $dml);

        require_once $ddl[0];
        require_once $dml[0];
        self::assertTrue(is_subclass_of('GeneratedDdl\Migrations\\' . basename($ddl[0], '.php'), DdlMigration::class));
        self::assertTrue(is_subclass_of('GeneratedDml\Migrations\\' . basename($dml[0], '.php'), DmlMigration::class));
    }

    public function testGenerateRequiresExactlyOneKind(): void
    {
        self::assertSame(Command::INVALID, $this->generateTester()->execute(['--set' => 'configuration']));
        self::assertSame(Command::INVALID, $this->generateTester()->execute(['--set' => 'configuration', '--ddl' => true, '--dml' => true]));
    }

    /**
     * @param list<string> $result
     *
     * @return CommandBus&object{dispatched: list<object>}
     */
    private function commandBus(array $result): CommandBus
    {
        return new class ($result) implements CommandBus {
            /** @var list<object> */
            public array $dispatched = [];

            /**
             * @param list<string> $result
             */
            public function __construct(private readonly array $result)
            {
            }

            /**
             * @return list<string>
             */
            public function execute(object $command): array
            {
                $this->dispatched[] = $command;

                return $this->result;
            }
        };
    }

    private function generateTester(): CommandTester
    {
        $generator = new MigrationGenerator(new MigrationSetRegistry('/nonexistent', $this->modulesDir), new Filesystem());

        return $this->tester(new GenerateConsoleCommand($generator), 'centreon:migrations:generate');
    }

    private function tester(callable $command, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($name));
    }
}
