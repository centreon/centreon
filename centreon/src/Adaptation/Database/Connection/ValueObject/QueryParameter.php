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
final class QueryParameter implements ValueObjectInterface
{
    /**
     * QueryParameter constructor.
     *
     * Example: new QueryParameter('name', 'value', QueryParameterTypeEnum::STRING);
     *
     * @param string $name
     * @param mixed $value
     * @param QueryParameterTypeEnum|null $type
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
        if (is_object($value)) {
            throw new ValueObjectException('Value of QueryParameter cannot be an object');
        }
        if ($type === QueryParameterTypeEnum::LARGE_OBJECT && ! is_string($value) && ! is_resource($value)) {
            throw new ValueObjectException(
                sprintf(
                    'Value of QueryParameter with type LARGE_OBJECT must be a string or a resource, %s given',
                    gettype($value)
                )
            );
        }
    }

    /**
     * @return string
     */
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

        return sprintf(
            '{name:"%s",value:"%s",type:"%s"}',
            $this->name,
            $this->value,
            $type
        );
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param QueryParameterTypeEnum|null $type
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function create(string $name, mixed $value, ?QueryParameterTypeEnum $type = null): self
    {
        return new self($name, $value, $type);
    }

    /**
     * Example : QueryParameter::int('name', 1);
     * Null value is not allowed for this type.
     *
     * @param string $name
     * @param int|null $value
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function int(string $name, ?int $value): self
    {
        if ($value === null) {
            return self::null($name);
        }

        return self::create($name, $value, QueryParameterTypeEnum::INTEGER);
    }

    /**
     * Example : QueryParameter::string('name', 'value');
     * Null value is not allowed for this type.
     *
     * @param string $name
     * @param string|null $value
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function string(string $name, ?string $value): self
    {
        if ($value === null) {
            return self::null($name);
        }

        return self::create($name, $value, QueryParameterTypeEnum::STRING);
    }

    /**
     * Example : QueryParameter::bool('name', true);.
     *
     * @param string $name
     * @param bool $value
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function bool(string $name, bool $value): self
    {
        return self::create($name, $value, QueryParameterTypeEnum::BOOLEAN);
    }

    /**
     * Example : QueryParameter::null('name');.
     *
     * @param string $name
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function null(string $name): self
    {
        return self::create($name, null, QueryParameterTypeEnum::NULL);
    }

    /**
     * Example : QueryParameter::largeObject('name', 'blob');.
     *
     * @param string $name
     * @param string|resource $value
     *
     * @throws ValueObjectException
     *
     * @return QueryParameter
     */
    public static function largeObject(string $name, mixed $value): self
    {
        return self::create($name, $value, QueryParameterTypeEnum::LARGE_OBJECT);
    }

    /**
     * @param QueryParameter $object
     *
     * @throws ValueObjectException
     *
     * @return bool
     */
    public function equals(ValueObjectInterface $object): bool
    {
        if (! $object instanceof self) {
            throw new ValueObjectException(
                sprintf(
                    'Expected object of type %s, %s given',
                    $this::class,
                    $object::class
                )
            );
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return QueryParameterTypeEnum|null
     */
    public function getType(): ?QueryParameterTypeEnum
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
