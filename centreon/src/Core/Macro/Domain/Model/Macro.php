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

namespace Core\Macro\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Macro
{
    public const MAX_NAME_LENGTH = 255;
    public const MAX_VALUE_LENGTH = 4096;
    public const MAX_DESCRIPTION_LENGTH = 65535;

    private string $shortName;

    private bool $isPassword = false;

    private string $description = '';

    private int $order = 0;

    /**
     * @param int $ownerId
     * @param string $name
     * @param string $value
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $ownerId,
        private string $name,
        private string $value,
    ) {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($ownerId, "{$this->shortName}::ownerId");
        Assertion::notEmptyString($this->name, "{$this->shortName}::name");
        $this->name = mb_strtoupper($name);
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$this->shortName}::name");
        Assertion::maxLength($this->value, self::MAX_VALUE_LENGTH, "{$this->shortName}::value");
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function isPassword(): bool
    {
        return $this->isPassword;
    }

    public function setIsPassword(bool $isPassword): void
    {
        $this->isPassword = $isPassword;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @throws AssertionFailedException
     */
    public function setDescription(string $description): void
    {
        $description = trim($description);
        Assertion::maxLength($description, self::MAX_DESCRIPTION_LENGTH, "{$this->shortName}::description");
        $this->description = $description;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @param int $order
     *
     * @throws AssertionFailedException
     */
    public function setOrder(int $order): void
    {
        Assertion::min($order, 0, "{$this->shortName}::order");
        $this->order = $order;
    }

    /**
     * Return two arrays:
     *  - the first is an array of the direct macros
     *  - the second is an array of the inherited macros
     * Both use the macro's name as key.
     *
     * @param Macro[] $macros
     * @param int[] $inheritanceLine
     * @param int $childId
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,Macro>
     * }
     */
    public static function resolveInheritance(array $macros, array $inheritanceLine, int $childId): array
    {
        /** @var array<string,Macro> $directMacros */
        $directMacros = [];
        foreach ($macros as $macro) {
            if ($macro->getOwnerId() === $childId) {
                $directMacros[$macro->getName()] = $macro;
            }
        }

        /** @var array<string,Macro> $inheritedMacros */
        $inheritedMacros = [];
        foreach ($inheritanceLine as $parentId) {
            foreach ($macros as $macro) {
                if (
                    ! isset($inheritedMacros[$macro->getName()])
                    && $macro->getOwnerId() === $parentId
                ) {
                    $inheritedMacros[$macro->getName()] = $macro;
                }
            }
        }

        return [$directMacros, $inheritedMacros];
    }
}
