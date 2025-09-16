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

namespace App\MonitoringConfiguration\Infrastructure\Dbal\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgument;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgumentExample;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandConnector;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandGraphTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandType;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\Shared\Infrastructure\TransformerInterface;
use App\Shared\Domain\Collection;

/**
 * @phpstan-import-type RowTypeAlias from DbalServiceCategoryRepository
 *
 * @implements TransformerInterface<RowTypeAlias, Command>
 */
final readonly class DbalCommandTransformer implements TransformerInterface
{
    public function transform(mixed $from): Command
    {
        return new Command(
            id: new CommandId($from['command_id']),
            name: $from['command_name'] !== null ? new CommandName($from['command_name']) : null,
            commandLine: $from['command_line'] !== null ? new CommandLine($from['command_line']) : null,
            type: CommandType::tryFrom($from['command_type']),
            argumentExample: $from['command_example'] !== null ? new CommandArgumentExample($from['command_example']) : null,
            connector: null, //$from['command_connector'] !== null ? new CommandConnector($from['command_connector']) : null,
            graphTemplate: null, //$from['graph_id'] !== null ? new CommandGraphTemplate($from['graph_id']) : null,
            comment: $from['command_comment'] !== null ? new CommandComment($from['command_comment']) : null,
            isShellEnabled: $from['enable_shell'] === 1,
            isActivated: $from['command_activate'] === '1',
            isLocked: $from['command_locked'] === 1,
            arguments: new Collection([], CommandArgument::class), // must be filled by callers
            macros: new Collection([], CommandMacro::class), // must be filled by callers
        );
    }
}
