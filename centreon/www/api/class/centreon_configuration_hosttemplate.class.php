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
 * @class CentreonConfigurationHosttemplate
 */
class CentreonConfigurationHosttemplate extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationHosttemplate constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        $queryValues = [];

        // Check for select2 'q' argument
        $queryValues['hostName'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT h.host_name, h.host_id FROM host h '
            . 'WHERE h.host_register = "0" '
            . 'AND h.host_name LIKE :hostName '
            . 'ORDER BY h.host_name ';

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
        $stmt->bindParam(':hostName', $queryValues['hostName'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $hostList = [];
        while ($data = $stmt->fetch()) {
            $hostList[] = ['id' => htmlentities($data['host_id']), 'text' => $data['host_name']];
        }

        return ['items' => $hostList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
