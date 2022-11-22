<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;

class DbWriteHostCategoryRepository extends AbstractRepositoryDRB implements WriteHostCategoryRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostCategoryId): void
    {
        $request = $this->translateDbName(
            'DELETE hc FROM `:db`.hostcategories hc
            WHERE hc.hc_id = :hostCategoryId'
        );
        $request .= " AND hc.level IS NULL ";

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteByIdAndAccessGroups(int $hostCategoryId, array $accessGroups): void
    {
        if (empty($accessGroups)) {
            return ;
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $request = $this->translateDbName(
            'DELETE hc FROM `:db`.hostcategories hc
            INNER JOIN `:db`.acl_resources_hc_relations arhr
                ON hc.hc_id = arhr.hc_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id
            WHERE hc.hc_id = :hostCategoryId'
        );

        $request .= " AND hc.level IS NULL ";
        $request .= ' ag.acl_group_id IN (' . implode(', ', $accessGroupIds) . ')';

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_INT);

        $statement->execute();
    }
}
