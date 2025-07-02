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

require_once __DIR__ . '/../List.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsConnectorService
 */
class CentreonWidgetParamsConnectorService extends CentreonWidgetParamsList
{
    /** @var HTML_QuickForm_element */
    public $element;

    /**
     * CentreonWidgetParamsConnectorService constructor
     *
     * @param $db
     * @param $quickform
     * @param $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
        $this->trigger = true;
    }

    /**
     * @param $params
     *
     * @throws HTML_QuickForm_Error
     * @throws PDOException
     * @return void
     */
    public function init($params): void
    {
        parent::init($params);
        if (isset($this->quickform)) {
            $tab = $this->getListValues($params['parameter_id']);
            $triggerSource = './include/home/customViews/triggers/loadServiceFromHost.php';
            $this->element = $this->quickform->addElement(
                'select',
                'param_trigger_' . $params['parameter_id'],
                'Host',
                $tab,
                ['onchange' => 'javascript:loadFromTrigger("' . $triggerSource . '", '
                    . $params['parameter_id'] . ', this.value);']
            );
            $userPref = $this->getUserPreferences($params);
            $svcTab = [];
            if (isset($userPref)) {
                [$hostId, $serviceId] = explode('-', $userPref);
                $svcTab = $this->getServiceIds($hostId);
            }
            $this->quickform->addElement(
                'select',
                'param_' . $params['parameter_id'],
                $params['parameter_name'],
                $svcTab
            );
        }
    }

    /**
     * Get service id from host id
     *
     * @param int $hostId
     *
     * @throws PDOException
     * @return array
     */
    protected function getServiceIds($hostId)
    {
        $aclString = $this->acl->queryBuilder(
            'AND',
            's.service_id',
            $this->acl->getServicesString('ID', $this->monitoringDb)
        );
        $sql = 'SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr
        		WHERE hsr.host_host_id = ' . $this->db->escape($hostId) . '
        		AND hsr.service_service_id = s.service_id ';
        $sql .= $aclString;
        $sql .= ' UNION ';
        $sql .= ' SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr, hostgroup_relation hgr
        		WHERE hsr.hostgroup_hg_id = hgr.hostgroup_hg_id
        		AND hgr.host_host_id = ' . $this->db->escape($hostId) . '
        		AND hsr.service_service_id = s.service_id ';
        $sql .= $aclString;
        $sql .= ' ORDER BY service_description ';
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            // For meta services, use display_name column instead of service_description
            $serviceDescription = (preg_match('/meta_/', $row['service_description']))
                ? $row['display_name'] : $row['service_description'];
            $tab[$hostId . '-' . $row['service_id']] = $serviceDescription;
        }

        return $tab;
    }

    /**
     * @param $paramId
     *
     * @throws PDOException
     * @return mixed|null[]
     */
    public function getListValues($paramId)
    {
        static $tab;

        if (! isset($tab)) {
            $aclString = $this->acl->queryBuilder(
                'AND',
                'host_id',
                $this->acl->getHostsString(
                    'ID',
                    $this->monitoringDb
                )
            );
            $query = "SELECT host_id, host_name
                      FROM host
            	      WHERE host_activate = '1'
            	      AND host_register = '1' ";
            $query .= $aclString;
            // Add virtual host 'Meta' in the list if it exists and if ACL allows it
            $query .= "UNION SELECT host_id, 'Meta'
                       FROM host
                       WHERE host_register = '2'
                       AND host_name = '_Module_Meta' ";
            $query .= $aclString;
            $query .= ' ORDER BY host_name';
            $res = $this->db->query($query);
            $tab = [null => null];
            while ($row = $res->fetchRow()) {
                $tab[$row['host_id']] = $row['host_name'];
            }
        }

        return $tab;
    }

    /**
     * Set Value
     *
     * @param array $params
     *
     * @throws HTML_QuickForm_Error
     * @throws PDOException
     * @return void
     */
    public function setValue($params): void
    {
        $userPref = $this->getUserPreferences($params);
        if (isset($userPref)) {
            [$hostId, $serviceId] = explode('-', $userPref);
            $this->quickform->setDefaults(['param_trigger_' . $params['parameter_id'] => $hostId]);
            $this->quickform->setDefaults(['param_' . $params['parameter_id'] => $userPref]);
        }
    }
}
