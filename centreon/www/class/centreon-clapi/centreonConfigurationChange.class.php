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

namespace CentreonClapi;

use CentreonDB;
use Exception;
use PDO;
use PDOException;

/**
 * Class
 *
 * @class CentreonConfigurationChange
 * @package CentreonClapi
 */
class CentreonConfigurationChange
{
    public const UNKNOWN_RESOURCE_TYPE = 'Unknown resource type';
    public const RESOURCE_TYPE_HOST = 'host';
    public const RESOURCE_TYPE_HOSTGROUP = 'hostgroup';
    public const RESOURCE_TYPE_SERVICE = 'service';
    public const RESOURCE_TYPE_SERVICEGROUP = 'servicegroup';

    /**
     * CentreonConfigurationChange constructor
     *
     * @param CentreonDB $db
     */
    public function __construct(
        private CentreonDB $db
    ) {
    }

    /**
     * Return ids of hosts linked to hostgroups
     *
     * @param int[] $hostgroupIds
     * @param bool $shouldHostgroupBeEnabled (default true)
     * @throws Exception
     * @return int[]
     */
    public function findHostsForConfigChangeFlagFromHostGroupIds(
        array $hostgroupIds,
        bool $shouldHostgroupBeEnabled = true
    ): array {
        if ($hostgroupIds === []) {
            return [];
        }

        $bindedParams = [];
        foreach ($hostgroupIds as $key => $hostgroupId) {
            $bindedParams[':hostgroup_id_' . $key] = $hostgroupId;
        }

        if ($shouldHostgroupBeEnabled) {
            $query = "SELECT DISTINCT(hgr.host_host_id)
                FROM hostgroup_relation hgr
                JOIN hostgroup ON hostgroup.hg_id = hgr.hostgroup_hg_id
                WHERE hostgroup.hg_activate = '1'
                AND hgr.hostgroup_hg_id IN (" . implode(', ', array_keys($bindedParams)) . ')';
        } else {
            $query = 'SELECT DISTINCT(hgr.host_host_id) FROM hostgroup_relation hgr
                WHERE hgr.hostgroup_hg_id IN (' . implode(', ', array_keys($bindedParams)) . ')';
        }

        $stmt = $this->db->prepare($query);
        foreach ($bindedParams as $bindedParam => $bindedValue) {
            $stmt->bindValue($bindedParam, $bindedValue, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return ids of hosts linked to services
     *
     * @param int[] $serviceIds
     * @param bool $shoudlServiceBeEnabled
     *
     * @throws PDOException
     * @return int[]
     */
    public function findHostsForConfigChangeFlagFromServiceIds(
        array $serviceIds,
        bool $shoudlServiceBeEnabled = true
    ): array {
        if ($serviceIds === []) {
            return [];
        }

        $bindedParams = [];
        foreach ($serviceIds as $key => $serviceId) {
            $bindedParams[':service_id_' . $key] = $serviceId;
        }

        if ($shoudlServiceBeEnabled) {
            $query = "SELECT DISTINCT(hsr.host_host_id)
                FROM host_service_relation hsr
                JOIN service ON service.service_id = hsr.service_service_id
                WHERE service.service_activate = '1' AND hsr.service_service_id IN ("
                . implode(', ', array_keys($bindedParams)) . ')';
        } else {
            $query = 'SELECT DISTINCT(hsr.host_host_id)
                FROM host_service_relation hsr
                WHERE hsr.service_service_id IN (' . implode(', ', array_keys($bindedParams)) . ')';
        }

        $stmt = $this->db->prepare($query);
        foreach ($bindedParams as $bindedParam => $bindedValue) {
            $stmt->bindValue($bindedParam, $bindedValue, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return ids of services linked to templates recursively
     *
     * @param int[] $serviceTemplateIds
     * @throws Exception
     * @return int[]
     */
    private function findServicesForConfigChangeFlagFromServiceTemplateIds(array $serviceTemplateIds): array
    {
        if ($serviceTemplateIds === []) {
            return [];
        }

        $bindedParams = [];
        foreach ($serviceTemplateIds as $key => $serviceTemplateId) {
            $bindedParams[':servicetemplate_id_' . $key] = $serviceTemplateId;
        }

        $query = "SELECT service_id, service_register FROM service
            WHERE service.service_activate = '1'
            AND service_template_model_stm_id IN (" . implode(', ', array_keys($bindedParams)) . ')';

        $stmt = $this->db->prepare($query);
        foreach ($bindedParams as $bindedParam => $bindedValue) {
            $stmt->bindValue($bindedParam, $bindedValue, PDO::PARAM_INT);
        }
        $stmt->execute();

        $serviceIds = [];
        $serviceTemplateIds2 = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if ($value['service_register'] === '0') {
                $serviceTemplateIds2[] = $value['service_id'];
            } else {
                $serviceIds[] = $value['service_id'];
            }
        }

        return array_merge(
            $serviceIds,
            $this->findServicesForConfigChangeFlagFromServiceTemplateIds($serviceTemplateIds2)
        );
    }

    /**
     * Return ids of hosts linked to service
     *
     * @param int $servicegroupId
     * @param bool $shouldServicegroupBeEnabled (default true)
     * @throws Exception
     * @return int[]
     */
    public function findHostsForConfigChangeFlagFromServiceGroupId(
        int $servicegroupId,
        bool $shouldServicegroupBeEnabled = true
    ): array {
        $query = "SELECT sgr.*, service.service_register
            FROM servicegroup_relation sgr
            JOIN servicegroup ON servicegroup.sg_id = sgr.servicegroup_sg_id
            JOIN service ON service.service_id = sgr.service_service_id
            WHERE service.service_activate = '1' AND sgr.servicegroup_sg_id = :servicegroup_id"
            . ($shouldServicegroupBeEnabled ? " AND servicegroup.sg_activate = '1'" : '');

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':servicegroup_id', $servicegroupId, PDO::PARAM_INT);
        $stmt->execute();

        $hostIds = [];
        $hostgroupIds = [];
        $serviceTemplateIds = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if ($value['service_register'] === '0') {
                $serviceTemplateIds[] = $value['service_service_id'];
            } elseif ($value['hostgroup_hg_id'] !== null) {
                $hostgroupIds[] = $value['hostgroup_hg_id'];
            } else {
                $hostIds[] = $value['host_host_id'];
            }
        }

        $serviceIds = $this->findServicesForConfigChangeFlagFromServiceTemplateIds($serviceTemplateIds);

        return array_merge(
            $hostIds,
            $this->findHostsForConfigChangeFlagFromHostGroupIds($hostgroupIds),
            $this->findHostsForConfigChangeFlagFromServiceIds($serviceIds)
        );
    }

    /**
     * Return ids of pollers linked to hosts
     *
     * @param int[] $hostIds
     * @param bool $shouldHostBeEnabled (default true)
     * @throws Exception
     * @return int[]
     */
    public function findPollersForConfigChangeFlagFromHostIds(array $hostIds, bool $shouldHostBeEnabled = true): array
    {
        if ($hostIds === []) {
            return [];
        }

        $bindedParams = [];
        foreach ($hostIds as $key => $hostId) {
            $bindedParams[':host_id_' . $key] = $hostId;
        }

        if ($shouldHostBeEnabled) {
            $query = "SELECT DISTINCT(phr.nagios_server_id)
            FROM ns_host_relation phr
            JOIN host ON host.host_id = phr.host_host_id
            WHERE host.host_activate = '1' AND phr.host_host_id IN (" . implode(', ', array_keys($bindedParams)) . ')';
        } else {
            $query = 'SELECT DISTINCT(phr.nagios_server_id) FROM ns_host_relation phr
            WHERE phr.host_host_id IN (' . implode(', ', array_keys($bindedParams)) . ')';
        }

        $stmt = $this->db->prepare($query);
        foreach ($bindedParams as $bindedParam => $bindedValue) {
            $stmt->bindValue($bindedParam, $bindedValue, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Set 'updated' flag to '1' for all listed poller ids
     *
     * @param int[] $pollerIds
     * @throws Exception
     */
    private function definePollersToUpdated(array $pollerIds): void
    {
        if ($pollerIds === []) {
            return;
        }

        $bindedParams = [];
        foreach ($pollerIds as $key => $pollerId) {
            $bindedParams[':poller_id_' . $key] = $pollerId;
        }
        $query = "UPDATE nagios_server SET updated = '1' WHERE id IN ("
            . implode(', ', array_keys($bindedParams)) . ')';
        $stmt = $this->db->prepare($query);
        foreach ($bindedParams as $bindedParam => $bindedValue) {
            $stmt->bindValue($bindedParam, $bindedValue, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * Set relevent pollers as updated
     *
     * @param string $resourceType
     * @param int $resourceId
     * @param int[] $previousPollers
     * @param bool $shouldResourceBeEnabled (default true)
     * @throws Exception
     */
    public function signalConfigurationChange(
        string $resourceType,
        int $resourceId,
        array $previousPollers = [],
        bool $shouldResourceBeEnabled = true
    ): void {
        $hostIds = [];
        switch ($resourceType) {
            case self::RESOURCE_TYPE_HOST:
                $hostIds[] = $resourceId;
                break;
            case self::RESOURCE_TYPE_HOSTGROUP:
                $hostIds = array_merge(
                    $hostIds,
                    $this->findHostsForConfigChangeFlagFromHostGroupIds([$resourceId], $shouldResourceBeEnabled)
                );
                break;
            case self::RESOURCE_TYPE_SERVICE:
                $hostIds = array_merge(
                    $hostIds,
                    $this->findHostsForConfigChangeFlagFromServiceIds([$resourceId], $shouldResourceBeEnabled)
                );
                break;
            case self::RESOURCE_TYPE_SERVICEGROUP:
                $hostIds = array_merge(
                    $hostIds,
                    $this->findHostsForConfigChangeFlagFromServiceGroupId($resourceId, $shouldResourceBeEnabled)
                );
                break;
            default:
                throw new CentreonClapiException(self::UNKNOWN_RESOURCE_TYPE . ':' . $resourceType);
                break;
        }
        $pollerIds = $this->findPollersForConfigChangeFlagFromHostIds(
            $hostIds,
            $resourceType === self::RESOURCE_TYPE_HOST ? $shouldResourceBeEnabled : true
        );

        $this->definePollersToUpdated(array_merge($pollerIds, $previousPollers));
    }
}
