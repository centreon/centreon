<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Command
{
    /**
     * @param int $id
     * @param string $name
     * @param string $commandLine
     * @param CommandType $type
     * @param bool $isShellEnabled
     * @param bool $isActivated
     * @param bool $isLocked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $commandLine,
        private readonly CommandType $type = CommandType::Check,
        private readonly bool $isShellEnabled = false,
        private readonly bool $isActivated = true,
        private readonly bool $isLocked = false,
        // Note: this is not the full list of object properties, see DB definition for more
    ) {
        Assertion::positiveInt($id, 'Command::id');
        Assertion::notEmptyString($name, 'Command::name');
        Assertion::notEmptyString($this->commandLine, 'Command::commandLine');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCommandLine(): string
    {
        return $this->commandLine;
    }

    public function getType(): CommandType
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isShellEnabled(): bool
    {
        return $this->isShellEnabled;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }
}
