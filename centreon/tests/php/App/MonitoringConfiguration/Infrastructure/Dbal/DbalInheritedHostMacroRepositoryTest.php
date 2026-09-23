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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalInheritedHostMacroRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalInheritedHostMacroRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalInheritedHostMacroRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // Constructed directly: nothing consumes the service yet, so the container would prune it.
        $this->repository = new DbalInheritedHostMacroRepository($this->connection);
    }

    public function testItExtractsCustomMacrosDeclaredByTheCheckCommandExcludingReservedSnmpOnes(): void
    {
        $commandId = $this->createCommand('$USER1$/check -c $_HOSTFOO$ -s $_HOSTSNMPCOMMUNITY$ -b $_HOSTBAR$');

        $macros = $this->repository->findInheritedMacros(
            new Collection([], HostTemplateId::class),
            new CommandId($commandId),
        )->toArray();

        $names = array_map(static fn (HostMacro $macro): string => $macro->name->value, $macros);
        self::assertSame(['FOO', 'BAR'], $names);
        self::assertSame('', $macros[0]->value);
        self::assertFalse($macros[0]->isPassword);
    }

    public function testItReadsMacrosInheritedFromTheTemplateChain(): void
    {
        $templateId = $this->createHostTemplate('generic-template');
        $this->insertMacro($templateId, '$_HOSTTPLMACRO$', 'inherited-value', isPassword: true);

        $macros = $this->repository->findInheritedMacros(
            new Collection([new HostTemplateId($templateId)], HostTemplateId::class),
            null,
        )->toArray();

        self::assertCount(1, $macros);
        self::assertSame('TPLMACRO', $macros[0]->name->value);
        self::assertSame('inherited-value', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
    }

    public function testItReturnsNothingWithoutTemplatesOrCommand(): void
    {
        self::assertSame([], $this->repository->findInheritedMacros(new Collection([], HostTemplateId::class), null)->toArray());
    }

    private function createCommand(string $commandLine): int
    {
        $this->connection->insert('command', [
            'command_name' => 'check_' . bin2hex(random_bytes(4)),
            'command_line' => $commandLine,
            'command_type' => 2,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name . '_' . bin2hex(random_bytes(4)),
            'host_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword): void
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'host_host_id' => $hostId,
            'macro_order' => 0,
        ]);
    }
}
