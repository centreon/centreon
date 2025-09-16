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
    public function __construct(
        ?CommandId $id,
        public readonly ?CommandName $name,
        public readonly ?CommandType $type,
        public readonly ?CommandLine $commandLine,
        public readonly ?CommandArgumentExample $argumentExample,
        public readonly ?CommandConnector $connector,
        public readonly ?CommandGraphTemplate $graphTemplate,
        public readonly ?CommandComment $comment,
        public readonly bool $isShellEnabled,
        public readonly bool $isActivated,
        public readonly bool $isLocked,
        public readonly Collection $arguments,
        public readonly Collection $macros,
    ) {
        parent::__construct($id);
    }

    public function addCommandMacro(CommandMacro $macro): self
    {
        $this->macros->add($macro);

        return $this;
    }

    // dans la repo : instantier une commande avec la collection macro vide
    // creer tous les macros et les ajouter a la commande
}
