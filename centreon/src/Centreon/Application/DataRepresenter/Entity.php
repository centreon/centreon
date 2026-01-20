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

namespace Centreon\Application\DataRepresenter;

use JsonSerializable;
use ReflectionClass;

class Entity implements JsonSerializable
{
    /** @var mixed */
    private $entity;

    /**
     * Construct
     *
     * @param mixed $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * JSON serialization of entity
     *
     * @return mixed[]
     */
    public function jsonSerialize(): mixed
    {
        return is_object($this->entity) ? static::dismount($this->entity) : (array) $this->entity;
    }

    /**
     * @param object $object
     * @throws \ReflectionException
     * @return array<mixed>
     */
    public static function dismount(object $object): array
    {
        $reflectionClass = new ReflectionClass($object::class);
        $array = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);
            $array[$property->getName()] = $property->getValue($object);
            $property->setAccessible(false);
        }

        return $array;
    }
}
