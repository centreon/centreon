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

namespace Adaptation\Database\Connection\ValueObject;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\ValueObjectInterface;

/**
 * Class.
 *
 * @class   QueryParameter
 */
final readonly class QueryParameter implements ValueObjectInterface
{
    /**
     * QueryParameter constructor.
     *
     * Example: new QueryParameter('name', 'value', QueryParameterTypeEnum::STRING);
     *
     * @throws ValueObjectException
     */
    private function __construct(
        public string $name,
        public mixed $value,
        public ?QueryParameterTypeEnum $type = null
    ) {
        if (empty($name)) {
            throw new ValueObjectException('Name of QueryParameter cannot be empty');
        }
        if (\is_object($value)) {
            throw new ValueObjectException('Value of QueryParameter cannot be an object');
        }
        if (QueryParameterTypeEnum::LARGE_OBJECT === $type && ! \is_string($value) && ! \is_resource($value)) {
            throw new ValueObjectException(\sprintf('Value of QueryParameter with type LARGE_OBJECT must be a string or a resource, %s given', \gettype($value)));
        }
    }

    public function __toString(): string
    {
        $type = match ($this->type) {
            QueryParameterTypeEnum::STRING => 'string',
            QueryParameterTypeEnum::INTEGER => 'int',
            QueryParameterTypeEnum::BOOLEAN => 'bool',
            QueryParameterTypeEnum::NULL => 'null',
            QueryParameterTypeEnum::LARGE_OBJECT => 'largeObject',
            default => 'unknown',
        };

        if (\is_object($this->value) && method_exists($this->value, '__toString')) {
            $value = (string) $this->value;
        } elseif (\is_scalar($this->value) || null === $this->value) {
            $value = (string) $this->value;
        } else {
            $value = 'unsupported type';
        }

        return \sprintf(
            '{name:"%s",value:"%s",type:"%s"}',
            $this->name,
            $value,
            $type
        );
    }

    /**
     * @throws ValueObjectException
     */
    public static function create(string $name, mixed $value, ?QueryParameterTypeEnum $type = null): self
    {
        return new self($name, $value, $type);
    }

    /**
     * Example : QueryParameter::int('name', 1);
     * Null value is not allowed for this type.
     *
     * @throws ValueObjectException
     */
    public static function int(string $name, ?int $value): self
    {
        if (null === $value) {
            return self::null($name);
        }

        return self::create($name, $value, QueryParameterTypeEnum::INTEGER);
    }

    /**
     * Example : QueryParameter::string('name', 'value');
     * Null value is not allowed for this type.
     *
     * @throws ValueObjectException
     */
    public static function string(string $name, ?string $value): self
    {
        if (null === $value) {
            return self::null($name);
        }

        return self::create($name, $value, QueryParameterTypeEnum::STRING);
    }

    /**
     * Example : QueryParameter::bool('name', true);.
     *
     * @throws ValueObjectException
     */
    public static function bool(string $name, bool $value): self
    {
        return self::create($name, $value, QueryParameterTypeEnum::BOOLEAN);
    }

    /**
     * Example : QueryParameter::null('name');.
     *
     * @throws ValueObjectException
     */
    public static function null(string $name): self
    {
        return self::create($name, null, QueryParameterTypeEnum::NULL);
    }

    /**
     * Example : QueryParameter::largeObject('name', 'blob');.
     *
     * @param string|resource $value
     *
     * @throws ValueObjectException
     */
    public static function largeObject(string $name, mixed $value): self
    {
        return self::create($name, $value, QueryParameterTypeEnum::LARGE_OBJECT);
    }

    /**
     * @param QueryParameter $object
     *
     * @throws ValueObjectException
     */
    public function equals(ValueObjectInterface $object): bool
    {
        if (! $object instanceof self) {
            throw new ValueObjectException(\sprintf('Expected object of type %s, %s given', self::class, $object::class));
        }

        return "{$this}" === "{$object}";
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?QueryParameterTypeEnum
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
