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

final class Command extends AggregateRoot
{
    public function __construct(
        ?CommandId $id,
        public CommandName $name,
        public CommandTypeEnum $type,
        public CommandLine $commandLine,
        public bool $isShellEnabled,
        public bool $isActivated,
        public bool $isFromMonitoringConnector,
        public ?Connector $connector,
        public ?CommandComment $comment,
        public ?int $usedHostsCount = null,
        public ?int $usedHostTemplatesCount = null,
        public ?int $usedServicesCount = null,
        public ?int $usedServiceTemplatesCount = null,
    ) {
        parent::__construct($id);
    }

    public function updateName(CommandName $name): void
    {
        $this->name = $name;
    }

    public function updateType(CommandTypeEnum $type): void
    {
        $this->type = $type;
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

    public function addConnector(Connector $connector): void
    {
        $this->connector = $connector;
    }

    public function removeConnector(): void
    {
        $this->connector = null;
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
