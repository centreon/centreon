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

/**
 * Class
 *
 * @class CentreonAclGroup
 */
class CentreonAclGroup
{
    /** @var CentreonDB */
    protected $db;

    /**
     *  CentreonAclGroup constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        $items = [];

        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':group' . $v . ',';
                $queryValues['group' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected timeperiods
        $query = 'SELECT acl_group_id, acl_group_name FROM acl_groups '
            . 'WHERE acl_group_id IN (' . $listValues . ') '
            . 'ORDER BY acl_group_name ';
        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $items[] = ['id' => $row['acl_group_id'], 'text' => $row['acl_group_name']];
        }

        return $items;
    }
}
