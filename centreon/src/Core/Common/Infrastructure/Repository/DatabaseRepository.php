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

declare(strict_types=1);

namespace Core\Common\Infrastructure\Repository;

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;

/**
 * Class
 *
 * @class DatabaseRepository
 * @package Core\Common\Infrastructure\Repository
 */
abstract class DatabaseRepository
{
    /**
     * DatabaseRepository constructor
     *
     * @param ConnectionInterface $connection
     * @param QueryBuilderInterface $queryBuilder
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected QueryBuilderInterface $queryBuilder
    ) {
    }

    /**
     * Replace all instances of :dbstg and :db by the real db names.
     *
     * @param string $query
     *
     * @return string
     */
    protected function translateDbName(string $query): string
    {
        return str_replace(
            [':dbstg', ':db'],
            [
                $this->getDbNameRealTime(),
                $this->getDbNameConfiguration(),
            ],
            $query
        );
    }

    /**
     * @return string
     */
    protected function getDbNameConfiguration(): string
    {
        return $this->connection->getConnectionConfig()->getDatabaseNameConfiguration();
    }

    /**
     * @return string
     */
    protected function getDbNameRealTime(): string
    {
        return $this->connection->getConnectionConfig()->getDatabaseNameRealTime();
    }
}
