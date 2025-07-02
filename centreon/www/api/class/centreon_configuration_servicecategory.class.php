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
 * @class CentreonConfigurationServicecategory
 */
class CentreonConfigurationServicecategory extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationServicecategory constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     * @return array
     */
    public function getList()
    {
        $queryValues = [];

        // Check for select2 'q' argument
        $queryValues['name'] = false !== isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        /*
         * Check for select2 't' argument
         * 'a' or empty = category and severitiy
         * 'c' = category only
         * 's' = severity only
         */
        if (isset($this->arguments['t'])) {
            $selectList = ['a', 'c', 's'];
            if (in_array(strtolower($this->arguments['t']), $selectList)) {
                $t = $this->arguments['t'];
            } else {
                throw new RestBadRequestException('Error, Bad type');
            }
        } else {
            $t = '';
        }

        $queryContact = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT sc_id, sc_name FROM service_categories '
            . 'WHERE sc_name LIKE :name ';
        if ($t == 'c') {
            $queryContact .= 'AND level IS NULL ';
        }
        if ($t == 's') {
            $queryContact .= 'AND level IS NOT NULL ';
        }
        $queryContact .= 'ORDER BY sc_name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryContact .= 'LIMIT :offset,:limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }
        $stmt = $this->pearDB->prepare($queryContact);
        $stmt->bindParam(':name', $queryValues['name'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $serviceList = [];
        while ($data = $stmt->fetch()) {
            $serviceList[] = ['id' => $data['sc_id'], 'text' => $data['sc_name']];
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
