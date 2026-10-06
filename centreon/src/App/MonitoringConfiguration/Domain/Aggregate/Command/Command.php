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

namespace App\MonitoringConfiguration\Domain\Aggregate\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Security\CommandPermissionEnum;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<CommandId>
 */
final class Command extends AggregateRoot
{
    /**
     * Host macros that are native host fields, not custom macros.
     */
    private const EXCLUDED_HOST_MACROS = ['SNMPCOMMUNITY', 'SNMPVERSION'];

    /**
     * @param Connector|(\Closure(): ?Connector)|null $connector
     * @param list<CommandMacro>|(\Closure(): list<CommandMacro>) $storedMacros
     */
    public function __construct(
        ?CommandId $id,
        public CommandName $name,
        public CommandTypeEnum $type,
        public CommandLine $commandLine,
        public bool $isShellEnabled,
        public bool $isActivated,
        public bool $isFromMonitoringConnector,
        private Connector|\Closure|null $connector,
        public ?CommandComment $comment,
        private array|\Closure $storedMacros = [],
    ) {
        parent::__construct($id);
    }

    /**
     * Macros used in the command line: host macros then service macros, each in order of appearance.
     * The id comes from the stored macros (first one wins on duplicates), null when the macro is not stored.
     *
     * @return list<CommandMacro>
     */
    public function macros(): array
    {
        if ($this->storedMacros instanceof \Closure) {
            $this->storedMacros = ($this->storedMacros)();
        }

        $storedIds = [];
        foreach ($this->storedMacros as $storedMacro) {
            $storedIds[$storedMacro->type->value][$storedMacro->name] ??= $storedMacro->id;
        }

        $macros = [];
        foreach ($this->commandLine->extractHostMacros() as $name) {
            if (in_array($name, self::EXCLUDED_HOST_MACROS, true)) {
                continue;
            }
            $macros[] = new CommandMacro(
                $storedIds[CommandMacroTypeEnum::Host->value][$name] ?? null,
                $name,
                CommandMacroTypeEnum::Host,
            );
        }
        foreach ($this->commandLine->extractServiceMacros() as $name) {
            $macros[] = new CommandMacro(
                $storedIds[CommandMacroTypeEnum::Service->value][$name] ?? null,
                $name,
                CommandMacroTypeEnum::Service,
            );
        }

        return $macros;
    }

    /**
     * @param list<CommandMacro>|(\Closure(): list<CommandMacro>) $storedMacros
     */
    public function setStoredMacros(array|\Closure $storedMacros): void
    {
        $this->storedMacros = $storedMacros;
    }

    public function updateName(CommandName $name): void
    {
        $this->name = $name;
    }

    public function updateCommandLine(CommandLine $commandLine): void
    {
        $this->commandLine = $commandLine;
    }

    public function enableShell(): void
    {
        $this->isShellEnabled = true;
    }

    public function disableShell(): void
    {
        $this->isShellEnabled = false;
    }

    public function updateComment(?CommandComment $comment): void
    {
        $this->comment = $comment;
    }

    public function enable(): void
    {
        $this->isActivated = true;
    }

    public function disable(): void
    {
        $this->isActivated = false;
    }

    public function connector(): ?Connector
    {
        if ($this->connector instanceof \Closure) {
            $this->connector = ($this->connector)();
        }

        return $this->connector;
    }

    /**
     * @param (\Closure(): ?Connector)|Connector $connector
     */
    public function addConnector(\Closure|Connector $connector): void
    {
        $this->connector = $connector;
    }

    public function removeConnector(): void
    {
        $this->connector = null;
    }

    public function isCentreonMonitoringAgent(): bool
    {
        return $this->isFromMonitoringConnector
            && (
                str_contains($this->name->value, CommandName::CENTREON_MONITORING_AGENT_MARKER)
                || str_contains($this->name->value, CommandName::CMA_MARKER)
            );
    }

    public static function getWritePermissionForType(CommandTypeEnum $type): CommandPermissionEnum
    {
        return match($type) {
            CommandTypeEnum::Notification => CommandPermissionEnum::CanReadAndWriteNotifications,
            CommandTypeEnum::Check => CommandPermissionEnum::CanReadAndWriteChecks,
            CommandTypeEnum::Miscellaneous => CommandPermissionEnum::CanReadAndWriteMiscellaneous,
            CommandTypeEnum::Discovery => CommandPermissionEnum::CanReadAndWriteDiscovery,
        };
    }

    public static function getReadPermissionForType(CommandTypeEnum $type): CommandPermissionEnum
    {
        return match($type) {
            CommandTypeEnum::Notification => CommandPermissionEnum::CanReadNotifications,
            CommandTypeEnum::Check => CommandPermissionEnum::CanReadChecks,
            CommandTypeEnum::Miscellaneous => CommandPermissionEnum::CanReadMiscellaneous,
            CommandTypeEnum::Discovery => CommandPermissionEnum::CanReadDiscovery,
        };
    }
}
