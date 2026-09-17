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

require_once 'Centreon/Object/Object.php';

/**
 * Used for interacting with hosts
 *
 * @author Toufik MECHOUET
 */
class Centreon_Object_Service_Template extends Centreon_Object
{
    protected $table = 'service';

    protected $primaryKey = 'service_id';

    protected $uniqueLabelField = 'service_description';

    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $paramName
     * @param array $paramValues
     * @return array
     */
    public function getIdByParameter($paramName, $paramValues = [])
    {
        $sql = "SELECT {$this->primaryKey} FROM {$this->table} WHERE ";
        $condition = '';
        if (! is_array($paramValues)) {
            $paramValues = [$paramValues];
        }
        foreach ($paramValues as $val) {
            if ($condition != '') {
                $condition .= ' OR ';
            }
            $condition .= $paramName . ' = ? ';
        }
        if ($condition) {
            $sql .= $condition;
            $sql .= ' AND ' . $this->table . ".service_register = '0' ";
            $rows = $this->getResult($sql, $paramValues, 'fetchAll');
            $tab = [];
            foreach ($rows as $val) {
                $tab[] = $val[$this->primaryKey];
            }

            return $tab;
        }

        return [];
    }
}
