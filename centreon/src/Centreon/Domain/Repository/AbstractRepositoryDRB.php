<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Repository;

use Adaptation\Database\Connection\ConnectionInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * Class
 *
 * @class AbstractRepositoryDRB
 * @package Centreon\Domain\Repository
 *
 * @deprecated use {@see DatabaseRepository} instead
 */
class AbstractRepositoryDRB
{
    /** @var DatabaseConnection */
    protected ConnectionInterface $db;

    /**
     * Replace all instances of :dbstg and :db by the real db names.
     * The table names of the database are defined in the services.yaml
     * configuration file.
     *
     * @param string $request Request to translate
     * @return string Request translated
     */
    protected function translateDbName(string $request): string
    {
        return str_replace(
            [':dbstg', ':db'],
            [$this->db->getConnectionConfig()->getDatabaseNameRealTime(), $this->db->getConnectionConfig()->getDatabaseNameConfiguration()],
            $request
        );
    }

    /**
     * Formats the access group ids in string. (values are separated by coma)
     *
     * @param AccessGroup[] $accessGroups
     * @return string
     */
    public function accessGroupIdToString(array $accessGroups): string
    {
        $ids = [];
        foreach ($accessGroups as $accessGroup) {
            $ids[] = $accessGroup->getId();
        }
        return implode(',', $ids);
    }
}
