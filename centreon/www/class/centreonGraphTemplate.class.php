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
 * @class CentreonGraphTemplate
 */
class CentreonGraphTemplate
{
    /** @var CentreonDB */
    protected $db;

    /** @var CentreonInstance */
    protected $instanceObj;

    /**
     * CentreonGraphTemplate constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->instanceObj = new CentreonInstance($db);
    }

    /**
     * @param array $values
     * @param array $options
     * @param string $register
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [], $register = '1')
    {
        $items = [];
        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':graph' . $v . ',';
                $queryValues['graph' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        $query = 'SELECT graph_id, name FROM giv_graphs_template
            WHERE graph_id IN (' . $listValues . ') ORDER BY name';

        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetchRow()) {
            $items[] = ['id' => $row['graph_id'], 'text' => $row['name']];
        }

        return $items;
    }
}
