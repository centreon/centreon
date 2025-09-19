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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalCommandRepositoryTest extends KernelTestCase
{
    private CommandRepository $repository;

    protected function setUp(): void
    {
        /** @var CommandRepository $repository */
        $repository = self::getContainer()->get(CommandRepository::class);

        $this->repository = $repository;
    }

    public function testGetById(): void
    {
        $commandId = new CommandId(2);

        $command = $this->repository->getById($commandId);

        self::assertEquals($commandId, $command->id()->value);
    }

    public function testGetByIdNotFound(): void
    {
        $commandId = new CommandId(9999);

        $this->expectException(CommandNotFoundException::class);
        $this->repository->getById($commandId);
    }
}
