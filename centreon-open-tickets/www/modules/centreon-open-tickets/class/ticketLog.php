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

class Centreon_OpenTickets_Log
{
    protected $_db;

    protected $_dbStorage;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @param CentreonDB $dbStorage
     * @return void
     */
    public function __construct($db, $dbStorage)
    {
        $this->_db = $db;
        $this->_dbStorage = $dbStorage;
    }

    /*
     * Params example:
     *       [host_filter] => Array
     *           (
     *               [0] => 105
     *           )
     *       [service_filter] => Array
     *           (
     *               [0] => 104-836
     *               [1] => 105-838
     *           )
     *
     *       [subject] => test
     *       [StartDate] => 06/10/2016
     *       [StartTime] => 10:20
     *       [EndDate] => 06/06/2016
     *       [EndTime] =>
     *       [ticket_id] => XXXX
     *       [period] => 10800
     */
    public function getLog($params, $centreon_bg, $pagination = 30, $current_page = 1, $all = false)
    {
        // Get time
        $range_time = $this->getTime(
            $params['StartDate'],
            $params['StartTime'],
            $params['EndDate'],
            $params['EndTime'],
            $params['period']
        );

        $bindParams = [];
        $query = 'SELECT SQL_CALC_FOUND_ROWS mot.ticket_value AS ticket_id, mot.timestamp, mot.user, '
            . 'motl.hostname AS host_name, motl.service_description, motd.subject '
            . 'FROM mod_open_tickets_link motl, mod_open_tickets_data motd, mod_open_tickets mot WHERE ';
        if (! is_null($range_time['start'])) {
            $query .= 'mot.timestamp >= :rangeStart AND ';
            $bindParams[':rangeStart'] = [PDO::PARAM_INT, (int) $range_time['start']];
        }
        if (! is_null($range_time['end'])) {
            $query .= 'mot.timestamp <= :rangeEnd AND ';
            $bindParams[':rangeEnd'] = [PDO::PARAM_INT, (int) $range_time['end']];
        }
        if (! is_null($params['ticket_id']) && $params['ticket_id'] != '') {
            $query .= 'mot.ticket_value LIKE :ticketId AND ';
            $bindParams[':ticketId'] = [PDO::PARAM_STR, '%' . $params['ticket_id'] . '%'];
        }
        if (! is_null($params['subject']) && $params['subject'] != '') {
            $query .= 'motd.subject LIKE :subject AND ';
            $bindParams[':subject'] = [PDO::PARAM_STR, '%' . $params['subject'] . '%'];
        }

        $serviceConditions = [];
        $serviceParamIndex = 0;
        if (isset($params['service_filter']) && is_array($params['service_filter'])) {
            foreach ($params['service_filter'] as $val) {
                $tmp = explode('-', $val);
                $hostParam = ':svcFilterHost' . $serviceParamIndex;
                $svcParam = ':svcFilterSvc' . $serviceParamIndex;
                $serviceConditions[] = '(motl.host_id = ' . $hostParam . ' AND motl.service_id = ' . $svcParam . ')';
                $bindParams[$hostParam] = [PDO::PARAM_INT, (int) $tmp[0]];
                $bindParams[$svcParam] = [PDO::PARAM_INT, (int) $tmp[1]];
                $serviceParamIndex++;
            }
        }

        $hostPlaceholders = [];
        if (isset($params['host_filter']) && is_array($params['host_filter']) && $params['host_filter'] !== []) {
            foreach ($params['host_filter'] as $idx => $hostId) {
                $param = ':hostFilter' . $idx;
                $hostPlaceholders[] = $param;
                $bindParams[$param] = [PDO::PARAM_INT, (int) $hostId];
            }
            if ($serviceConditions !== []) {
                $query .= '(motl.host_id IN (' . implode(',', $hostPlaceholders) . ') '
                    . 'OR (' . implode(' OR ', $serviceConditions) . ')) AND ';
            } else {
                $query .= 'motl.host_id IN (' . implode(',', $hostPlaceholders) . ') AND ';
            }
        } elseif ($serviceConditions !== []) {
            $query .= '(' . implode(' OR ', $serviceConditions) . ') AND ';
        }

        if (! $centreon_bg->is_admin) {
            $ids = $centreon_bg->access->getAccessGroups()->getIds();
            $accessGroupIds = empty($ids) ? '0' : implode(',', $ids);
            $query .= <<<SQL
                    EXISTS(
                        SELECT 1 FROM centreon_acl WHERE centreon_acl.group_id IN ({$accessGroupIds})
                        AND motl.host_id = centreon_acl.host_id
                        AND (motl.service_id IS NULL OR motl.service_id = centreon_acl.service_id)
                    ) AND 
                SQL;
        }
        $query .= 'motl.ticket_id = motd.ticket_id AND motd.ticket_id = mot.ticket_id
            ORDER BY `timestamp` DESC ';

        // Pagination
        if (is_null($current_page) || $current_page <= 0) {
            $current_page = 1;
        }
        if (is_null($pagination) || $pagination <= 0) {
            $pagination = 30;
        }

        if ($all == false) {
            $query .= 'LIMIT :paginationOffset, :paginationLimit';
            $bindParams[':paginationOffset'] = [PDO::PARAM_INT, ($current_page - 1) * $pagination];
            $bindParams[':paginationLimit'] = [PDO::PARAM_INT, (int) $pagination];
        }

        $stmt = $this->_dbStorage->prepare($query);
        foreach ($bindParams as $param => $paramData) {
            $stmt->bindValue($param, $paramData[1], $paramData[0]);
        }
        $stmt->execute();
        $result['tickets'] = $stmt->fetchAll();
        $rows = $stmt->rowCount();
        $result['rows'] = $rows;
        $result['start'] = $range_time['start'];
        $result['end'] = $range_time['end'];

        return $result;
    }

    protected function getTime($start_date, $start_time, $end_date, $end_time, $period)
    {
        $start = null;
        $end = null;
        $auto_period = 1;
        if (! is_null($start_date) && $start_date != '') {
            $auto_period = 0;
            if ($start_time == '') {
                $start_time = '00:00';
            }

            preg_match("/^([0-9]*)\/([0-9]*)\/([0-9]*)/", $start_date, $matchesD);
            preg_match('/^([0-9]*):([0-9]*)/', $start_time, $matchesT);
            $start = mktime($matchesT[1], $matchesT[2], '0', $matchesD[1], $matchesD[2], $matchesD[3]);
        }
        if (! is_null($end_date) && $end_date != '') {
            $auto_period = 0;
            if ($end_time == '') {
                $end_time = '00:00';
            }

            preg_match("/^([0-9]*)\/([0-9]*)\/([0-9]*)/", $end_date, $matchesD);
            preg_match('/^([0-9]*):([0-9]*)/', $end_time, $matchesT);
            $end = mktime($matchesT[1], $matchesT[2], '0', $matchesD[1], $matchesD[2], $matchesD[3]);
        }

        if ($auto_period == 1 && ! is_null($period) && $period > 0) {
            $start = time() - ($period);
            $end = time();
        }

        return ['start' => $start, 'end' => $end];
    }
}
