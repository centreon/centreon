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

namespace Centreon\Infrastructure\CentreonLegacyDB;

use Centreon\Infrastructure\CentreonLegacyDB\Mapping\ClassMetadata;

class EntityPersister
{
    /** @var string */
    protected $entityClassName;

    /** @var ClassMetadata */
    protected $classMetadata;

    /**
     * Construct
     *
     * @param class-string $entityClassName
     * @param ClassMetadata $classMetadata
     */
    public function __construct($entityClassName, ClassMetadata $classMetadata)
    {
        $this->entityClassName = $entityClassName;
        $this->classMetadata = $classMetadata;
    }

    /**
     * Get table name of entity
     *
     * @return object of Entity
     */
    public function load(array $data): object
    {
        $entity = new $this->entityClassName();

        // load entity with data
        foreach ($data as $column => $value) {
            $property = $this->classMetadata->getProperty($column);

            if ($property === null) {
                continue;
            }

            $action = 'set' . ucfirst($property);

            if (! is_callable([$entity, $action])) {
                continue;
            }

            $formatter = $this->classMetadata->getFormatter($property);

            if ($formatter === null) {
                $entity->{$action}($value);
            } else {
                $entity->{$action}($formatter($value));
            }
        }

        return $entity;
    }
}
