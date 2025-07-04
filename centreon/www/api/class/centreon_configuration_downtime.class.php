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
require_once _CENTREON_PATH_ . '/www/class/centreonDowntime.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationDowntime
 */
class CentreonConfigurationDowntime extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationDowntime constructor
     */
    public function __construct()
    {
        global $pearDBO;

        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        $pearDBO = $this->pearDBMonitoring;
    }

    /**
     * @throws Exception
     * @return array
     */
    public function getList()
    {
        $queryValues = [];
        // Check for select2 'q' argument
        $queryValues['dtName'] = false === isset($this->arguments['q']) ? '%%' : '%' . (string) $this->arguments['q'] . '%';

        $queryDowntime = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT dt.dt_name, dt.dt_id '
            . 'FROM downtime dt '
            . 'WHERE dt.dt_name LIKE :dtName '
            . 'ORDER BY dt.dt_name';

        $stmt = $this->pearDB->prepare($queryDowntime);
        $stmt->bindParam(':dtName', $queryValues['dtName'], PDO::PARAM_STR);
        $dbResult = $stmt->execute();

        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        $downtimeList = [];
        while ($data = $stmt->fetch()) {
            $downtimeList[] = ['id' => htmlentities($data['dt_id']), 'text' => $data['dt_name']];
        }

        return ['items' => $downtimeList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
