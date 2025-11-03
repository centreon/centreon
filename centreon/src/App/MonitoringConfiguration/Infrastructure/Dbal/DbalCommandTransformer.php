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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalCommandRepository
 *
 * @implements TransformerInterface<RowTypeAlias,Command>
 */
final readonly class DbalCommandTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): Command
    {
        return new Command(
            id: new CommandId($from['command_id']),
            name: new CommandName($from['command_name']),
            commandLine: new CommandLine($from['command_line']),
            type: CommandTypeEnum::from($from['command_type']),
            connector: null, // filled by callers
            comment: $from['command_comment'] !== null ? new CommandComment($from['command_comment']) : null,
            isShellEnabled: $from['enable_shell'] === 1,
            isActivated: $from['command_activate'] === '1',
            isFromMonitoringConnector: $from['command_locked'] === 1,
        );
    }
}
