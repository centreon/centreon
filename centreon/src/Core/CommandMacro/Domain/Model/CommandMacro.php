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

namespace Core\CommandMacro\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class CommandMacro
{
    public const MAX_NAME_LENGTH = 255,
        MAX_DESCRIPTION_LENGTH = 65535;

    private string $description = '';

    /**
     * @param int $commandId
     * @param CommandMacroType $type
     * @param string $name
     *
     * Note: See DB for complete property list
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $commandId,
        private readonly CommandMacroType $type,
        private string $name,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($commandId, "{$shortName}::commandId");

        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");
    }

    public function getCommandId(): int
    {
        return $this->commandId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): CommandMacroType
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $description = trim($description);
        Assertion::maxLength($description, self::MAX_DESCRIPTION_LENGTH, "{$shortName}::description");
        $this->description = $description;
    }
}