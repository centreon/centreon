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

namespace Tests\App\Upgrade\Infrastructure\Legacy;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The install and upgrade wizard steps, run as the web server runs them: their JSON response drives the wizard.
 */
final class WizardStepsTest extends TestCase
{
    private const MODULE = 'centreon-wizard-fixture';
    private const MODULE_PATH = 'www/modules/' . self::MODULE;

    public function testTheInstallWizardAppliesTheMigrations(): void
    {
        self::assertSame(['id' => 'migrations', 'result' => 0, 'msg' => 'OK'], $this->runStep('www/install/steps/process/migrateDatabases.php'));
    }

    public function testTheUpgradeWizardAppliesTheMigrations(): void
    {
        self::assertSame(0, $this->runStep('www/install/step_upgrade/process/process_migrations.php')['result']);
    }

    public function testTheUpgradeWizardListsTheOutdatedModules(): void
    {
        $this->withModule(self::MODULE_PATH, "<?php\n", function (\CentreonDB $db): void {
            self::assertContains(self::MODULE, $this->listModules());

            $db->query("UPDATE `modules_informations` SET `mod_release` = '1.0.1' WHERE `name` = '" . self::MODULE . "'");
            self::assertNotContains(self::MODULE, $this->listModules(), 'An up-to-date module is not listed');
        });
    }

    public function testTheUpgradeWizardUpgradesAnOutdatedModule(): void
    {
        $this->withModule(self::MODULE_PATH, "<?php\necho 'noise';\n", function (): void {
            self::assertSame(
                ['result' => 0, 'msg' => 'OK'],
                $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'update', 'id' => self::MODULE]),
                'The output of the upgrade scripts is not part of the response',
            );
            self::assertSame('1.0.1', $this->moduleRelease(self::MODULE));
            self::assertNotContains(self::MODULE, $this->listModules());
        });
    }

    public function testTheUpgradeWizardReportsAnUnknownModule(): void
    {
        self::assertSame(
            ['result' => 0, 'warning' => true, 'msg' => 'Module not found'],
            $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'update', 'id' => 'centreon-wizard-unknown']),
        );
    }

    public function testTheUpgradeWizardNeverUpgradesAModuleOutsideTheModulesDirectory(): void
    {
        $marker = 'var/wizard-traversal/upgraded';
        $this->withModule(
            'var/wizard-traversal/' . self::MODULE,
            \sprintf("<?php\nfile_put_contents(%s, '');\n", var_export(\dirname(__DIR__, 6) . '/' . $marker, true)),
            function () use ($marker): void {
                self::assertSame(
                    ['result' => 0, 'warning' => true, 'msg' => 'Module not found'],
                    $this->runStep(
                        'www/install/step_upgrade/process/process_modules.php',
                        ['action' => 'update', 'id' => '../../var/wizard-traversal/' . self::MODULE],
                    ),
                );
                self::assertFileDoesNotExist(\dirname(__DIR__, 6) . '/' . $marker);
                self::assertSame('1.0.0', $this->moduleRelease(self::MODULE));
            },
        );
    }

    public function testTheUpgradeWizardClearsTheCache(): void
    {
        self::assertSame(['result' => 0, 'msg' => 'OK'], $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'clearCache']));
    }

    public function testTheUpgradeWizardRejectsAnUnknownAction(): void
    {
        self::assertSame(1, $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'drop'])['result']);
    }

    public function testTheUpgradeWizardShowsTheVersionRecordedByTheMigrations(): void
    {
        // The session still holds the version of the last legacy script.
        $output = $this->runCode(
            "session_start(); \$_SESSION['CURRENT_VERSION'] = '0.0.1'; session_write_close();"
            . " include 'www/install/step_upgrade/process/process_migrations.php'; echo \"\\n\", \$_SESSION['CURRENT_VERSION'];",
        );

        self::assertSame(
            $this->db()->fetchOne("SELECT `value` FROM `informations` WHERE `key` = 'version'"),
            trim(mb_substr($output, (int) mb_strrpos($output, "\n"))),
        );
    }

    public function testAMigrationFailureStopsTheWizard(): void
    {
        // An executed migration missing from the code base makes the configuration set fail.
        $db = $this->db();
        $db->query("INSERT INTO `doctrine_migration_versions` (`version`) VALUES ('Centreon\\\\Migrations\\\\Configuration\\\\Version19700101000000')");

        try {
            $install = $this->runStep('www/install/steps/process/migrateDatabases.php');
            self::assertSame(1, $install['result'], 'unattended.sh and the Docker entrypoints stop on it');
            self::assertIsString($install['msg']);
            self::assertStringContainsString('Version19700101000000', $install['msg']);

            $output = $this->runCode(
                "session_start(); \$_SESSION['CURRENT_VERSION'] = '0.0.1'; session_write_close();"
                . " include 'www/install/step_upgrade/process/process_migrations.php'; echo \"\\n\", \$_SESSION['CURRENT_VERSION'];",
            );
            $upgrade = json_decode(mb_substr($output, 0, (int) mb_strrpos($output, "\n")), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($upgrade);
            self::assertSame(1, $upgrade['result']);
            self::assertSame('0.0.1', trim(mb_substr($output, (int) mb_strrpos($output, "\n"))), 'The version shown is the one of the legacy scripts');
        } finally {
            $db->query("DELETE FROM `doctrine_migration_versions` WHERE `version` LIKE '%Version19700101000000'");
        }
    }

    public function testAFailingModuleUpgradeIsAWarningAnsweredWithCleanJson(): void
    {
        // Legacy upgrade scripts may print before failing.
        $this->withModule(self::MODULE_PATH, "<?php\necho 'noise';\nthrow new Exception('the fixture upgrade failed');\n", function (): void {
            $response = $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'update', 'id' => self::MODULE]);

            self::assertSame(0, $response['result'], 'A module failure must not block the wizard');
            self::assertTrue($response['warning'] ?? false);
            self::assertIsString($response['msg']);
            self::assertStringContainsString('the fixture upgrade failed', $response['msg']);
        });
    }

    public function testTheUpgradeWizardAnnouncesTheTargetOfTheNewKernel(): void
    {
        $target = fn (string $legacy): string => trim($this->runCode(\sprintf(
            "include 'www/install/steps/functions.php'; echo getUpgradeTargetVersion(%s);",
            var_export($legacy, true),
        )));
        $console = (new Process([\PHP_BINARY, 'bin/console.new', 'centreon:upgrade:target-version', '26.09.1'], \dirname(__DIR__, 6)))->mustRun();

        self::assertSame(trim($console->getOutput()), $target('26.09.1'));
        self::assertSame('99.0.0', $target('99.0.0'), 'A legacy script recorded a later version');
    }

    /**
     * @param array<string, string> $post
     *
     * @return array<mixed>
     */
    private function runStep(string $script, array $post = []): array
    {
        $output = $this->runCode(\sprintf('$_POST = %s; include %s;', var_export($post, true), var_export($script, true)));

        try {
            $response = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::fail('Not a JSON response: ' . $output);
        }
        self::assertIsArray($response);

        return $response;
    }

    /**
     * A module installed at 1.0.0 whose code is at 1.0.1.
     *
     * @param callable(\CentreonDB): void $test
     */
    private function withModule(string $modulePath, string $upgradeScript, callable $test): void
    {
        $root = \dirname(__DIR__, 6) . '/';
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $root . $modulePath . '/conf.php',
            "<?php\n\$module_conf['" . self::MODULE . "'] = ['name' => '" . self::MODULE . "', 'rname' => 'Wizard fixture', "
            . "'mod_release' => '1.0.1', 'infos' => '', 'is_removeable' => '1', 'author' => 'Centreon', 'svc_tools' => '0', 'host_tools' => '0'];\n",
        );
        $filesystem->dumpFile($root . $modulePath . '/upgrade/1.0.1/php/upgrade.php', $upgradeScript);

        $db = $this->db();
        $db->query(
            'INSERT INTO `modules_informations` (`name`, `rname`, `mod_release`, `is_removeable`, `infos`, `author`, `svc_tools`, `host_tools`)'
            . " VALUES ('" . self::MODULE . "', 'Wizard fixture', '1.0.0', '1', '', 'Centreon', '0', '0')",
        );

        try {
            $test($db);
        } finally {
            $db->query("DELETE FROM `modules_informations` WHERE `name` = '" . self::MODULE . "'");
            $filesystem->remove([$root . self::MODULE_PATH, $root . 'var/wizard-traversal']);
        }
    }

    /**
     * @return list<mixed>
     */
    private function listModules(): array
    {
        $response = $this->runStep('www/install/step_upgrade/process/process_modules.php', ['action' => 'list']);
        self::assertSame(0, $response['result']);
        self::assertIsList($response['modules']);

        return $response['modules'];
    }

    private function moduleRelease(string $module): mixed
    {
        return $this->db()->fetchOne(
            'SELECT `mod_release` FROM `modules_informations` WHERE `name` = :name',
            QueryParameters::create([QueryParameter::string('name', $module)]),
        );
    }

    private function db(): \CentreonDB
    {
        $env = static function (string $name): string {
            $value = $_ENV[$name] ?? null;
            self::assertIsString($value);

            return $value;
        };

        return new \CentreonDB(connectionConfig: new ConnectionConfig(
            host: $env('hostCentreon'),
            user: $env('user'),
            password: $env('password'),
            databaseNameConfiguration: $env('db'),
            databaseNameRealTime: $env('dbcstg'),
            port: (int) $env('port'),
        ));
    }

    private function runCode(string $code): string
    {
        $process = new Process(
            [(new PhpExecutableFinder())->find(false) ?: 'php', '-r', $code],
            \dirname(__DIR__, 6),
            null,
            null,
            null,
        );
        $process->mustRun();

        return $process->getOutput();
    }
}
