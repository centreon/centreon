<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Application\UseCase\AddCommand;

use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Domain\Model\CommandMacroType;

final class AddCommandResponse
{
    public int $id = 0;

    public string $name = '';

    public CommandType $type = CommandType::Check;

    public string $commandLine = '';

    public bool $isShellEnabled = false;

    public bool $isActivated = true;

    public bool $isLocked = false;

    public string $argumentExample = '';

    /** @var array<array{name:string,description:string}> */
    public array $arguments = [];

    /** @var array<array{name:string,type:CommandMacroType,description:string}> */
    public array $macros = [];

    /** @var null|array{id:int,name:string} */
    public null|array $connector = null;

    /** @var null|array{id:int,name:string} */
    public null|array $graphTemplate = null;
}
