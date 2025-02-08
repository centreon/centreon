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

namespace Adaptation\Database\ValueObject;

use Adaptation\Database\Enum\ParameterType;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\ValueObjectInterface;

/**
 * Class
 *
 * @class   QueryParameter
 * @package Adaptation\Database\ValueObject
 */
class QueryParameter implements ValueObjectInterface
{

    /**
     * QueryParameter constructor
     *
     * Example: new QueryParameter('name', 'value', ParameterType::STRING);
     *
     * @param string        $name
     * @param mixed         $value
     * @param ParameterType $type
     *
     * @throws ValueObjectException
     */
    private function __construct(
        public string $name,
        public mixed $value,
        public ParameterType $type
    ) {
        if (empty($name)) {
            throw new ValueObjectException('Name of QueryParameter cannot be empty');
        }
        if (is_object($value)) {
            throw new ValueObjectException('Value of QueryParameter cannot be an object');
        }
    }

    /**
     * Example : QueryParameter::integer('name', 1);
     *
     * @param string $name
     * @param int    $value
     *
     * @throws ValueObjectException
     * @return QueryParameter
     */
    public static function integer(string $name, int $value): QueryParameter
    {
        return new static($name, $value, ParameterType::INT);
    }

    /**
     * Example : QueryParameter::string('name', 'value');
     *
     * @param string $name
     * @param string $value
     *
     * @throws ValueObjectException
     * @return QueryParameter
     */
    public static function string(string $name, string $value): QueryParameter
    {
        return new static($name, $value, ParameterType::STRING);
    }

    /**
     * Example : QueryParameter::boolean('name', true);
     *
     * @param string $name
     * @param bool   $value
     *
     * @throws ValueObjectException
     * @return QueryParameter
     */
    public static function boolean(string $name, bool $value): QueryParameter
    {
        return new static($name, $value, ParameterType::BOOL);
    }

    /**
     * Example : QueryParameter::nullable('name');
     *
     * @param string $name
     *
     * @throws ValueObjectException
     * @return QueryParameter
     */
    public static function nullable(string $name): QueryParameter
    {
        return new static($name, null, ParameterType::NULL);
    }

    /**
     * Example : QueryParameter::largeObject('name', 'blob');
     *
     * @param string $name
     * @param string|resource $value
     *
     * @throws ValueObjectException
     * @return QueryParameter
     */
    public static function largeObject(string $name, mixed $value): QueryParameter
    {
        if (! is_string($value) && ! is_resource($value)) {
            throw new ValueObjectException('Value of a Large Object QueryParameter cannot be a ' . gettype($value));
        }
        return new static($name, $value, ParameterType::LARGE_OBJECT);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return '{name:"' . $this->name . '",value:"' . $this->value . '",type:"' . $this->type->value . '"}';
    }

    /**
     * @param QueryParameter $object
     *
     * @throws ValueObjectException
     * @return bool
     */
    public function equals(ValueObjectInterface $object): bool
    {
        if (! $object instanceof QueryParameter) {
            throw new ValueObjectException(
                "Equal checking failed because not a " . $this::class . ", " . $object::class . " given",
            );
        }

        return "$this" === "$object";
    }
}
