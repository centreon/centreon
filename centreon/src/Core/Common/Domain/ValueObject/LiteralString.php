<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Common\Domain\ValueObject;

use Core\Common\Domain\Exception\ValueObjectException;

/**
 * Class
 *
 * @class   LiteralString
 * @package Core\Common\Domain\ValueObject
 */
readonly class LiteralString implements ValueObjectInterface
{
    /**
     * LiteralString constructor
     *
     * @param string $value
     */
    public function __construct(protected string $value) {}

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    /**
     * @return int
     */
    public function length(): int
    {
        return mb_strlen($this->value);
    }

    /**
     * @return LiteralString
     */
    public function toUpperCase(): self
    {
        return new static(mb_strtoupper($this->value));
    }

    /**
     * @return LiteralString
     */
    public function toLowerCase(): self
    {
        return new static(mb_strtolower($this->value));
    }

    /**
     * @param string $needle
     *
     * @return bool
     */
    public function contains(string $needle): bool
    {
        return str_contains($this->value, $needle);
    }

    /**
     * @param string $needle
     *
     * @return bool
     */
    public function startsWith(string $needle): bool
    {
        return str_starts_with($this->value, $needle);
    }

    /**
     * @param string $needle
     *
     * @return bool
     */
    public function endsWith(string $needle): bool
    {
        return str_ends_with($this->value, $needle);
    }

    /**
     * @param string $search
     * @param string $replace
     *
     * @return LiteralString
     */
    public function replace(string $search, string $replace): self
    {
        return new static(str_replace($search, $replace, $this->value));
    }

    /**
     * @return LiteralString
     */
    public function trim(): self
    {
        return new static(trim($this->value));
    }

    /**
     * @param string $value
     *
     * @return LiteralString
     */
    public function append(string $value): self
    {
        return new static($this->value . $value);
    }

    /**
     * @param ValueObjectInterface $object
     *
     * @throws ValueObjectException
     * @return bool
     */
    public function equals(ValueObjectInterface $object): bool
    {
        if (! $object instanceof static) {
            throw new ValueObjectException(
                'Equal checking failed because not a ' . $this::class . ', ' . $object::class . ' given',
            );
        }

        return $this->value === $object->getValue();
    }

    /**
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
