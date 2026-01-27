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
    /** @var array */
    protected array $mocks = [];

    public function getRepository($repository): ServiceEntityRepository
    {
        if (array_key_exists($repository, $this->mocks)) {
            return $this->mocks[$repository];
        }

        return parent::getRepository($repository);
    }

    public function resetResultSet(): static
    {
        $this->mocks = [];

        /** @var CentreonDB $db */
        $db = $this->getCentreonDBInstance();
        $db->resetResultSet();

        return $this;
    }

    public function getMocks(): array
    {
        return $this->mocks;
    }

    public function addResultSet($query, $result, $params = null, ?callable $callback = null): static
    {
        /** @var CentreonDB $db */
        $db = $this->getCentreonDBInstance();
        $db->addResultSet($query, $result, $params, $callback);

        return $this;
    }

    public function setCommitCallback(?callable $callback = null): static
    {
        /** @var CentreonDB $db */
        $db = $this->getCentreonDBInstance();
        $db->setCommitCallback($callback);

        return $this;
    }

    public function setLastInsertId(?int $id = null): static
    {
        /** @var CentreonDB $db */
        $db = $this->getCentreonDBInstance();
        $db->setLastInsertId($id);

        return $this;
    }

    public function addRepositoryMock(string $className, object $repository): static
    {
        $this->mocks[$className] = $repository;

        return $this;
    }
}
