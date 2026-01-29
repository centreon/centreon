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
 * @class CentreonMonitoringPoller
 */
class CentreonMonitoringPoller extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonMonitoringPoller constructor
     */
    public function __construct()
    {
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        parent::__construct();
    }

    /**
     * @throws Exception
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $queryValues = [];

        // Check for select2 'q' argument
        $queryValues['name'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $queryPoller = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT instance_id, name FROM instances '
            . 'WHERE name LIKE :name AND deleted=0 ';

        if (! $centreon->user->admin) {
            $acl = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
            $queryPoller .= 'AND instances.instance_id IN ('
                . $acl->getPollerString('ID', $this->pearDBMonitoring) . ') ';
        }

        $queryPoller .= ' ORDER BY name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryPoller .= 'LIMIT :offset,:limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDBMonitoring->prepare($queryPoller);
        $stmt->bindParam(':name', $queryValues['name'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        $pollerList = [];
        while ($data = $stmt->fetch()) {
            $pollerList[] = ['id' => $data['instance_id'], 'text' => $data['name']];
        }

        return ['items' => $pollerList, 'total' => (int) $this->pearDBMonitoring->numberRows()];
    }
}
