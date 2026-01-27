<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Centreon\Test\Mock;

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter as BaseCentreonDBAdapter;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Centreon\Infrastructure\Service\CentreonDBManagerService as BaseCentreonDBManagerService;

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
    protected CentreonDBAdapter $manager;

    public function __construct()
    {
        $this->manager = new CentreonDBAdapter(new CentreonDB());
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
        return $this->manager->getRepository($repository);
    }

    public function resetResultSet(): CentreonDBAdapter
    {
        return $this->manager->resetResultSet();
    }

    public function addResultSet($query, $result, $params = null, ?callable $callback = null): CentreonDBAdapter
    {
        return $this->manager->addResultSet($query, $result, $params, $callback);
    }

    public function setCommitCallback(?callable $callback = null): CentreonDBAdapter
    {
        return $this->manager->setCommitCallback($callback);
    }

    public function addRepositoryMock(string $className, object $repository): CentreonDBAdapter
    {
        return $this->manager->addRepositoryMock($className, $repository);
    }

    public function setLastInsertId(?int $id = null): static
    {
        $this->manager->setLastInsertId($id);

        return $this;
    }
}
