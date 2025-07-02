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

require_once _CENTREON_PATH_ . 'www/class/centreonInstance.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonCommand.class.php';

/**
 * Class
 *
 * @class CentreonMetrics
 */
class CentreonMetrics
{
    /** @var CentreonDB */
    public $dbo;

    /** @var CentreonDB */
    protected $db;

    /** @var CentreonInstance */
    protected $instanceObj;

    /** @var CentreonService */
    protected $serviceObj;

    /**
     * CentreonMetrics constructor
     *
     * @param CentreonDB $db
     *
     * @throws PDOException
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->dbo = new CentreonDB('centstorage');
        $this->instanceObj = new CentreonInstance($db);
        $this->serviceObj = new CentreonService($db);
    }

    /**
     * Get metrics information from ids to populat select2
     *
     * @param array $values list of metric ids
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [])
    {
        $metrics = [];
        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $v) {
                $multiValues = explode(',', $v);
                foreach ($multiValues as $item) {
                    $listValues .= ':metric' . $item . ',';
                    $queryValues['metric' . $item] = (int) $item;
                }
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues = '""';
        }

        $queryService = "SELECT SQL_CALC_FOUND_ROWS m.metric_id, CONCAT(i.host_name,' - ', i.service_description,"
            . "' - ', m.metric_name) AS fullname "
            . 'FROM metrics m, index_data i '
            . 'WHERE m.metric_id IN (' . $listValues . ') '
            . 'AND i.id = m.index_id '
            . 'ORDER BY fullname COLLATE utf8_general_ci';

        $stmt = $this->dbo->prepare($queryService);
        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $metrics[] = [
                'id' => $row['metric_id'],
                'text' => $row['fullname'],
            ];
        }

        return $metrics;
    }
}
