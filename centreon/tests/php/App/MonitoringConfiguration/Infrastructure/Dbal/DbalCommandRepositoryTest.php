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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalCommandRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalCommandRepositoryTest extends KernelTestCase
{
    private DbalCommandRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalCommandRepository $repository */
        $repository = self::getContainer()->get(DbalCommandRepository::class);

        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('command', [
            'command_id' => 2,
            'command_name' => 'check_disk_smb',
            'command_line' => '$USER1$/check_disk_smb -H $HOSTADDRESS$',
            'command_type' => 2,
            'enable_shell' => '0',
            'command_activate' => '1',
            'command_locked' => '0',
        ]);
    }

    public function testGetById(): void
    {
        $commandId = new CommandId(2);

        $command = $this->repository->getById($commandId);

        self::assertEquals($commandId, $command->id());
    }

    public function testGetByIdNotFound(): void
    {
        $commandId = new CommandId(9999);

        $this->expectException(CommandNotFoundException::class);

        $this->repository->getById($commandId);
    }

    public function testAdd(): void
    {
        self::assertNull($this->repository->findOneByName(new CommandName('NAME')));

        $command = new Command(
            id: null,
            name: new CommandName('NAME'),
            type: CommandTypeEnum::from(1),
            commandLine: new CommandLine('$CENTREONPLUGINS$ /check_dhcp $ADMINEMAIL$'),
            isShellEnabled: true,
            isFromMonitoringConnector: false,
            isActivated: true,
            connector: null,
            comment: null
        );

        $this->repository->add($command);
        self::assertEquals($command->name->value, ($this->repository->findOneByName(new CommandName('NAME'))?->name->value));
    }

    public function testUpdate(): void
    {
        $command = $this->repository->getById(new CommandId(2));
        $command->updateName(new CommandName('UPDATED_NAME'));

        $this->repository->update($command);
        self::assertEquals('UPDATED_NAME', ($this->repository->getById(new CommandId(2))->name->value));
    }

    public function testDelete(): void
    {
        $command = new Command(
            id: null,
            name: new CommandName('NAME_TO_DELETE'),
            type: CommandTypeEnum::from(1),
            commandLine: new CommandLine('$CENTREONPLUGINS$ /check_dhcp $ADMINEMAIL$'),
            isShellEnabled: true,
            isFromMonitoringConnector: false,
            isActivated: true,
            connector: null,
            comment: null
        );

        $this->repository->add($command);
        $this->repository->delete($command);

        $this->expectException(CommandNotFoundException::class);
        $this->repository->getById(new CommandId($command->id()->value));
    }

    public function testFindAll(): void
    {
        $commands = $this->repository->findAll();

        self::assertInstanceOf(Collection::class, $commands);
        self::assertNotEmpty($commands);
        self::assertContainsOnlyInstancesOf(Command::class, $commands);
    }

    public function testFindAllWithNameLikeCriteria(): void
    {
        $criteria = (new CommandCriteria())->withName('disk_smb', CommandCriteria::OPERATOR_LIKE);

        $commands = $this->repository->findAll($criteria);

        self::assertCount(1, $commands);
        self::assertSame('check_disk_smb', iterator_to_array($commands)[0]->name->value);
    }

    public function testFindAllWithNameEqualCriteria(): void
    {
        $criteria = (new CommandCriteria())->withName('check_disk_smb', CommandCriteria::OPERATOR_EQUAL);

        $commands = $this->repository->findAll($criteria);

        self::assertCount(1, $commands);
        self::assertSame('check_disk_smb', iterator_to_array($commands)[0]->name->value);
    }

    /**
     * Make sure a double quote in the filter value is bound as a parameter
     * instead of being concatenated into the SQL string.
     */
    public function testFilterByCriteriaIsSafeAgainstQuoteInjection(): void
    {
        $payload = 'x" UNION SELECT * FROM command --';

        $likeCriteria = (new CommandCriteria())->withName($payload, CommandCriteria::OPERATOR_LIKE);
        $equalCriteria = (new CommandCriteria())->withName($payload, CommandCriteria::OPERATOR_EQUAL);

        self::assertCount(0, $this->repository->findAll($likeCriteria));
        self::assertCount(0, $this->repository->findAll($equalCriteria));
    }
}
