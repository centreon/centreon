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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;

/**
 * The macros a service inherits, one per name, each tagged with its origin type.
 *
 * Resolution follows legacy: walking the template chain nearest-to-service first, the closest template
 * definition wins over any farther ancestor; the service macros of the check command are the lowest priority and
 * only fill names no template defines, the first command declaring a name winning.
 */
final readonly class InheritedServiceMacros
{
    /**
     * The check command's macros a service never inherits: the SNMP community and version, which legacy never
     * offers as macros to inherit from a command, of either type (they are host settings). A template macro of
     * the same name is a custom macro and is inherited.
     */
    private const NOT_INHERITED_COMMAND_MACROS = ['SNMPCOMMUNITY', 'SNMPVERSION'];

    /**
     * @param array<string, ServiceMacro> $macrosByName
     */
    private function __construct(private array $macrosByName)
    {
    }

    /**
     * @param list<ServiceTemplate> $inheritanceLine the template chain, nearest to the service first
     * @param list<Command> $commands the commands whose service macros are inherited, in priority order; only a
     *                                check-type command contributes
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
            foreach ($command->macros(CommandMacroTypeEnum::Service) as $commandMacro) {
                if (in_array($commandMacro->name, self::NOT_INHERITED_COMMAND_MACROS, true)) {
                    continue;
                }
                $macro = self::fromCommandMacro($commandMacro);
                $macrosByName[$macro->name->value] ??= $macro;
            }
        }

        return new self($macrosByName);
    }

    /**
     * Every macro a service effectively has: its own macros first, then the inherited ones they do not shadow
     * by name.
     *
     * @param list<ServiceMacro> $own
     *
     * @return list<ServiceMacro>
     */
    public function effectiveWith(array $own): array
    {
        $ownNames = [];
        foreach ($own as $macro) {
            $ownNames[$macro->name->value] = true;
        }

        return [
            ...$own,
            ...array_values(array_filter(
                $this->macrosByName,
                static fn (ServiceMacro $macro): bool => ! isset($ownNames[$macro->name->value]),
            )),
        ];
    }

    /**
     * A command macro carries no value: the service supplies it.
     */
    private static function fromCommandMacro(CommandMacro $macro): ServiceMacro
    {
        return new ServiceMacro(
            new ServiceMacroName($macro->name),
            '',
            isPassword: false,
            id: $macro->id instanceof CommandMacroId ? new ServiceMacroId($macro->id->value) : null,
            parent: ServiceMacroParentEnum::Command,
        );
    }
}
