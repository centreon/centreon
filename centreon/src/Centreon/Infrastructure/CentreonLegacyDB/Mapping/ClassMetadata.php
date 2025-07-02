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

namespace Centreon\Infrastructure\CentreonLegacyDB\Mapping;

use PDO;

class ClassMetadata
{
    public const COLUMN = 'column';
    public const TYPE = 'type';
    public const FORMATTER = 'formatter';

    /**
     * Table name of entity
     *
     * @var string
     */
    protected $tableName;

    /**
     * List of properties of entity vs columns in DB table
     *
     * @var array
     */
    protected $columns;

    /**
     * Name of property that is the primary key
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * Set table name
     *
     * @param string $name
     * @return self
     */
    public function setTableName($name): self
    {
        $this->tableName = $name;

        return $this;
    }

    /**
     * Get table name of entity
     *
     * @return string
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * Add information about the property
     *
     * @param string $property name of the property in the Entity class
     * @param string $columnName name of the column in DB
     * @param int $dataType type of data use PDO::PARAM_*
     * @param callable $dataFormatter
     * @param bool $primaryKey is it PK
     * @return self
     */
    public function add(
        string $property,
        string $columnName,
        int $dataType = PDO::PARAM_STR,
        ?callable $dataFormatter = null,
        $primaryKey = false
    ): self {
        $this->columns[$property] = [
            static::COLUMN => $columnName,
            static::TYPE => $dataType,
            static::FORMATTER => $dataFormatter,
        ];

        // mark property as primary kay
        if ($primaryKey === true) {
            $this->primaryKey = $property;
        }

        return $this;
    }

    /**
     * Has PK
     *
     * @return bool
     */
    public function hasPrimaryKey(): bool
    {
        return $this->primaryKey !== null;
    }

    /**
     * Get PK property
     *
     * @return string
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * Get PK column
     *
     * @return string
     */
    public function getPrimaryKeyColumn(): ?string
    {
        return $this->hasPrimaryKey() ? $this->getColumn($this->getPrimaryKey()) : null;
    }

    /**
     * Check is property exists in metadata
     *
     * @param string $property
     * @return bool
     */
    public function has(string $property): bool
    {
        return array_key_exists($property, $this->columns);
    }

    /**
     * Get metadata of the property
     *
     * @param string $property
     * @return array|null
     */
    public function get(string $property): ?array
    {
        return $this->has($property) ? $this->columns[$property] : null;
    }

    /**
     * Get column name of the property
     *
     * @param string $property
     * @return string|null
     */
    public function getColumn(string $property): ?string
    {
        return $this->has($property) ? $this->columns[$property][static::COLUMN] : null;
    }

    /**
     * Get data for columns
     *
     * @return array|null
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * Get data type of the property
     *
     * @param string $property
     * @return int
     */
    public function getType(string $property): int
    {
        return $this->has($property) ? $this->columns[$property][static::TYPE] : PDO::PARAM_INT;
    }

    /**
     * Get data formatter function
     *
     * @param string $property
     * @return callable|null
     */
    public function getFormatter(string $property): ?callable
    {
        return $this->has($property) ? $this->columns[$property][static::FORMATTER] : null;
    }

    /**
     * Get the property by the column name
     *
     * @param string $column
     * @return string|null
     */
    public function getProperty(string $column): ?string
    {
        foreach ($this->columns as $property => $data) {
            if (strtolower($data[self::COLUMN]) === strtolower($column)) {
                return $property;
            }
        }

        return null;
    }
}
