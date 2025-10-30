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
        public readonly CommandName $name,
        public readonly CommandTypeEnum $type,
        public readonly CommandLine $commandLine,
        public readonly bool $isShellEnabled,
        public readonly bool $isActivated,
        public readonly bool $isFromMonitoringConnector,
        public ?Connector $connector,
        public readonly ?CommandComment $comment,
    ) {
        parent::__construct($id);
    }

    public function addConnector(Connector $connector): void
    {
        $this->connector = $connector;
    }

    public static function getCreationPermissionForType(CommandTypeEnum $type): CommandPermissionEnum
    {
        return match($type) {
            CommandTypeEnum::Notification => CommandPermissionEnum::CanReadAndWriteNotifications,
            CommandTypeEnum::Check => CommandPermissionEnum::CanReadAndWriteChecks,
            CommandTypeEnum::Miscellaneous => CommandPermissionEnum::CanReadAndWriteMiscellaneous,
            CommandTypeEnum::Discovery => CommandPermissionEnum::CanReadAndWriteDiscovery,
        };
    }
}
