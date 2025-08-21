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

use Centreon\Infrastructure\Service\CentreonDBManagerService as BaseCentreonDBManagerService;
use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter as BaseCentreonDBAdapter;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Mock\CentreonDBAdapter;

/**
 * Mock class for dbconn
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
class CentreonDBManagerService extends BaseCentreonDBManagerService
{

    /**
     * @var \Centreon\Test\Mock\CentreonDBAdapter
     */
    protected $manager;

    public function __construct()
    {
        $this->manager = new CentreonDBAdapter(new CentreonDB);
    }

    public function getAdapter(string $alias): BaseCentreonDBAdapter
    {
        return $this->manager;
    }

    public function getDefaultAdapter(): BaseCentreonDBAdapter
    {
        return $this->manager;
    }

    public function getRepository($repository): ServiceEntityRepository
    {
        $manager = $this->manager->getRepository($repository);

        return $manager;
    }

    public function resetResultSet()
    {
        return $this->manager->resetResultSet();
    }

    public function addResultSet($query, $result, $params = null, callable $callback = null)
    {
        return $this->manager->addResultSet($query, $result, $params, $callback);
    }

    public function setCommitCallback(callable $callback = null)
    {
        return $this->manager->setCommitCallback($callback);
    }

    public function addRepositoryMock(string $className, object $repository)
    {
        return $this->manager->addRepositoryMock($className, $repository);
    }

    public function setLastInsertId(int $id = null)
    {
        $this->manager->setLastInsertId($id);

        return $this;
    }
}
