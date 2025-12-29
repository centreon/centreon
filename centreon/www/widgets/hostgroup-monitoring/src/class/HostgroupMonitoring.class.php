<?php

/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

class HostgroupMonitoring
{
    protected $dbb;

    /**
     * Constructor
     *
     * @param CentreonDB $dbb
     * @return void
     */
    public function __construct($dbb)
    {
        $this->dbb = $dbb;
    }

    /**
     * Get Host States
     *
     * @param $data | array('name' => '', 'host_state' => array(), 'service_state' => array())
     * @param bool $isUserAdmin
     * @param AccessGroupCollection $accessGroups
     * @param bool $detailFlag
     */
    public function getHostStates(
        array &$data,
        bool $isUserAdmin,
        AccessGroupCollection $accessGroups,
        bool $detailFlag = false,
    ) {
        if (
            $data === []
            || (! $isUserAdmin && $accessGroups->isEmpty())
        ) {
            return [];
        }

        ['parameters' => $queryParameters, 'placeholderList' => $hostGroupNameList] = createMultipleBindParameters(
            array_keys($data),
            'hg_name',
            QueryParameterTypeEnum::STRING,
        );

        $query = <<<SQL
                SELECT
                    1 AS REALTIME,
                    h.host_id,
                    h.state,
                    h.name,
                    h.alias,
                    hhg.hostgroup_id,
                    hg.name AS hgname
                FROM hosts h
                INNER JOIN hosts_hostgroups hhg
                    ON h.host_id = hhg.host_id
                INNER JOIN hostgroups hg
                    ON hhg.hostgroup_id = hg.hostgroup_id
                    AND hg.name IN ({$hostGroupNameList})
            SQL;

        if (! $isUserAdmin) {
            $accessGroupsList = implode(', ', $accessGroups->getIds());

            $query .= <<<SQL
                    INNER JOIN centreon_acl
                        ON centreon_acl.host_id = h.host_id
                        AND centreon_acl.group_id IN ({$accessGroupsList})
                SQL;
        }

        $query .= ' WHERE h.enabled = 1 ORDER BY h.name';

        foreach ($this->dbb->iterateAssociative($query, QueryParameters::create($queryParameters)) as $row) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (!isset($data[$k]['host_state'][$row['name']])) {
                    $data[$k]['host_state'][$row['name']] = array();
                }
                foreach ($row as $key => $val) {
                    $data[$k]['host_state'][$row['name']][$key] = $val;
                }
            } else {
                if (!isset($data[$k]['host_state'][$row['state']])) {
                    $data[$k]['host_state'][$row['state']] = 0;
                }
                $data[$k]['host_state'][$row['state']]++;
            }
        }
    }

    /**
     * Get Service States
     *
     * @param array $data
     * @param bool $isUserAdmin
     * @param AccessGroupCollection $accessGroups
     * @param bool $detailFlag
     */
    public function getServiceStates(
        array &$data,
        bool $isUserAdmin,
        AccessGroupCollection $accessGroups,
        bool $detailFlag = false,
    ) {
        if (
            $data === []
            || (! $isUserAdmin && $accessGroups->isEmpty())
        ) {
            return [];
        }

        ['parameters' => $queryParameters, 'placeholderList' => $hostGroupNameList] = createMultipleBindParameters(
            array_keys($data),
            'hg_name',
            QueryParameterTypeEnum::STRING,
        );

        $query = <<<SQL
                SELECT
                    1 AS REALTIME,
                    h.host_id,
                    h.name,
                    s.service_id,
                    s.description,
                    s.state,
                    hhg.hostgroup_id,
                    hg.name as hgname,
                    CASE s.state
                        WHEN 0 THEN 3
                        WHEN 2 THEN 0
                        WHEN 3 THEN 2
                        ELSE s.state
                    END AS tri
                FROM hosts h
                INNER JOIN hosts_hostgroups hhg
                    ON h.host_id = hhg.host_id
                INNER JOIN services s
                    ON s.host_id = h.host_id
                INNER JOIN hostgroups hg
                    ON hg.hostgroup_id = hhg.hostgroup_id
                    AND hg.name IN ({$hostGroupNameList})
            SQL;

        if (! $isUserAdmin) {
            $accessGroupsList = implode(', ', $accessGroups->getIds());

            $query .= <<<SQL
                    INNER JOIN centreon_acl
                        ON centreon_acl.host_id = h.host_id
                        AND centreon_acl.service_id = s.service_id
                        AND centreon_acl.group_id IN ({$accessGroupsList})
                SQL;
        }

        $query .= <<<'SQL'
                WHERE s.enabled = 1 AND h.enabled = 1 ORDER BY tri, s.description ASC
            SQL;

        foreach ($this->dbb->iterateAssociative($query, QueryParameters::create($queryParameters)) as $row) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (!isset($data[$k]['service_state'][$row['host_id']])) {
                    $data[$k]['service_state'][$row['host_id']] = array();
                }
                if (
                    isset($data[$k]['service_state'][$row['host_id']])
                    && !isset($data[$k]['service_state'][$row['host_id']][$row['service_id']])
                ) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']] = array();
                }
                foreach ($row as $key => $val) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']][$key] = $val;
                }
            } else {
                if (!isset($data[$k]['service_state'][$row['state']])) {
                    $data[$k]['service_state'][$row['state']] = 0;
                }
                $data[$k]['service_state'][$row['state']]++;
            }
        }
    }
}
