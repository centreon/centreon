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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

class ServicegroupMonitoring
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
     * @param string $serviceGroupName
     * @param bool $isUserAdmin
     * @param AccessGroupCollection $accessGroups
     * @param bool $detailFlag
     * @return array
     */
    public function getHostStates(
        string $serviceGroupName,
        bool $isUserAdmin,
        AccessGroupCollection $accessGroups,
        bool $detailFlag = false,
    ): array {
        if (
            empty($serviceGroupName)
            || (! $isUserAdmin && $accessGroups->isEmpty())
        ) {
            return [];
        }

        $queryParameters = [];
        $queryParameters[] = QueryParameter::string('serviceGroupName', $serviceGroupName);

        $query = <<<'SQL'
                SELECT DISTINCT
                    h.host_id,
                    h.state,
                    h.name,
                    h.alias,
                    ssg.servicegroup_id
                FROM hosts h
                INNER JOIN services_servicegroups ssg
                    ON h.host_id = ssg.host_id
                INNER JOIN servicegroups sg
                    ON ssg.servicegroup_id = sg.servicegroup_id
            SQL;

        if (! $isUserAdmin) {
            $accessGroupsList = implode(', ', $accessGroups->getIds());

            $query .= <<<SQL
                    INNER JOIN centreon_acl
                        ON centreon_acl.host_id = h.host_id
                        AND centreon_acl.group_id IN ({$accessGroupsList})
                SQL;
        }

        $query .= <<<'SQL'
                WHERE h.name NOT LIKE '_Module_%'
                    AND h.enabled = 1
                    AND sg.name = :serviceGroupName
                ORDER BY h.name
            SQL;

        $tab = [];
        $detailTab = [];
        foreach ($this->dbb->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
            if (! isset($tab[$record['state']])) {
                $tab[$record['state']] = 0;
            }
            if (! isset($detailTab[$record['name']])) {
                $detailTab[$record['name']] = [];
            }
            foreach ($record as $key => $val) {
                $detailTab[$record['name']][$key] = $val;
            }
            $tab[$record['state']]++;
        }
        if ($detailFlag == true) {
            return $detailTab;
        }

        return $tab;
    }

    /**
     * Get Service States
     *
     * @param string $serviceGroupName
     * @param bool $isUserAdmin
     * @param AccessGroupCollection $accessGroups
     * @param bool $detailFlag
     * @return array
     */
    public function getServiceStates(
        string $serviceGroupName,
        bool $isUserAdmin,
        AccessGroupCollection $accessGroups,
        bool $detailFlag = false,
    ): array {
        if (
            empty($serviceGroupName)
            || (! $isUserAdmin && $accessGroups->isEmpty())
        ) {
            return [];
        }

        $query = <<<'SQL'
                SELECT DISTINCT
                    h.host_id,
                    s.state,
                    h.name,
                    s.service_id,
                    s.description,
                    ssg.servicegroup_id
                FROM hosts h
                INNER JOIN services s
                    ON h.host_id = s.host_id
                INNER JOIN services_servicegroups ssg
                    ON s.host_id = ssg.host_id
                    AND s.service_id = ssg.service_id
                INNER JOIN servicegroups sg
                    ON ssg.servicegroup_id = sg.servicegroup_id
            SQL;

        if (! $isUserAdmin) {
            $accessGroupsList = implode(', ', $accessGroups->getIds());
            $query .= <<<SQL
                    INNER JOIN centreon_acl acl
                        ON h.host_id = acl.host_id
                        AND s.service_id = acl.service_id
                        AND acl.group_id IN ({$accessGroupsList})
                SQL;
        }

        $query .= <<<'SQL'
                WHERE h.name NOT LIKE '_Module_%'
                    AND s.enabled = 1
                    AND sg.name = :serviceGroupName
                ORDER BY h.name
            SQL;

        $tab = [];
        $detailTab = [];
        $queryParameters = QueryParameters::create([QueryParameter::string('serviceGroupName', $serviceGroupName)]);
        foreach ($this->dbb->iterateAssociative($query, $queryParameters) as $record) {
            if (! isset($tab[$record['state']])) {
                $tab[$record['state']] = 0;
            }
            if (! isset($detailTab[$record['host_id']])) {
                $detailTab[$record['host_id']] = [];
            }
            if (isset($detailTab[$record['name']]) && ! isset($detailTab[$record['name']][$record['service_id']])) {
                $detailTab[$record['host_id']][$record['service_id']] = [];
            }
            foreach ($record as $key => $val) {
                $detailTab[$record['host_id']][$record['service_id']][$key] = $val;
            }
            $tab[$record['state']]++;
        }
        if ($detailFlag == true) {
            return $detailTab;
        }

        return $tab;
    }
}
