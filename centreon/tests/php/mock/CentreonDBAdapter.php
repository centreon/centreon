<?php
/**
 * Copyright 2019 Centreon
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
 */

namespace Centreon\Test\Mock;

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter as BaseCentreonDBAdapter;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

/**
 * Mock class for dbconn
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
class CentreonDBAdapter extends BaseCentreonDBAdapter
{

    /**
     * @var array
     */
    protected $mocks = [];

    public function getRepository($repository): ServiceEntityRepository
    {
        if (array_key_exists($repository, $this->mocks)) {
            return $this->mocks[$repository];
        }

        return parent::getRepository($repository);
    }

    public function resetResultSet(): CentreonDBAdapter
    {
        $this->mocks = [];

        $this->getCentreonDBInstance()->resetResultSet();

        return $this;
    }

    public function getMocks(): array
    {
        return $this->mocks;
    }

    public function addResultSet($query, $result, $params = null, callable $callback = null): CentreonDBAdapter
    {
        $this->getCentreonDBInstance()->addResultSet($query, $result, $params, $callback);

        return $this;
    }

    public function setCommitCallback(callable $callback = null): CentreonDBAdapter
    {
        $this->getCentreonDBInstance()->setCommitCallback($callback);

        return $this;
    }

    public function setLastInsertId(int $id = null)
    {
        $this->getCentreonDBInstance()->setLastInsertId($id);

        return $this;
    }

    public function addRepositoryMock(string $className, object $repository): CentreonDBAdapter
    {
        $this->mocks[$className] = $repository;

        return $this;
    }
}
