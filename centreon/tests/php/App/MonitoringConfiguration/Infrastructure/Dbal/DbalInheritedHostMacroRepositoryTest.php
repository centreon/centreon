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

    public function testItReadsMacrosInheritedThroughAMultiLevelTemplateChain(): void
    {
        // host -> child -> parent; a macro defined only on the parent is still inherited.
        $parentId = $this->createHostTemplate('parent-template');
        $this->insertMacro($parentId, '$_HOSTPARENTMACRO$', 'from-parent', isPassword: false);
        $childId = $this->createHostTemplate('child-template');
        $this->linkTemplateToParent($childId, $parentId);

        $macros = $this->repository->findInheritedMacros(
            new Collection([new HostTemplateId($childId)], HostTemplateId::class),
            null,
        )->toArray();

        $byName = $this->indexByName($macros);
        self::assertArrayHasKey('PARENTMACRO', $byName);
        self::assertSame('from-parent', $byName['PARENTMACRO']->value);
    }

    public function testTheDefinitionClosestToTheHostWinsWhenAMacroIsOverriddenAlongTheChain(): void
    {
        // Both the child and the parent define the same macro: the child (closest to the host) wins.
        $parentId = $this->createHostTemplate('parent-template');
        $this->insertMacro($parentId, '$_HOSTSHARED$', 'from-parent', isPassword: false);
        $childId = $this->createHostTemplate('child-template');
        $this->insertMacro($childId, '$_HOSTSHARED$', 'from-child', isPassword: false);
        $this->linkTemplateToParent($childId, $parentId);

        $macros = $this->repository->findInheritedMacros(
            new Collection([new HostTemplateId($childId)], HostTemplateId::class),
            null,
        )->toArray();

        self::assertCount(1, $macros);
        self::assertSame('SHARED', $macros[0]->name->value);
        self::assertSame('from-child', $macros[0]->value);
    }

    public function testTheFirstDirectTemplateWinsWhenTwoDefineTheSameMacro(): void
    {
        // Two direct templates in `order`: the first one wins, like legacy's ordered template chain.
        $firstId = $this->createHostTemplate('first-template');
        $this->insertMacro($firstId, '$_HOSTSHARED$', 'from-first', isPassword: false);
        $secondId = $this->createHostTemplate('second-template');
        $this->insertMacro($secondId, '$_HOSTSHARED$', 'from-second', isPassword: false);

        $macros = $this->repository->findInheritedMacros(
            new Collection([new HostTemplateId($firstId), new HostTemplateId($secondId)], HostTemplateId::class),
            null,
        )->toArray();

        self::assertCount(1, $macros);
        self::assertSame('from-first', $macros[0]->value);
    }

    public function testATemplateMacroWinsOverACheckCommandMacroOfTheSameName(): void
    {
        // A name defined by both a template and the check command resolves to the template value
        // (legacy comparaPriority: fromTpl > fromCommand); command-only names still come through.
        $templateId = $this->createHostTemplate('generic-template');
        $this->insertMacro($templateId, '$_HOSTSHARED$', 'from-template', isPassword: false);
        $commandId = $this->createCommand('$USER1$/check -a $_HOSTSHARED$ -b $_HOSTCMDONLY$');

        $macros = $this->indexByName($this->repository->findInheritedMacros(
            new Collection([new HostTemplateId($templateId)], HostTemplateId::class),
            new CommandId($commandId),
        )->toArray());

        self::assertSame('from-template', $macros['SHARED']->value);
        self::assertArrayHasKey('CMDONLY', $macros);
        self::assertSame('', $macros['CMDONLY']->value);
    }

    public function testItReturnsNothingWithoutTemplatesOrCommand(): void
    {
        self::assertSame([], $this->repository->findInheritedMacros(new Collection([], HostTemplateId::class), null)->toArray());
    }

    public function testItIgnoresACommandThatIsNotACheckCommand(): void
    {
        // A non-check command (type 1 = notification) declaring $_HOST macros contributes nothing,
        // matching legacy getMacroByIdAndType()'s command_type = 2 guard.
        $commandId = $this->createCommand('$USER1$/notify -x $_HOSTFOO$', type: 1);

        $macros = $this->repository->findInheritedMacros(
            new Collection([], HostTemplateId::class),
            new CommandId($commandId),
        )->toArray();

        self::assertSame([], $macros);
    }

    private function createCommand(string $commandLine, int $type = 2): int
    {
        $this->connection->insert('command', [
            'command_name' => 'cmd_' . bin2hex(random_bytes(4)),
            'command_line' => $commandLine,
            'command_type' => $type,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name . '_' . bin2hex(random_bytes(4)),
            'host_register' => '0',
            // The parent-chain query filters on activated templates.
            'host_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkTemplateToParent(int $childTemplateId, int $parentTemplateId, int $order = 0): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $childTemplateId,
            'host_tpl_id' => $parentTemplateId,
            // `order` is a reserved word — quote the identifier for the raw insert.
            '`order`' => $order,
        ]);
    }

    /**
     * @param array<HostMacro> $macros
     *
     * @return array<string, HostMacro>
     */
    private function indexByName(array $macros): array
    {
        $byName = [];
        foreach ($macros as $macro) {
            $byName[$macro->name->value] = $macro;
        }

        return $byName;
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
