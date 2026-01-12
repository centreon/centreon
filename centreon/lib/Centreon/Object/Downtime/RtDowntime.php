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

require_once 'Centreon/Object/ObjectRt.php';

/**
 * Class
 *
 * @class Centreon_Object_RtDowntime
 */
class Centreon_Object_RtDowntime extends Centreon_ObjectRt
{
    /** @var string */
    protected $table = 'downtimes';

    /** @var string */
    protected $name = 'downtime_name';

    /** @var string */
    protected $primaryKey = 'downtime_id';

    /** @var string */
    protected $uniqueLabelField = 'comment_data';

    /**
     * @param array $hostList
     * @return array
     */
    public function getHostDowntimes($hostList = [])
    {
        $hostFilter = '';

        if (! empty($hostList)) {
            $hostFilter = "AND h.name IN ('" . implode("','", $hostList) . "') ";
        }

        $query = 'SELECT downtime_id, name, author, actual_start_time , actual_end_time, '
            . 'start_time, end_time, comment_data, duration, fixed '
            . 'FROM downtimes d, hosts h '
            . 'WHERE d.host_id = h.host_id '
            . 'AND d.cancelled = 0 '
            . 'AND type = 2 '
            . 'AND end_time > UNIX_TIMESTAMP(NOW()) '
            . $hostFilter
            . 'ORDER BY actual_start_time, name';

        return $this->getResult($query);
    }

    /**
     * @param array $svcList
     * @return array
     */
    public function getSvcDowntimes($svcList = [])
    {
        $serviceFilter = '';

        if (! empty($svcList)) {
            $serviceFilter = 'AND (';
            $filterTab = [];
            $counter = count($svcList);
            for ($i = 0; $i < $counter; $i += 2) {
                $hostname = $svcList[$i];
                $serviceDescription = $svcList[$i + 1];
                $filterTab[] = '(h.name = "' . $hostname . '" AND s.description = "' . $serviceDescription . '")';
            }
            $serviceFilter .= implode(' AND ', $filterTab) . ') ';
        }

        $query = 'SELECT d.downtime_id, h.name, s.description, author, actual_start_time, actual_end_time, '
            . 'start_time, end_time, comment_data, duration, fixed '
            . 'FROM downtimes d, hosts h, services s '
            . 'WHERE d.service_id = s.service_id '
            . 'AND d.host_id = s.host_id '
            . 'AND s.host_id = h.host_id '
            . 'AND d.cancelled = 0 '
            . 'AND d.type = 1 '
            . 'AND end_time > UNIX_TIMESTAMP(NOW()) '
            . $serviceFilter
            . 'ORDER BY actual_start_time, h.name, s.description';

        return $this->getResult($query);
    }

    /**
     * @param $id
     * @return array
     */
    public function getCurrentDowntime($id)
    {
        $query = 'SELECT * FROM downtimes WHERE ISNULL(actual_end_time) '
            . ' AND end_time > ' . time() . ' AND downtime_id = ' . $id;

        return $this->getResult($query, [], 'fetch');
    }
}
