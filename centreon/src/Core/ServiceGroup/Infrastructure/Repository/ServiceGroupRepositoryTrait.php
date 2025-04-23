<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceGroup\Infrastructure\Repository;

trait ServiceGroupRepositoryTrait
{
    /**
     * Determine if accessGroups give access to all serviceGroups
     * true: all service groups are accessible
     * false: all service groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function hasAccessToAllServiceGroups(array $accessGroupIds): bool
    {
        if ($accessGroupIds === []) {
            return false;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = <<<SQL
            SELECT res.all_servicegroups
            FROM `:db`.acl_resources res
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id
            WHERE res.acl_res_activate = '1' AND ag.acl_group_id IN ({$bindQuery})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }
}
