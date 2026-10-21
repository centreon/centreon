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

use Adaptation\Database\Connection\Model\ConnectionConfig;
use CentreonLegacy\Core\Module\UpgradeProgress;
use PHPUnit\Framework\TestCase;

/**
 * The legacy module upgrader keeps its progress in the configuration database.
 */
final class ModuleUpgradeProgressTest extends TestCase
{
    private const string MODULE = 'centreon-progress-fixture';

    private \CentreonDB $db;

    private UpgradeProgress $progress;

    protected function setUp(): void
    {
        $this->db = new \CentreonDB(connectionConfig: new ConnectionConfig(
            host: $this->env('hostCentreon'),
            user: $this->env('user'),
            password: $this->env('password'),
            databaseNameConfiguration: $this->env('db'),
            databaseNameRealTime: $this->env('dbcstg'),
            port: (int) $this->env('port'),
        ));
        $this->progress = new UpgradeProgress($this->db);
        $this->progress->clear(self::MODULE);
    }

    protected function tearDown(): void
    {
        $this->progress->clear(self::MODULE);
    }

    public function testSavesReplacesAndClearsASingleRow(): void
    {
        $this->progress->save(self::MODULE, '26.11.0', ['pre' => true, 'sql' => 1, 'checksum' => 'abc']);
        $this->progress->save(self::MODULE, '26.11.0', ['pre' => true, 'sql' => 2, 'checksum' => 'abc']);
        $this->progress->save(self::MODULE, '26.11.0', ['pre' => true, 'sql' => 2, 'checksum' => 'abc']);

        self::assertSame(['pre' => true, 'sql' => 2, 'checksum' => 'abc'], $this->progress->get(self::MODULE, '26.11.0'));
        self::assertSame(1, $this->countRows(), 'Saving the same progress again must not add a row');

        $this->progress->clear(self::MODULE);

        self::assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get(self::MODULE, '26.11.0'));
        self::assertSame(0, $this->countRows());
    }

    private function countRows(): int
    {
        $count = $this->db->fetchOne("SELECT COUNT(*) FROM `options` WHERE `key` = 'module_upgrade_progress:" . self::MODULE . "'");
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }
}
