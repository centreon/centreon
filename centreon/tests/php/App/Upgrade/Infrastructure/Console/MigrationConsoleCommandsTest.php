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

use App\Upgrade\Application\Command\MigrateCommand;
use App\Upgrade\Application\Command\RollbackMigrationCommand;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Application\Query\GetRollbackPlanQuery;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Console\GenerateConsoleCommand;
use App\Upgrade\Infrastructure\Console\MigrateConsoleCommand;
use App\Upgrade\Infrastructure\Console\RollbackConsoleCommand;
use App\Upgrade\Infrastructure\Console\StatusConsoleCommand;
use App\Upgrade\Infrastructure\Doctrine\MigrationGenerator;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use App\Upgrade\Infrastructure\Migration\DdlMigration;
use App\Upgrade\Infrastructure\Migration\DmlMigration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\Shared\Double\CommandBusSpy;
use Tests\App\Shared\Double\QueryBusSpy;
use Tests\App\Upgrade\Infrastructure\Double\RollbackBusSpy;

final class MigrationConsoleCommandsTest extends TestCase
{
    private string $modulesDir;

    protected function setUp(): void
    {
        $this->modulesDir = sys_get_temp_dir() . '/' . uniqid('modules_', true);
        (new Filesystem())->mkdir([$this->modulesDir . '/generated', $this->modulesDir . '/generated-ddl', $this->modulesDir . '/generated-dml']);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->modulesDir);
    }

    public function testMigrateDispatchesTheCoreMigrationByDefault(): void
    {
        $bus = $this->commandBus(new MigrationOutcome(['Centreon\Migrations\Configuration\Version1']));
        $tester = $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals([new MigrateCommand()], $bus->executed);
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
        self::assertStringContainsString('1 migration(s) executed.', $tester->getDisplay());
    }

    public function testMigrateDispatchesASingleSet(): void
    {
        $bus = $this->commandBus(new MigrationOutcome([]));
        $tester = $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate');

        self::assertSame(Command::SUCCESS, $tester->execute(['--module' => 'centreon-open-tickets']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--set' => 'configuration']));
        self::assertEquals([new MigrateCommand(MigrationSet::module('centreon-open-tickets')), new MigrateCommand(MigrationSet::web(WebMigrationSetEnum::Configuration))], $bus->executed);
        self::assertStringContainsString('No migration to execute.', $tester->getDisplay());
    }

    public function testMigrateShowsTheWarningsOfASuccessfulRun(): void
    {
        $bus = $this->commandBus(new MigrationOutcome([], ['Kept the platform version 26.09.1']));
        $tester = $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('[WARNING] Kept the platform version 26.09.1', $tester->getDisplay());
    }

    public function testASetAndAModuleAreExclusive(): void
    {
        $bus = $this->commandBus(new MigrationOutcome([]));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Choose either --set or --module.');

        try {
            $this->tester(new MigrateConsoleCommand($bus), 'centreon:migrations:migrate')->execute(['--set' => 'configuration', '--module' => 'centreon-open-tickets']);
        } finally {
            self::assertSame([], $bus->executed);
        }
    }

    public function testRejectsAnUnknownWebSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Centreon Web migration set "module:centreon-open-tickets", expected one of: "realtime", "configuration".');

        $this->tester(new MigrateConsoleCommand($this->commandBus(new MigrationOutcome([]))), 'centreon:migrations:migrate')->execute(['--set' => 'module:centreon-open-tickets']);
    }

    public function testRollbackPlansThenRevertsTheTargetVersion(): void
    {
        $bus = $this->rollbackBus(['Centreon\Migrations\Configuration\Version2']);
        $tester = $this->tester(new RollbackConsoleCommand($bus, $bus), 'centreon:migrations:rollback');

        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false]));
        self::assertEquals([
            new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev'),
            new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', ['Centreon\Migrations\Configuration\Version2']),
        ], $bus->dispatched);
        self::assertStringContainsString('[WARNING] 2 rows stay without host', $tester->getDisplay());
        self::assertStringContainsString('1 migration(s) reverted.', $tester->getDisplay());
    }

    public function testRollbackAsksToConfirmTheMigrationsItWillRevert(): void
    {
        $bus = $this->rollbackBus(['Centreon\Migrations\Configuration\Version2']);
        $tester = $this->tester(new RollbackConsoleCommand($bus, $bus), 'centreon:migrations:rollback');

        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'first', '--set' => 'configuration']));
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version2', $tester->getDisplay());
        self::assertStringContainsString('Revert these 1 migration(s) of the "configuration" migration set?', $tester->getDisplay());
        self::assertEquals([new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'first')], $bus->dispatched, 'A declined rollback reverts nothing');
        self::assertStringContainsString('Rollback cancelled.', $tester->getDisplay());

        $tester->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'first', '--set' => 'configuration']));
        self::assertEquals(new RollbackMigrationCommand(MigrationSet::web(WebMigrationSetEnum::Configuration), 'first', ['Centreon\Migrations\Configuration\Version2']), $bus->dispatched[array_key_last($bus->dispatched)]);
    }

    public function testRollbackRequiresASet(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Choose a migration set with --set or --module.');

        $this->tester(new RollbackConsoleCommand($this->rollbackBus([]), $this->rollbackBus([])), 'centreon:migrations:rollback')->execute(['version' => 'prev'], ['interactive' => false]);
    }

    public function testRollbackReportsWhenThereIsNothingToRevertWithoutAsking(): void
    {
        $bus = $this->rollbackBus([]);
        $tester = $this->tester(new RollbackConsoleCommand($bus, $bus), 'centreon:migrations:rollback');

        self::assertSame(Command::SUCCESS, $tester->execute(['version' => 'prev', '--set' => 'configuration']));
        self::assertStringContainsString('No migration to revert.', $tester->getDisplay());
        self::assertEquals([new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev')], $bus->dispatched);
    }

    public function testRollbackRefusesAnUnknownTargetBeforeAsking(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->exception = new \RuntimeException('The "configuration" migration set has no "2026010100000" migration');

        $commandBus = $this->rollbackBus([]);
        $tester = $this->tester(new RollbackConsoleCommand($queryBus, $commandBus), 'centreon:migrations:rollback');

        self::assertSame(Command::FAILURE, $tester->execute(['version' => '2026010100000', '--set' => 'configuration']));
        self::assertStringContainsString('has no "2026010100000" migration', $tester->getDisplay());
        self::assertStringNotContainsString('Revert', $tester->getDisplay(), 'Nothing is asked');
        self::assertSame([], $commandBus->dispatched, 'Nothing is reverted');
    }

    #[DataProvider('failures')]
    public function testRollbackFailsWithOneWhateverTheCodeOfTheFailure(\Throwable $failure): void
    {
        $commandBus = $this->failingBus($failure);
        $tester = $this->tester(new RollbackConsoleCommand($this->rollbackBus(['Centreon\Migrations\Configuration\Version1']), $commandBus), 'centreon:migrations:rollback');

        self::assertSame(Command::FAILURE, $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false]));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
        self::assertStringNotContainsString('Stack trace:', $tester->getDisplay());

        $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString('Stack trace:', $tester->getDisplay());
    }

    #[DataProvider('failures')]
    public function testMigrateFailsWithOneWhateverTheCodeOfTheFailure(\Throwable $failure): void
    {
        $commandBus = $this->failingBus($failure);
        $tester = $this->tester(new MigrateConsoleCommand($commandBus), 'centreon:migrations:migrate');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
        self::assertStringNotContainsString('Stack trace:', $tester->getDisplay());

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString('Stack trace:', $tester->getDisplay());
    }

    public function testMigrateShowsTheExecutedMigrationsBeforeAFailedLockRelease(): void
    {
        $outcome = new MigrationOutcome(['Centreon\Migrations\Configuration\Version1'], ['Kept the platform version 26.09.1']);
        $tester = $this->tester(new MigrateConsoleCommand($this->failingBus(new LockNotReleasedException(new \RuntimeException('Semaphore removed'), $outcome))), 'centreon:migrations:migrate');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('[WARNING] Kept the platform version 26.09.1', $tester->getDisplay());
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
        self::assertStringContainsString('releasing the lock failed: Semaphore removed', $tester->getDisplay());
    }

    public function testRollbackShowsTheRevertedMigrationsBeforeAFailedLockRelease(): void
    {
        $plan = ['Centreon\Migrations\Configuration\Version2'];
        $commandBus = $this->failingBus(new LockNotReleasedException(new \RuntimeException('Semaphore removed'), new MigrationOutcome($plan, ['2 rows stay without host'])));
        $tester = $this->tester(new RollbackConsoleCommand($this->rollbackBus($plan), $commandBus), 'centreon:migrations:rollback');

        self::assertSame(Command::FAILURE, $tester->execute(['version' => 'prev', '--set' => 'configuration'], ['interactive' => false]));
        self::assertSame(2, mb_substr_count($tester->getDisplay(), 'Centreon\Migrations\Configuration\Version2'), 'Listed in the plan, then as reverted');
        self::assertStringContainsString('[WARNING] 2 rows stay without host', $tester->getDisplay());
        self::assertStringContainsString('1 migration(s) reverted.', $tester->getDisplay());
        self::assertStringContainsString('releasing the lock failed: Semaphore removed', $tester->getDisplay());
    }

    public function testStatusFailsOnTheIgnoredModuleDirectories(): void
    {
        (new Filesystem())->mkdir($this->modulesDir . '/centreon-foo.bak/migrations');
        $queryBus = new QueryBusSpy();
        $queryBus->result = [];

        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('[ERROR] Ignored the migrations of', $tester->getDisplay());
        self::assertStringContainsString('centreon-foo.bak', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, string>, MigrationSet}>
     */
    public static function provideRequestedSets(): iterable
    {
        yield 'a Centreon Web set' => [['--set' => 'configuration'], MigrationSet::web(WebMigrationSetEnum::Configuration)];

        yield 'a module' => [['--module' => 'centreon-open-tickets'], MigrationSet::module('centreon-open-tickets')];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('provideRequestedSets')]
    public function testStatusOfARequestedSetLeavesTheIgnoredModuleDirectoriesOut(array $options, MigrationSet $set): void
    {
        (new Filesystem())->mkdir($this->modulesDir . '/centreon-foo.bak/migrations');
        $queryBus = new QueryBusSpy();
        $queryBus->result = [MigrationSetStatus::read($set, [], [])];

        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        self::assertSame(Command::SUCCESS, $tester->execute($options));
        self::assertEquals([new GetMigrationStatusQuery($set)], $queryBus->asked);
        self::assertStringNotContainsString('Ignored the migrations', $tester->getDisplay());
    }

    public function testGenerateNamesACentreonWebMigrationAfterTheUtcTime(): void
    {
        $generator = new MigrationGenerator(new MigrationSetRegistry($this->modulesDir, '/nonexistent', new NullLogger()), new Filesystem());

        $path = $generator->generate(MigrationSet::web(WebMigrationSetEnum::Configuration), false, new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('Europe/Paris')));

        self::assertSame($this->modulesDir . '/migrations/configuration/Version20261013100000.php', $path);
        self::assertStringContainsString('namespace Centreon\Migrations\Configuration;', (string) file_get_contents($path));
    }

    public function testGenerateRefusesToOverwriteAMigration(): void
    {
        $generator = new MigrationGenerator(new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger()), new Filesystem());
        $now = new \DateTimeImmutable('2026-10-13 10:00:00', new \DateTimeZone('UTC'));
        $generator->generate(MigrationSet::module('generated'), true, $now);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');

        $generator->generate(MigrationSet::module('generated'), false, $now);
    }

    public function testStatusRendersEverySet(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->result = [
            MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Realtime), ['Centreon\Migrations\Realtime\Version1'], []),
            MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), [], ['Centreon\Migrations\Configuration\Version1']),
        ];
        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals([new GetMigrationStatusQuery()], $queryBus->asked);
        self::assertStringContainsString('Centreon\Migrations\Realtime\Version1', $tester->getDisplay());
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
    }

    public function testVerboseStatusListsEveryExecutedMigration(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->result = [MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), ['Centreon\Migrations\Configuration\Version1', 'Centreon\Migrations\Configuration\Version2'], [])];

        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        $tester->execute([]);
        self::assertStringNotContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
    }

    public function testStatusFailsOnASetMigrateWouldRefuseButStillRendersTheOthers(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->result = [
            MigrationSetStatus::unreadable(MigrationSet::web(WebMigrationSetEnum::Realtime), 'The "realtime" migration set has executed migrations that are not available anymore'),
            MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), [], ['Centreon\Migrations\Configuration\Version1'], 'The update scripts of 26.12.0 are pending'),
        ];
        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        self::assertSame(Command::FAILURE, $tester->execute([]), 'An unreadable set prevails over a refused one');
        self::assertStringContainsString('migrations that are not available anymore', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertStringContainsString('The update scripts of 26.12.0 are pending', $tester->getDisplay(), 'A refusal does not hide the migrations it could read');
        self::assertStringContainsString('Centreon\Migrations\Configuration\Version1', $tester->getDisplay());
    }

    public function testStatusRendersARefusedSetWithoutMigrationYet(): void
    {
        $queryBus = new QueryBusSpy();
        $queryBus->result = [MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), [], [], 'The update scripts of 26.12.0 are pending')];

        $tester = $this->tester(new StatusConsoleCommand($queryBus, new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger())), 'centreon:migrations:status');

        self::assertSame(StatusConsoleCommand::REFUSED, $tester->execute([]), 'A refusal is told apart from a broken set');
        self::assertStringContainsString('Last executed', $tester->getDisplay(), 'A refused set still shows what it read');
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

    public function testAGeneratedMigrationFailsClearlyUntilItIsWritten(): void
    {
        self::assertSame(Command::SUCCESS, $this->generateTester()->execute(['--module' => 'generated', '--ddl' => true]));
        $file = (glob($this->modulesDir . '/generated/migrations/Version*.php') ?: [])[0];
        require_once $file;
        $class = 'Generated\Migrations\\' . basename($file, '.php');
        /** @var DdlMigration $migration */
        $migration = new $class(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), new NullLogger());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Not written yet');

        $migration->up(new Schema());
    }

    public function testGenerateRefusesAModuleThatDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The module "generated-typo" does not exist');

        try {
            $this->generateTester()->execute(['--module' => 'generated-typo', '--ddl' => true]);
        } finally {
            self::assertDirectoryDoesNotExist($this->modulesDir . '/generated-typo');
        }
    }

    public function testGenerateRefusesAModuleThatIsAFile(): void
    {
        (new Filesystem())->dumpFile($this->modulesDir . '/generated-file', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The module "generated-file" does not exist');

        $this->generateTester()->execute(['--module' => 'generated-file', '--ddl' => true]);
    }

    public function testGenerateRequiresExactlyOneKind(): void
    {
        self::assertSame(Command::INVALID, $this->generateTester()->execute(['--set' => 'configuration']));
        self::assertSame(Command::INVALID, $this->generateTester()->execute(['--set' => 'configuration', '--ddl' => true, '--dml' => true]));
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'driver error code' => [new \RuntimeException('Connection refused', 2002)];

        yield 'error' => [new \TypeError('Connection refused')];
    }

    private function failingBus(\Throwable $failure): CommandBusSpy
    {
        $bus = new CommandBusSpy();
        $bus->exception = $failure;

        return $bus;
    }

    private function commandBus(MigrationOutcome $result): CommandBusSpy
    {
        $bus = new CommandBusSpy();
        $bus->result = $result;

        return $bus;
    }

    /**
     * @param list<string> $plan
     */
    private function rollbackBus(array $plan): RollbackBusSpy
    {
        return new RollbackBusSpy($plan, ['2 rows stay without host']);
    }

    private function generateTester(): CommandTester
    {
        $generator = new MigrationGenerator(new MigrationSetRegistry('/nonexistent', $this->modulesDir, new NullLogger()), new Filesystem());

        return $this->tester(new GenerateConsoleCommand($generator), 'centreon:migrations:generate');
    }

    private function tester(callable $command, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($name));
    }
}
