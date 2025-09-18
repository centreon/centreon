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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroDescription;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroType;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalCommandMacroRepository
 *
 * @implements TransformerInterface<RowTypeAlias, CommandMacro>
 */
final readonly class DbalCommandMacroTransformer implements TransformerInterface
{
    public function transform(mixed $from): mixed
    {
        return new CommandMacro(
            id: new CommandMacroId($from['command_macro_id']),
            name: new CommandMacroName($from['command_macro_name']),
            type: CommandMacroType::from($from['command_macro_type']),
            description: new CommandMacroDescription($from['command_macro_desciption']),
        );
    }
}
