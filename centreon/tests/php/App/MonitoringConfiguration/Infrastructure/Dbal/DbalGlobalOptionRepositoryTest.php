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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Infrastructure\Dbal\DbalGlobalOptionRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalGlobalOptionRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalGlobalOptionRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no consumer yet (that lands with the command handler), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalGlobalOptionRepository($this->connection);

        // The `inheritance_mode` row ships with every install, so each test mutates a shared row:
        // wrap it in a transaction rather than trying to restore the previous value by hand.
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testAdditiveInheritanceIsEnabledForTheValueOne(): void
    {
        $this->setInheritanceMode('1');

        self::assertTrue($this->repository->isAdditiveInheritanceEnabled());
    }

    /**
     * A fresh install ships '3' — so additive inheritance is off out of the box, and a client
     * asking for it gets its request silently dropped, exactly as legacy does.
     */
    public function testAdditiveInheritanceIsDisabledForTheShippedDefault(): void
    {
        $this->setInheritanceMode('3');

        self::assertFalse($this->repository->isAdditiveInheritanceEnabled());
    }

    public function testAdditiveInheritanceIsDisabledWhenTheOptionIsMissing(): void
    {
        $this->deleteInheritanceMode();

        self::assertFalse($this->repository->isAdditiveInheritanceEnabled());
    }

    private function setInheritanceMode(string $value): void
    {
        $this->deleteInheritanceMode();
        // `key` and `value` are MySQL reserved words: Connection::insert() would not quote them.
        $this->connection->executeStatement(
            'INSERT INTO options (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'inheritance_mode', 'value' => $value],
        );
    }

    private function deleteInheritanceMode(): void
    {
        $this->connection->executeStatement('DELETE FROM options WHERE `key` = :key', ['key' => 'inheritance_mode']);
    }
}
