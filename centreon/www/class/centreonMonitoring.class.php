<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;

/**
 * Class
 *
 * @class CentreonMonitoring
 */
class CentreonMonitoring
{
    use SqlMultipleBindTrait;

    public const SERVICE_STATUS_OK = 0;
    public const SERVICE_STATUS_WARNING = 1;
    public const SERVICE_STATUS_CRITICAL = 2;
    public const SERVICE_STATUS_UNKNOWN = 3;
    public const SERVICE_STATUS_PENDING = 4;

    /** @var string */
    protected $poller;
    /** @var CentreonDB */
    protected $DB;
    /** @var */
    protected $objBroker;

    /**
     * CentreonMonitoring constructor
     *
     * @param CentreonDB $DB
     */
    public function __construct($DB)
    {
        $this->DB = $DB;
    }

    /**
     * @param string $pollerId
     *
     * @return void
     */
    public function setPoller($pollerId): void
    {
        $this->poller = $pollerId;
    }

    /**
     * @return string
     */
    public function getPoller()
    {
        return $this->poller;
    }

    /**
     * @param $host_name
     * @param $objXMLBG
     * @param $o
     * @param $status
     * @param $obj
     *
     * Proxy function
     *
     * @param string $hostName
     * @param CentreonXMLBGRequest $centreonXMLBGRequest
     * @param string $o
     * @param int $serviceStatus
     *
     * @throws CentreonDbException
     * @return int
     */
    public function getServiceStatusCount(
        string $hostName,
        CentreonXMLBGRequest $centreonXMLBGRequest,
        string $o,
        int $serviceStatus
    ): int {
        $toBind = [':service_status' => $serviceStatus, ':host_name' => $hostName];
        if ($centreonXMLBGRequest->is_admin) {
            $query = <<<SQL
                SELECT count(distinct s.service_id) as count, 1 AS REALTIME
                FROM services s
                INNER JOIN hosts h
                    ON h.host_id = s.host_id
                WHERE s.state = :service_status
                    AND s.host_id = h.host_id
                    AND s.enabled = '1'
                    AND h.enabled = '1'
                    AND h.name = :host_name
                SQL;
        } else {
            $accessGroups = $centreonXMLBGRequest->access->getAccessGroups();
            [$bindValues, $subRequest] = $this->createMultipleBindQuery(array_keys($accessGroups), ':grp_');
            $query = <<<SQL
                SELECT count(distinct s.service_id) as count, 1 AS REALTIME
                FROM services s
                INNER JOIN hosts h
                    ON h.host_id = s.host_id
                INNER JOIN centreon_acl acl
                    ON acl.host_id = h.host_id
                    AND acl.service_id = s.service_id
                WHERE s.state = :service_status
                    AND s.host_id = h.host_id
                    AND s.enabled = '1'
                    AND h.enabled = '1'
                    AND h.name = :host_name
                    AND acl.group_id IN ({$subRequest})
                SQL;
            $toBind = [...$toBind, ...$bindValues];
        }

        # Acknowledgement filter
        if ($o === 'svcSum_ack_0') {
            $query .= ' AND s.acknowledged = 0 AND s.state != 0 ';
        } elseif ($o === "svcSum_ack_1") {
            $query .= ' AND s.acknowledged = 1 AND s.state != 0 ';
        }
        $statement = $centreonXMLBGRequest->DBC->prepare($query);
        $centreonXMLBGRequest->DBC->executePreparedQuery($statement, $toBind);

        if (($count = $centreonXMLBGRequest->DBC->fetchColumn($statement)) !== false) {
            return (int) $count;
        }

        return 0;
    }

    /**
     * @param int[] $hostIds
     * @param CentreonXMLBGRequest $centreonXMLBGRequest
     * @param string $o
     * @param int $monitoringServerId
     *
     * @throws CentreonDbException
     * @return array<string, array<string, array{state: int, service_id: int}>>
     */
    public function getServiceStatus(
        array $hostIds,
        CentreonXMLBGRequest $centreonXMLBGRequest,
        string $o,
        int $monitoringServerId,
    ): array {
        if ($hostIds === []) {
            return [];
        }
        [$hostIdsToBind, $hostNamesSubQuery] = $this->createMultipleBindQuery($hostIds, ':host_id_');
        $toBind = $hostIdsToBind;
        $query = <<<SQL
            SELECT
                1 AS REALTIME,
                h.name, s.description AS service_name, s.state, s.service_id,
                (CASE s.state
                    WHEN 0 THEN 3
                    WHEN 2 THEN 0
                    WHEN 3 THEN 2
                    ELSE s.state
                END) AS tri
            FROM hosts h
            INNER JOIN services s
                ON s.host_id = h.host_id
            SQL;

        if (! $centreonXMLBGRequest->is_admin) {
            $accessGroups = $centreonXMLBGRequest->access->getAccessGroups();
            [$bindValues, $accessGroupsSubQuery] = $this->createMultipleBindQuery(array_keys($accessGroups), ':grp_');
            $toBind = [...$toBind, ...$bindValues];
            $query .= <<<SQL
                
                INNER JOIN centreon_acl
                    ON centreon_acl.host_id = h.host_id
                    AND centreon_acl.service_id = s.service_id
                    AND centreon_acl.group_id IN ({$accessGroupsSubQuery})
                SQL;
        }
        $query .= <<<SQL

            WHERE s.enabled = '1'
                AND h.enabled = '1'
                AND h.name NOT LIKE '\_Module\_%'
            SQL;

        if ($o === "svcgrid_pb" || $o === "svcOV_pb") {
            $query .= " AND s.state != 0 ";
        } elseif ($o === "svcgrid_ack_0" || $o === "svcOV_ack_0") {
            $query .= " AND s.acknowledged = 0 AND s.state != 0 ";
        } elseif ($o === "svcgrid_ack_1" || $o === "svcOV_ack_1") {
            $query .= " AND s.acknowledged = 1 ";
        }

        $query .= " AND h.host_id IN ({$hostNamesSubQuery}) ";

        # Instance filter
        if ($monitoringServerId !== -1) {
            $query .=  " AND h.instance_id = :monitoring_server_id";
            $toBind[':monitoring_server_id'] = $monitoringServerId;
        }

        $query .= " ORDER BY tri ASC, service_name";

        $serviceDetails = [];
        $statement = $centreonXMLBGRequest->DBC->prepare($query);
        $centreonXMLBGRequest->DBC->executePreparedQuery($statement, $toBind);

        while ($result = $centreonXMLBGRequest->DBC->fetch($statement)) {
            if (! isset($serviceDetails[$result["name"]])) {
                $serviceDetails[$result["name"]] = [];
            }
            $serviceDetails[$result["name"]][$result["service_name"]] = [
                'state' => $result["state"],
                'service_id' => $result['service_id']
            ];
        }
        $centreonXMLBGRequest->DBC->closeQuery($statement);

        return $serviceDetails;
    }
}
