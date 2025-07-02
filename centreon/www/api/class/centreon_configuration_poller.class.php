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
 * @class CentreonConfigurationPoller
 */
class CentreonConfigurationPoller extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDB;

    /**
     * CentreonConfigurationPoller constructor
     */
    public function __construct()
    {
        $this->pearDB = new CentreonDB('centreon');
        parent::__construct();
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $queryValues = [];

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
        }

        // Check for select2 'q' argument
        $queryValues['name'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $queryPoller = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT ns.id, ns.name FROM nagios_server ns ';

        if (isset($this->arguments['t'])) {
            if ($this->arguments['t'] == 'remote') {
                $queryPoller .= 'JOIN remote_servers rs ON ns.id = rs.server_id ';
                // Exclude selected master Remote Server
                if (isset($this->arguments['e'])) {
                    $queryPoller .= 'WHERE ns.id <> :masterId ';
                    $queryValues['masterId'] = (int) $this->arguments['e'];
                }
            } elseif ($this->arguments['t'] == 'poller') {
                $queryPoller .= 'LEFT JOIN remote_servers rs ON  ns.id = rs.server_id '
                    . 'WHERE rs.ip IS NULL '
                    . "AND ns.localhost = '0' ";
            } elseif ($this->arguments['t'] == 'central') {
                $queryPoller .= "WHERE ns.localhost = '0' ";
            }
        } else {
            $queryPoller .= '';
        }

        if (stripos($queryPoller, 'WHERE') === false) {
            $queryPoller .= 'WHERE ns.name LIKE :name ';
        } else {
            $queryPoller .= 'AND ns.name LIKE :name ';
        }
        $queryPoller .= 'AND ns.ns_activate = "1" ';

        if (! $isAdmin) {
            $queryPoller .= $acl->queryBuilder('AND', 'id', $acl->getPollerString('ID', $this->pearDB));
        }
        $queryPoller .= 'ORDER BY name ';
        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryPoller .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($queryPoller);
        $stmt->bindParam(':name', $queryValues['name'], PDO::PARAM_STR);
        // bind exluded master Remote Server
        if (isset($this->arguments['t'])
            && $this->arguments['t'] == 'remote'
            && isset($this->arguments['e'])
        ) {
            $stmt->bindParam(':masterId', $queryValues['masterId'], PDO::PARAM_STR);
        }
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $pollerList = [];
        while ($data = $stmt->fetch()) {
            $pollerList[] = ['id' => $data['id'], 'text' => $data['name']];
        }

        return ['items' => $pollerList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
