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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationManufacturer
 */
class CentreonConfigurationManufacturer extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationManufacturer constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        $queryValues = [];
        // Check for select2 'q' argument
        $queryValues['name'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT id, name FROM traps_vendor '
            . 'WHERE name LIKE :name '
            . 'ORDER BY name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $query .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($query);
        $stmt->bindParam(':name', $queryValues['name'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $manufacturerList = [];
        while ($data = $stmt->fetch()) {
            $manufacturerList[] = ['id' => $data['id'], 'text' => $data['name']];
        }

        return ['items' => $manufacturerList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
