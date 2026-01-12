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
 * @class CentreonTag
 */
class CentreonTag
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonTag constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->db = $pearDB;
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
                $listValues .= ':tags' . $v . ',';
                $queryValues['tags' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected service categories
        $query = 'SELECT tags_id, tags_name FROM mod_export_tags '
            . 'WHERE tags_id IN (' . $listValues . ') ORDER BY tags_name ';

        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $items[] = ['id' => $row['tags_id'], 'text' => $row['tags_name']];
        }

        return $items;
    }
}
