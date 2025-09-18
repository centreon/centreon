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

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class Command extends AggregateRoot
{
    /**
     * @param Collection<CommandArgument> $arguments
     * @param Collection<CommandMacro> $macros
     */
    public function __construct(
        ?CommandId $id,
        public readonly CommandName $name,
        public readonly bool $isShellEnabled,
        public readonly bool $isActivated,
        public readonly bool $isLocked,
        public readonly CommandType $type,
        public readonly Collection $arguments,
        public readonly Collection $macros,
        public readonly ?CommandLine $commandLine,
        public readonly ?CommandArgumentExample $argumentExample,
        public readonly ?CommandConnector $connector,
        public readonly ?CommandGraphTemplate $graphTemplate,
        public readonly ?CommandComment $comment,
    ) {
        parent::__construct($id);
    }

    public function addCommandMacro(CommandMacro $macro): self
    {
        if ($this->macros->contains($macro)) {
            return $this;
        }
        $this->macros->add($macro);

        return $this;
    }

    public function addCommandArgument(CommandArgument $argument): self
    {
        if ($this->arguments->contains($argument)) {
            return $this;
        }
        $this->arguments->add($argument);

        return $this;
    }
}
