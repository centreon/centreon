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

namespace Core\HostMacro\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class HostMacro
{
    public const MAX_NAME_LENGTH = 255,
        MAX_VALUE_LENGTH = 4096,
        MAX_DESCRIPTION_LENGTH = 65535;

    private bool $isPassword = false;

    private string $description = '';

    private int $order = 0;

    /**
     * @param int $hostId
     * @param string $name
     * @param string $value
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $hostId,
        private string $name,
        private string $value,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($hostId, "{$shortName}::hostId");

        $this->name = strtoupper($name);
        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");

        Assertion::maxLength($this->value, self::MAX_VALUE_LENGTH, "{$shortName}::value");
    }

    public function getHostId(): int
    {
        return $this->hostId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
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

    public function setDescription(string $description): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $description = trim($description);
        Assertion::maxLength($description, self::MAX_DESCRIPTION_LENGTH, "{$shortName}::description");
        $this->description = $description;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::min($order, 0, "{$shortName}::order");
        $this->order = $order;
    }
}