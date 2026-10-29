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

use App\MonitoringConfiguration\Domain\Aggregate\Option\Option;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Exception\OptionDoesNotExistException;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOptionRepositoryTest extends KernelTestCase
{
    private OptionRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var OptionRepository $repository */
        $repository = self::getContainer()->get(OptionRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        $connection->executeStatement(
            "UPDATE `options` SET `value` = '/usr/lib64/nagios/plugins/' WHERE `key` = 'nagios_path_plugins'"
        );
    }

    public function testItFindByName(): void
    {
        $option = $this->repository->getByName(new OptionName(Option::PLUGIN_PATH_OPTION_NAME));
        self::assertSame(Option::PLUGIN_PATH_OPTION_NAME, $option->name->value);
        self::assertSame('/usr/lib64/nagios/plugins/', $option->value->value);
    }

    public function testItThrowExceptionWhenOptionDoesNotExist(): void
    {
        $this->expectException(OptionDoesNotExistException::class);
        $this->repository->getByName(new OptionName('foo'));
    }

    public function testFindByNameReturnsTheOptionWithItsStoredValue(): void
    {
        $this->insertOption('find_by_name_test', ' 050 ');

        $option = $this->repository->findByName(new OptionName('find_by_name_test'));

        self::assertInstanceOf(Option::class, $option);
        self::assertSame('find_by_name_test', $option->name->value);
        self::assertSame(' 050 ', $option->value->value, 'Stored as is, not trimmed.');
    }

    public function testFindByNameReturnsNullForAMissingOption(): void
    {
        self::assertNull($this->repository->findByName(new OptionName('find_by_name_missing')));
    }

    public function testFindByNameReturnsNullForANullValue(): void
    {
        $this->insertOption('find_by_name_null', null);

        self::assertNull($this->repository->findByName(new OptionName('find_by_name_null')));
    }

    public function testFindByNameReturnsNullForAnEmptyValue(): void
    {
        // An empty value cannot make an option (its value holds 1 to 255 characters): it reads as no option.
        $this->insertOption('find_by_name_empty', '');

        self::assertNull($this->repository->findByName(new OptionName('find_by_name_empty')));
    }

    private function insertOption(string $key, ?string $value): void
    {
        // `key` is a reserved word: the identifiers must be quoted, which Connection::insert() does not do.
        $this->connection->executeStatement('INSERT INTO options (`key`, `value`) VALUES (:key, :value)', ['key' => $key, 'value' => $value]);
    }
}
