<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once _CENTREON_PATH_ . "/www/class/centreonDB.class.php";
require_once _CENTREON_PATH_ . "/www/api/class/webService.class.php";

class CentreonOpenticketHistory extends CentreonWebService
{
    /**
     *
     * @var type
     */
    protected $pearDB;

    /**
     *
     * @global type $centreon
     */
    public function __construct()
    {
        global $centreon;

        $this->centreon = $centreon;
        $this->pearDBMonitoring = new CentreonDB('centstorage');

        parent::__construct();
    }

    public function postSaveHistory()
    {
        /* {
         *   "ticket_id": "199",
         *   "timestamp": 1498125177,
         *   "user": "toto",
         *   "subject": "mon sujet",
         *   "links": [
         *      { "hostname": "plop", "service_description": "caca", "service_state": "1" },
         *      { "hostname": "plop", "service_description": "caca2", "service_state": "2" }
         *   ]
         * }
         */
        $result = array('code' => 0, 'message' => 'history saved');

        if (!isset($this->arguments['ticket_id'])
            || !isset($this->arguments['user'])
            || !isset($this->arguments['subject'])
            || !is_array($this->arguments['links'])
        ) {
            $result = array('code' => 1, 'message' => 'parameters missing');
            return $result;
        }
        $timestamp = time();
        if (isset($this->arguments['timestamp'])) {
            $timestamp = $this->arguments['timestamp'];
        }

        $links_ok = array();
        $stmt_host = $this->pearDBMonitoring->prepare('SELECT host_id FROM hosts WHERE name = ?');
        $stmt_service = $this->pearDBMonitoring->prepare(
            'SELECT hosts.host_id, services.service_id
            FROM hosts, services
            WHERE hosts.name = ?
            AND hosts.host_id = services.host_id
            AND services.description = ?'
        );
        foreach ($this->arguments['links'] as $link) {
            if (isset($link['hostname']) && isset($link['service_description'])) {
                $res = $this->pearDBMonitoring->execute(
                    $stmt_service,
                    array($link['hostname'], $link['service_description'])
                );
                if ($row = $res->fetch()) {
                    $links_ok[] = array_merge(
                        $link,
                        array('service_id' => $row['service_id'], 'host_id' => $row['host_id'])
                    );
                }
            } elseif (isset($link['hostname'])) {
                $res = $this->pearDBMonitoring->execute($stmt_host, array($link['hostname']));
                if ($row = $res->fetch()) {
                    $links_ok[] = array_merge($link, array('host_id' => $row['host_id']));
                }
            }
        }

        if (count($links_ok) == 0) {
            $result = array('code' => 1, 'message' => 'links parameters missing or wrong');
            return $result;
        }

        /* Insert data */
        $this->pearDBMonitoring->beginTransaction();
        $res = $this->pearDBMonitoring->query(
            "INSERT INTO mod_open_tickets (`timestamp`, `user`, `ticket_value`) VALUES (" .
                $this->pearDBMonitoring->quote($timestamp) . ", " .
                $this->pearDBMonitoring->quote($this->arguments['user']) . ", " .
                $this->pearDBMonitoring->quote($this->arguments['ticket_id']) .
            ")"
        );
        if (true === PEAR::isError($res)) {
            $result = array('code' => 1, 'message' => 'cannot insert in database');
            return $result;
        }

        /* Get Autoincrement */
        $res = $this->pearDBMonitoring->query("SELECT LAST_INSERT_ID() AS last_id");
        if (true === PEAR::isError($res) || !($row = $res->fetch())) {
            $result = array('code' => 1, 'message' => 'database issue');
            return $result;
        }
        $auto_ticket = $row['last_id'];

        /* Insert data */
        $res = $this->pearDBMonitoring->query(
            "INSERT INTO mod_open_tickets_data (`ticket_id`, `subject`) VALUES (" .
                $this->pearDBMonitoring->quote($auto_ticket) . ", " .
                $this->pearDBMonitoring->quote($this->arguments['subject']) .
            ")"
        );
        if (true === PEAR::isError($res)) {
            $result = array('code' => 1, 'message' => 'cannot insert in database');
            return $result;
        }

        /* Insert data */
        foreach ($links_ok as $link_ok) {
            $names = '';
            $values = '';
            $append = '';
            foreach ($link_ok as $key => $value) {
                $names .= $append . $key;
                $values .= $append . $this->pearDBMonitoring->quote($value);
                $append = ', ';
            }
            $res = $this->pearDBMonitoring->query(
                "INSERT INTO mod_open_tickets_link (`ticket_id`, $names) VALUES (" .
                    $this->pearDBMonitoring->quote($auto_ticket) . ", " .
                    $values .
                ")"
            );
            if (true === PEAR::isError($res)) {
                $result = array('code' => 1, 'message' => 'cannot insert in database');
                return $result;
            }
        }

        $this->pearDBMonitoring->commit();
        return $result;
    }
}
