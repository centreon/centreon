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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;

/**
 * The macros a host (or template) inherits, one per name, each tagged with its origin type.
 *
 * Resolution follows legacy: walking the inheritance line nearest-to-host first, the closest
 * template definition wins over any farther ancestor; command macros are the lowest priority and
 * only fill names no template defines (legacy comparaPriority: fromTpl > fromCommand), the first
 * command declaring a name winning.
 */
final readonly class InheritedHostMacros
{
    /**
     * @param array<string, HostMacro> $macrosByName
     */
    private function __construct(private array $macrosByName)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param list<HostTemplate> $inheritanceLine the full template line, nearest to the host first
     * @param list<Command> $commands the commands whose host macros are inherited, in priority order
     *                                (see InheritedHostMacrosResolver); only a check-type command
     *                                contributes, like legacy getMacroByIdAndType()
     */
    public static function resolve(array $inheritanceLine, array $commands): self
    {
        $macrosByName = [];
        foreach ($inheritanceLine as $template) {
            foreach ($template->macrosAsInherited() as $macro) {
                $macrosByName[$macro->name->value] ??= $macro;
            }
        }

        foreach ($commands as $command) {
            if ($command->type !== CommandTypeEnum::Check) {
                continue;
            }
            foreach ($command->macros(CommandMacroTypeEnum::Host) as $commandMacro) {
                $macro = self::fromCommandMacro($commandMacro);
                $macrosByName[$macro->name->value] ??= $macro;
            }
        }

        return new self($macrosByName);
    }

    public function findByName(HostMacroName $name): ?HostMacro
    {
        return $this->macrosByName[$name->value] ?? null;
    }

    public function findBySource(HostMacroParentEnum $parent, HostMacroId $id): ?HostMacro
    {
        foreach ($this->macrosByName as $macro) {
            if ($macro->isIdentifiedBy($parent, $id)) {
                return $macro;
            }
        }

        return null;
    }

    /**
     * Keeps only the macros that do not merely repeat what is inherited: a macro equivalent to
     * the inherited macro of the same name is dropped, so the host relies on inheritance instead of
     * storing a redundant copy.
     *
     * @param list<HostMacro> $macros
     *
     * @return list<HostMacro>
     */
    public function withoutRedundant(array $macros): array
    {
        return array_values(array_filter(
            $macros,
            function (HostMacro $macro): bool {
                $inherited = $this->findByName($macro->name);

                return ! $inherited instanceof HostMacro || ! $macro->isEquivalentTo($inherited);
            },
        ));
    }

    /**
     * The inherited macros a host still relies on: those none of its direct macros shadows by name.
     * Together with the direct macros, they are every macro the host effectively has.
     *
     * @param list<HostMacro> $direct
     *
     * @return list<HostMacro>
     */
    public function notOverriddenBy(array $direct): array
    {
        $directNames = [];
        foreach ($direct as $macro) {
            $directNames[$macro->name->value] = true;
        }

        return array_values(array_filter(
            $this->macrosByName,
            static fn (HostMacro $macro): bool => ! isset($directNames[$macro->name->value]),
        ));
    }

    /**
     * Every macro a host effectively has: its direct macros first, then the inherited ones they do
     * not shadow.
     *
     * @param list<HostMacro> $direct
     *
     * @return list<HostMacro>
     */
    public function effectiveWith(array $direct): array
    {
        return [...$direct, ...$this->notOverriddenBy($direct)];
    }

    /**
     * @return list<HostMacro>
     */
    public function toList(): array
    {
        return array_values($this->macrosByName);
    }

    /**
     * A command macro carries no value: the host supplies it (legacy getMacroByIdAndType()).
     */
    private static function fromCommandMacro(CommandMacro $macro): HostMacro
    {
        return new HostMacro(
            new HostMacroName($macro->name),
            '',
            isPassword: false,
            id: $macro->id instanceof CommandMacroId ? new HostMacroId($macro->id->value) : null,
            parent: HostMacroParentEnum::Command,
        );
    }
}
