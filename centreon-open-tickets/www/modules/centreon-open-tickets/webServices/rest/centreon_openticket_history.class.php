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
require_once _CENTREON_PATH_ . '/www/api/class/webService.class.php';

class CentreonOpenticketHistory extends CentreonWebService
{
    /** @var Centreon */
    public $centreon;

    /** @var CentreonDB */
    public $pearDBMonitoring;

    /** @var CentreonDB|null */
    protected $pearDB;

    /**
     * @global Centreon $centreon
     */
    public function __construct()
    {
        global $centreon;

        $this->centreon = $centreon;
        $this->pearDBMonitoring = new CentreonDB('centstorage');

        parent::__construct();
    }

    /**
     * @param string $action
     * @param CentreonUser $user
     * @param bool $isInternal
     *
     * @return bool
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return (bool) (
            parent::authorize($action, $user, false)
            || ($user && $user->access->page(60421) !== CentreonACL::ACL_ACCESS_NONE)
        );
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
        $result = ['code' => 0, 'message' => 'history saved'];

        if (! isset($this->arguments['ticket_id'])
            || ! isset($this->arguments['user'])
            || ! isset($this->arguments['subject'])
            || ! is_array($this->arguments['links'])
        ) {
            return ['code' => 1, 'message' => 'parameters missing'];
        }
        $timestamp = time();
        if (isset($this->arguments['timestamp'])) {
            $timestamp = $this->arguments['timestamp'];
        }

        $allowedLinkKeys = ['hostname', 'service_description', 'host_state', 'service_state', 'host_id', 'service_id'];
        foreach ($this->arguments['links'] as $link) {
            if (is_array($link) && array_diff(array_keys($link), $allowedLinkKeys) !== []) {
                return ['code' => 1, 'message' => 'links parameters missing or wrong'];
            }
        }

        $hostAclCondition = '';
        $serviceAclCondition = '';
        $accessGroupIds = [];
        if (! $this->centreon->user->admin) {
            $accessGroupIds = array_keys($this->centreon->user->access->getAccessGroups());
            if ($accessGroupIds === []) {
                return ['code' => 1, 'message' => 'links parameters missing or wrong'];
            }
            $groupPlaceholders = implode(', ', array_fill(0, count($accessGroupIds), '?'));
            $hostAclCondition = ' AND EXISTS (SELECT 1 FROM centreon_acl acl WHERE acl.host_id = hosts.host_id'
                . " AND acl.group_id IN ({$groupPlaceholders}))";
            $serviceAclCondition = ' AND EXISTS (SELECT 1 FROM centreon_acl acl WHERE acl.host_id = hosts.host_id'
                . " AND acl.service_id = services.service_id AND acl.group_id IN ({$groupPlaceholders}))";
        }

        $links_ok = [];
        $stmt_host = $this->pearDBMonitoring->prepare('SELECT host_id FROM hosts WHERE name = ?' . $hostAclCondition);
        $stmt_service = $this->pearDBMonitoring->prepare(
            'SELECT hosts.host_id, services.service_id
            FROM hosts, services
            WHERE hosts.name = ?
            AND hosts.host_id = services.host_id
            AND services.description = ?' . $serviceAclCondition
        );
        foreach ($this->arguments['links'] as $link) {
            if (isset($link['hostname'], $link['service_description'])) {
                $res = $this->pearDBMonitoring->execute(
                    $stmt_service,
                    [$link['hostname'], $link['service_description'], ...$accessGroupIds]
                );
                if ($row = $stmt_service->fetch()) {
                    $links_ok[] = array_merge(
                        $link,
                        ['service_id' => $row['service_id'], 'host_id' => $row['host_id']]
                    );
                }
            } elseif (isset($link['hostname'])) {
                $res = $this->pearDBMonitoring->execute($stmt_host, [$link['hostname'], ...$accessGroupIds]);
                if ($row = $stmt_host->fetch()) {
                    $links_ok[] = array_merge($link, ['host_id' => $row['host_id']]);
                }
            }
        }

        if (count($links_ok) == 0) {
            return ['code' => 1, 'message' => 'links parameters missing or wrong'];
        }

        // Insert data
        $this->pearDBMonitoring->beginTransaction();
        $res = $this->pearDBMonitoring->query(
            'INSERT INTO mod_open_tickets (`timestamp`, `user`, `ticket_value`) VALUES ('
                . $this->pearDBMonitoring->quote($timestamp) . ', '
                . $this->pearDBMonitoring->quote($this->arguments['user']) . ', '
                . $this->pearDBMonitoring->quote($this->arguments['ticket_id'])
            . ')'
        );
        if (PEAR::isError($res) === true) {
            return ['code' => 1, 'message' => 'cannot insert in database'];
        }

        // Get Autoincrement
        $res = $this->pearDBMonitoring->query('SELECT LAST_INSERT_ID() AS last_id');
        if (PEAR::isError($res) === true || ! ($row = $res->fetch())) {
            return ['code' => 1, 'message' => 'database issue'];
        }
        $auto_ticket = $row['last_id'];

        // Insert data
        $res = $this->pearDBMonitoring->query(
            'INSERT INTO mod_open_tickets_data (`ticket_id`, `subject`) VALUES ('
                . $this->pearDBMonitoring->quote($auto_ticket) . ', '
                . $this->pearDBMonitoring->quote($this->arguments['subject'])
            . ')'
        );
        if (PEAR::isError($res) === true) {
            return ['code' => 1, 'message' => 'cannot insert in database'];
        }

        $stmt_link = $this->pearDBMonitoring->prepare(
            'INSERT INTO mod_open_tickets_link
            (`ticket_id`, `host_id`, `service_id`, `host_state`, `service_state`, `hostname`, `service_description`)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($links_ok as $link_ok) {
            $this->pearDBMonitoring->execute(
                $stmt_link,
                [
                    $auto_ticket,
                    $link_ok['host_id'],
                    $link_ok['service_id'] ?? null,
                    $link_ok['host_state'] ?? null,
                    $link_ok['service_state'] ?? null,
                    $link_ok['hostname'],
                    $link_ok['service_description'] ?? null,
                ]
            );
        }

        $this->pearDBMonitoring->commit();

        return $result;
    }
}
