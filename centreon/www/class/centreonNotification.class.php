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

/**
 * Class
 *
 * @class CentreonNotification
 */
class CentreonNotification
{
    public const HOST = 0;
    public const SVC = 1;
    public const HOST_ESC = 2;
    public const SVC_ESC = 3;

    /** @var CentreonDB */
    protected $db;

    /** @var array */
    protected $svcTpl = [];

    /** @var array */
    protected $svcNotifType = [];

    /** @var array */
    protected $svcBreak = [1 => false, 2 => false];

    /** @var array */
    protected $hostNotifType = [];

    /** @var array */
    protected $notifiedHosts = [];

    /** @var array */
    protected $hostBreak = [1 => false, 2 => false];

    /**
     * CentreonNotification constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get list of contact
     *
     * @throws PDOException
     * @return array
     */
    public function getList()
    {
        $sql = 'SELECT contact_id, contact_alias FROM contact ORDER BY contact_name';
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            $tab[$row['contact_id']] = $row['contact_alias'];
        }

        return $tab;
    }

    /**
     * Checks if notification is enabled
     *
     * @param int $contactId
     *
     * @throws PDOException
     * @return bool true if notification is enabled, false otherwise
     */
    protected function isNotificationEnabled($contactId)
    {
        $sql = 'SELECT contact_enable_notifications FROM contact WHERE contact_id = ' . $contactId;
        $res = $this->db->query($sql);
        if ($res->rowCount()) {
            $row = $res->fetchRow();
            if ($row['contact_enable_notifications']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get contact groups
     *
     * @param int $contactGroupId
     *
     * @throws PDOException
     * @return array
     */
    public function getContactGroupsById($contactGroupId)
    {
        $sql = 'SELECT cg_id, cg_name
        		FROM contactgroup cg
        		WHERE cg.cg_id = ' . $this->db->escape($contactGroupId);
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            $tab[$row['cg_id']] = $row['cg_name'];
        }

        return $tab;
    }

    /**
     * Get contact groups
     *
     * @param int $contactId
     *
     * @throws PDOException
     * @return array
     */
    public function getContactGroups($contactId)
    {
        $sql = 'SELECT cg_id, cg_name
        		FROM contactgroup cg, contactgroup_contact_relation ccr
        		WHERE cg.cg_id = ccr.contactgroup_cg_id
        		AND ccr.contact_contact_id = ' . $contactId;
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            $tab[$row['cg_id']] = $row['cg_name'];
        }

        return $tab;
    }

    /**
     * Get notifications
     *
     * @param int $notifType 0 for Hosts, 1 for Services, 2 for Host Escalations, 3 for Service Escalations
     * @param int $contactId
     *
     * @throws PDOException
     * @return array
     */
    public function getNotifications($notifType, $contactId)
    {
        $contactId = $this->db->escape($contactId);
        if (false === $this->isNotificationEnabled($contactId)) {
            return [];
        }
        $contactgroups = $this->getContactGroups($contactId);

        if ($notifType == self::HOST) {
            $resources = $this->getHostNotifications($contactId, $contactgroups);
        } elseif ($notifType == self::SVC) {
            $resources = $this->getServiceNotifications($contactId, $contactgroups);
        } elseif ($notifType == self::HOST_ESC || $notifType == self::SVC_ESC) {
            $resources = $this->getEscalationNotifications($notifType, $contactgroups);
        }

        return $resources;
    }

    /**
     * Get notifications
     *
     * @param int $notifType 0 for Hosts, 1 for Services, 2 for Host Escalations, 3 for Service Escalations
     * @param int $contactgroupId
     *
     * @throws PDOException
     * @return array
     */
    public function getNotificationsContactGroup($notifType, $contactgroupId)
    {
        /*if (false === $this->isNotificationEnabled($contactId)) {
            return array();
        }*/
        $contactgroups = $this->getContactGroupsById($contactgroupId);
        if ($notifType == self::HOST) {
            $resources = $this->getHostNotifications(-1, $contactgroups);
        } elseif ($notifType == self::SVC) {
            $resources = $this->getServiceNotifications(-1, $contactgroups);
        } elseif ($notifType == self::HOST_ESC || $notifType == self::SVC_ESC) {
            $resources = $this->getEscalationNotifications($notifType, $contactgroups);
        }

        return $resources;
    }

    /**
     * Get host escalatiosn
     *
     * @param array $escalations
     *
     * @throws PDOException
     * @return array
     */
    protected function getHostEscalations($escalations)
    {
        $escalations = implode(',', array_keys($escalations));
        $sql = 'SELECT h.host_id, h.host_name
        		FROM escalation_host_relation ehr, host h
        		WHERE h.host_id = ehr.host_host_id
        		AND ehr.escalation_esc_id IN (' . $escalations . ')
        		UNION
        		SELECT h.host_id, h.host_name
        		FROM escalation_hostgroup_relation ehr, hostgroup_relation hgr, host h
        		WHERE ehr.hostgroup_hg_id = hgr.hostgroup_hg_id
        		AND hgr.host_host_id = h.host_id
        		AND ehr.escalation_esc_id IN (' . $escalations . ')';
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            $tab[$row['host_id']] = $row['host_name'];
        }

        return $tab;
    }

    /**
     * Get service escalations
     *
     * @param array $escalations
     *
     * @throws PDOException
     * @return array
     */
    protected function getServiceEscalations($escalations)
    {
        $escalationsList = implode('', array_keys($escalations));
        $sql = 'SELECT h.host_id, h.host_name, s.service_id, s.service_description
        		FROM escalation_service_relation esr, host h, service s
        		WHERE h.host_id = esr.host_host_id
        		AND esr.service_service_id = s.service_id
        		AND esr.escalation_esc_id IN (' . $escalationsList . ')
        		UNION
        		SELECT h.host_id, h.host_name, s.service_id, s.service_description
        		FROM escalation_servicegroup_relation esr, servicegroup_relation sgr, host h, service s
        		WHERE esr.servicegroup_sg_id = sgr.servicegroup_sg_id
        		AND sgr.host_host_id = h.host_id
        		AND sgr.service_service_id = s.service_id
        		AND esr.escalation_esc_id IN (' . $escalationsList . ')';
        $res = $this->db->query($sql);
        $tab = [];
        while ($row = $res->fetchRow()) {
            if (! isset($tab[$row['host_id']])) {
                $tab[$row['host_id']] = [];
            }
            $tab[$row['host_id']][$row['service_id']]['host_name'] = $row['host_name'];
            $tab[$row['host_id']][$row['service_id']]['service_description'] = $row['service_description'];
        }

        return $tab;
    }

    /**
     * Get escalation notifications
     *
     * @param $notifType
     * @param array $contactgroups
     *
     * @throws PDOException
     * @return array
     */
    protected function getEscalationNotifications($notifType, $contactgroups)
    {
        if (! count($contactgroups)) {
            return [];
        }
        $sql = 'SELECT ecr.escalation_esc_id, e.esc_name
        		FROM escalation_contactgroup_relation ecr, escalation e
        		WHERE e.esc_id = ecr.escalation_esc_id
        		AND ecr.contactgroup_cg_id IN (' . implode(',', array_keys($contactgroups)) . ')';
        $res = $this->db->query($sql);
        $escTab = [];
        while ($row = $res->fetchRow()) {
            $escTab[$row['escalation_esc_id']] = $row['esc_name'];
        }
        if ($escTab === []) {
            return [];
        }
        if ($notifType == self::HOST_ESC) {
            return $this->getHostEscalations($escTab);
        }

        return $this->getServiceEscalations($escTab);
    }

    /**
     * Get Host Notifications
     *
     * @param int $contactId
     * @param array $contactgroups
     *
     * @throws PDOException
     * @return array
     */
    protected function getHostNotifications($contactId, $contactgroups)
    {
        $sql = 'SELECT host_id, host_name, host_register, 1 as notif_type
        		FROM contact_host_relation chr, host h
        		WHERE chr.contact_id = ' . $contactId . '
        		AND chr.host_host_id = h.host_id ';
        if (count($contactgroups)) {
            $sql .= ' UNION
        			  SELECT host_id, host_name, host_register, 2 as notif_type
        			  FROM contactgroup_host_relation chr, host h
        			  WHERE chr.contactgroup_cg_id IN (' . implode(',', array_keys($contactgroups)) . ')
        			  AND chr.host_host_id = h.host_id ';
        }
        $res = $this->db->query($sql);
        $this->notifiedHosts = [];
        $templates = [];
        while ($row = $res->fetchRow()) {
            if ($row['host_register'] == 1) {
                $this->notifiedHosts[$row['host_id']] = $row['host_name'];
            } else {
                $templates[$row['host_id']] = $row['host_name'];
                $this->hostNotifType[$row['host_id']] = $row['notif_type'];
            }
        }
        unset($res);

        if ($this->notifiedHosts !== []) {
            $sql2 = 'SELECT host_id, host_name
                FROM host
                WHERE host_id NOT IN (' . implode(',', array_keys($this->notifiedHosts)) . ") AND host_register = '1'";
        } else {
            $sql2 = "SELECT host_id, host_name FROM host WHERE host_register = '1'";
        }
        $res2 = $this->db->query(trim($sql2));
        while ($row = $res2->fetchRow()) {
            $this->hostBreak = [1 => false, 2 => false];
            if ($this->getHostTemplateNotifications($row['host_id'], $templates) === true) {
                $this->notifiedHosts[$row['host_id']] = $row['host_name'];
            }
        }

        return $this->notifiedHosts;
    }

    /**
     * Recursive method
     *
     * @param int $hostId
     * @param array $templates
     *
     * @throws PDOException
     * @return bool
     */
    protected function getHostTemplateNotifications($hostId, $templates)
    {
        $sql = 'SELECT htr.host_tpl_id, ctr.contact_id, ctr2.contactgroup_cg_id
        		FROM host_template_relation htr
        		LEFT JOIN contact_host_relation ctr ON htr.host_host_id = ctr.host_host_id
        		LEFT JOIN contactgroup_host_relation ctr2 ON htr.host_host_id = ctr2.host_host_id
        		WHERE htr.host_host_id = :host_id 
        		ORDER BY `order`';
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
        $statement->execute();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($row['contact_id']) {
                $this->hostBreak[1] = true;
            }
            if ($row['contactgroup_cg_id']) {
                $this->hostBreak[2] = true;
            }
            if (isset($templates[$row['host_tpl_id']])) {
                if ($this->hostNotifType[$row['host_tpl_id']] == 1 && $this->hostBreak[1] == true) {
                    return false;
                }

                return ! ($this->hostNotifType[$row['host_tpl_id']] == 2 && $this->hostBreak[2] == true);
            }

            return $this->getHostTemplateNotifications($row['host_tpl_id'], $templates);
        }

        return false;
    }

    /**
     * Get Service notifications
     *
     * @param int $contactId
     * @param array $contactGroups
     *
     * @throws PDOException
     * @return array
     */
    protected function getServiceNotifications($contactId, $contactGroups)
    {
        $sql = 'SELECT h.host_id, h.host_name, s.service_id, s.service_description, s.service_register, 1 as notif_type
        		FROM contact_service_relation csr, service s
        		LEFT JOIN host_service_relation hsr ON hsr.service_service_id = s.service_id
        		LEFT JOIN host h ON h.host_id = hsr.host_host_id
        		WHERE csr.contact_id = ' . $contactId . "
                AND csr.service_service_id = s.service_id
                AND s.service_use_only_contacts_from_host != '1'
        		UNION
                SELECT h.host_id, h.host_name, s.service_id, s.service_description, s.service_register, 1 as notif_type
        		FROM contact_service_relation csr, service s, host h, host_service_relation hsr, hostgroup_relation hgr
        		WHERE csr.contact_id = " . $contactId . "
        		AND csr.service_service_id = s.service_id
        		AND s.service_id = hsr.service_service_id
        		AND hsr.hostgroup_hg_id = hgr.hostgroup_hg_id
                AND hgr.host_host_id = h.host_id
                AND s.service_use_only_contacts_from_host != '1'";

        if (count($contactGroups)) {
            $contactGroups = implode(',', array_keys($contactGroups));
            $sql .= ' UNION
        			  SELECT h.host_id, h.host_name, s.service_id, s.service_description, s.service_register,
                      2 as notif_type
        			  FROM contactgroup_service_relation csr, service s
        			  LEFT JOIN host_service_relation hsr ON hsr.service_service_id = s.service_id
        			  LEFT JOIN host h ON h.host_id = hsr.host_host_id
        			  WHERE csr.contactgroup_cg_id IN (' . $contactGroups . ")
                      AND csr.service_service_id = s.service_id
                      AND s.service_use_only_contacts_from_host != '1'
        			  UNION
        			  SELECT h.host_id, h.host_name, s.service_id, s.service_description, s.service_register,
                      2 as notif_type
        			  FROM contactgroup_service_relation csr, service s, host h, host_service_relation hsr,
                      hostgroup_relation hgr
        			  WHERE csr.contactgroup_cg_id IN (" . $contactGroups . ")
        			  AND csr.service_service_id = s.service_id
        			  AND s.service_id = hsr.service_service_id
        			  AND hsr.hostgroup_hg_id = hgr.hostgroup_hg_id
                      AND hgr.host_host_id = h.host_id
                      AND s.service_use_only_contacts_from_host != '1'";
        }
        $res = $this->db->query($sql);
        $svcTab = [];
        $svcList = [];
        $templates = [];
        while ($row = $res->fetchRow()) {
            $svcList[$row['service_id']] = $row['service_id'];
            if ($row['service_register'] == 1) {
                if (! isset($svcTab[$row['host_id']])) {
                    $svcTab[$row['host_id']] = [];
                }
                $svcTab[$row['host_id']][$row['service_id']] = [];
                $svcTab[$row['host_id']][$row['service_id']]['host_name'] = $row['host_name'];
                $svcTab[$row['host_id']][$row['service_id']]['service_description'] = $row['service_description'];
            } else {
                $templates[$row['service_id']] = $row['service_description'];
                $this->svcNotifType[$row['service_id']] = $row['notif_type'];
            }
        }
        unset($res);

        if (count($this->notifiedHosts)) {
            $sql = 'SELECT h.host_id, h.host_name, s.service_id, s.service_description '
                . 'FROM service s, host h, host_service_relation hsr '
                . 'WHERE hsr.service_service_id = s.service_id '
                . 'AND hsr.host_host_id = h.host_id '
                . 'AND h.host_id IN (' . implode(',', array_keys($this->notifiedHosts)) . ')';
            $res = $this->db->query($sql);
            while ($row = $res->fetchRow()) {
                $svcTab[$row['host_id']][$row['service_id']] = [];
                $svcTab[$row['host_id']][$row['service_id']]['host_name'] = $row['host_name'];
                $svcTab[$row['host_id']][$row['service_id']]['service_description'] = $row['service_description'];
            }
            unset($res);
        }

        if ($svcTab !== []) {
            $tab = [];
            foreach ($svcTab as $tmp) {
                $tab = array_merge(array_keys($tmp), $tab);
            }
            $sql2 = 'SELECT service_id, service_description
            		 FROM service
            		 WHERE service_id NOT IN (' . implode(',', $tab) . ") AND service_register = '1'";
        } else {
            $sql2 = "SELECT service_id, service_description
            		 FROM service
            		 WHERE service_register = '1'";
        }

        $res2 = $this->db->query($sql2);

        $sql3 = 'SELECT h.host_id, h.host_name, hsr.service_service_id as service_id
                    		 FROM host h, host_service_relation hsr
                    		 WHERE h.host_id = hsr.host_host_id
                    		 UNION
                    		 SELECT h.host_id, h.host_name, hsr.service_service_id
                    		 FROM host h, host_service_relation hsr, hostgroup_relation hgr
                    		 WHERE h.host_id = hgr.host_host_id
                    		 AND hgr.hostgroup_hg_id = hsr.hostgroup_hg_id';
        $res3 = $this->db->query($sql3);
        while ($row3 = $res3->fetchRow()) {
            $list[$row3['service_id']] = $row3;
        }

        while ($row = $res2->fetchRow()) {
            $this->svcBreak = [1 => false, 2 => false];
            $flag = false;
            if ($this->getServiceTemplateNotifications($row['service_id'], $templates) === true) {
                if (array_key_exists($row['service_id'], $list)) {
                    $row3 = $list[$row['service_id']];
                    if (! isset($svcTab[$row3['host_id']])) {
                        $svcTab[$row3['host_id']] = [];
                    }
                    $svcTab[$row3['host_id']][$row['service_id']] = [];
                    $svcTab[$row3['host_id']][$row['service_id']]['host_name'] = $row3['host_name'];
                    $svcTab[$row3['host_id']][$row['service_id']]['service_description'] = $row['service_description'];
                }
            }
        }

        return $svcTab;
    }

    /**
     * Recursive method
     *
     * @param int $serviceId
     * @param array $templates
     *
     * @throws PDOException
     * @return bool
     */
    protected function getServiceTemplateNotifications($serviceId, $templates)
    {
        $tplId = 0;
        if (! isset($this->svcTpl[$serviceId])) {
            $sql = 'SELECT s.service_template_model_stm_id, csr.contact_id, csr2.contactgroup_cg_id
        			FROM service s
        			LEFT JOIN contact_service_relation csr ON csr.service_service_id = s.service_id
        			LEFT JOIN contactgroup_service_relation csr2 ON csr2.service_service_id = s.service_id
        			WHERE service_id = ' . $this->db->escape($serviceId);
            $res = $this->db->query($sql);
            $row = $res->fetchRow();
            $tplId = $row['service_template_model_stm_id'];
        } else {
            $tplId = $this->svcTpl[$serviceId];
        }
        if ($row['contact_id']) {
            $this->svcBreak[1] = true;
        }
        if ($row['contactgroup_cg_id']) {
            $this->svcBreak[2] = true;
        }
        if (isset($templates[$tplId]) && $templates[$tplId]) {
            if ($this->svcNotifType[$tplId] == 1 && $this->svcBreak[1] == true) {
                return false;
            }

            return ! ($this->svcNotifType[$tplId] == 2 && $this->svcBreak[2] == true);
        }
        if ($tplId) {
            return $this->getServiceTemplateNotifications($tplId, $templates);
        }

        return false;
    }
}
