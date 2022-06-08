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

class Centreon_OpenTickets_Rule
{
    protected $_db;
    protected $_provider = null;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($db)
    {
        $this->_db = $db;
    }

    /**
     * Sets the activate field
     *
     * @param array $select
     * @param int $val
     * @return void
     */
    protected function _setActivate($select, $val)
    {
        $query = "UPDATE mod_open_tickets_rule SET `activate` = '" . $val . "' WHERE rule_id IN (";
        $ruleList = "";
        $ruleListAppend = "";
        if (is_array($select)) {
            foreach ($select as $key => $value) {
                $ruleList .= $ruleListAppend . "'" . $key . "'";
                $ruleListAppend = ', ';
            }
        }
        if (isset($_REQUEST['rule_id'])) {
            $ruleList .= $ruleListAppend . "'" . $_REQUEST['rule_id'] . "'";
        }
        $query .= $ruleList;
        $query .= ")";
        if (!$ruleList) {
            return null;
        }
        $this->_db->query($query);
    }

    public function getAliasAndProviderId($rule_id)
    {
        $result = [];
        if (is_null($rule_id)) {
            return $result;
        }

        $dbResult = $this->_db->query(
            "SELECT alias, provider_id FROM mod_open_tickets_rule WHERE rule_id = '" . $rule_id . "' LIMIT 1"
        );
        if (($row = $dbResult->fetch())) {
            $result['alias'] = $row['alias'];
            $result['provider_id'] = $row['provider_id'];
        }

        return $result;
    }

    protected function loadProvider($rule_id, $provider_id, $widget_id, $uniq_id = null)
    {
        global $centreon_path, $register_providers;

        if (!is_null($this->_provider)) {
            return ;
        }

        $centreon_open_tickets_path = $centreon_path . 'www/modules/centreon-open-tickets/';
        require_once $centreon_open_tickets_path . 'providers/register.php';
        require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

        $provider_name = null;
        foreach ($register_providers as $name => $id) {
            if ($id == $provider_id) {
                $provider_name = $name;
                break;
            }
        }

        if (is_null($provider_name)
            || !file_exists(
                $centreon_open_tickets_path .
                'providers/' .
                $provider_name . '/' .
                $provider_name .
                'Provider.class.php'
            )
        ) {
            throw new Exception(sprintf('Cannot find provider'));
        }

        include_once $centreon_open_tickets_path .
            'providers/' .
            $provider_name . '/' .
            $provider_name .
            'Provider.class.php';
        $classname = $provider_name . 'Provider';
        $this->_provider = new $classname(
            $this,
            $centreon_path,
            $centreon_open_tickets_path,
            $rule_id,
            null,
            $provider_id
        );
        $this->_provider->setWidgetId($widget_id);
        $this->_provider->setUniqId($uniq_id);
    }

    public function getUrl($rule_id, $ticket_id, $data, $widget_id)
    {
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id'], $widget_id);
        return $this->_provider->getUrl($ticket_id, $data);
    }

    public function getMacroNames($rule_id, $widget_id)
    {
        $result = array(
            'ticket_id' => null
        );

        if (!$rule_id) {
            return $result;
        }

        $infos = $this->getAliasAndProviderId($rule_id);

        if ($infos) {
            $this->loadProvider($rule_id, $infos['provider_id'], $widget_id);
            $result['ticket_id'] = $this->_provider->getMacroTicketId();
        }

        return $result;
    }

    public function loadSelection($db_storage, $cmd, $selection)
    {
        global $centreon_bg;

        if (is_null($db_storage)) {
            $db_storage = new CentreonDB('centstorage');
        }

        $selected_values = explode(',', $selection);
        $selected = array('host_selected' => array(), 'service_selected' => array());

        if ($cmd == 3) {
            $selected_str = '';
            $selected_str2 = '';
            $selected_str_append = '';
            foreach ($selected_values as $value) {
                $str = explode(';', $value);
                $selected_str .= $selected_str_append .
                    'services.host_id = ' . $str[0] .
                    ' AND services.service_id = ' . $str[1];
                $selected_str2 .= $selected_str_append . 'host_id = ' . $str[0] . ' AND service_id = ' . $str[1];
                $selected_str_append = ' OR ';
            }

            $query = "SELECT
                    services.*,
                    hosts.address,
                    hosts.state AS host_state,
                    hosts.host_id,
                    hosts.name AS host_name,
                    hosts.instance_id
                FROM services, hosts";
            $query_where = " WHERE (" . $selected_str . ') AND services.host_id = hosts.host_id';
            if (!$centreon_bg->is_admin) {
                $query_where .= " AND EXISTS(
                    SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (" . $centreon_bg->grouplistStr . "
                    ) AND hosts.host_id = centreon_acl.host_id AND services.service_id = centreon_acl.service_id
                )";
            }

            $dbResult = $db_storage->query($query . $query_where);

            $dbResult_graph = $db_storage->query(
                "SELECT host_id, service_id, COUNT(*) AS num_metrics 
                FROM index_data, metrics 
                WHERE (" . $selected_str2 . ")
                AND index_data.id = metrics.index_id 
                GROUP BY host_id, service_id"
            );
            $datas_graph = array();
            while (($row = $dbResult_graph->fetch())) {
                $datas_graph[$row['host_id'] . '.' . $row['service_id']] = $row['num_metrics'];
            }

            while (($row = $dbResult->fetch())) {
                $row['service_state'] = $row['state'];
                $row['state_str'] = $this->getServiceStateStr($row['state']);
                $row['last_state_change_duration'] = CentreonDuration::toString(
                    time() - $row['last_state_change']
                );
                $row['last_hard_state_change_duration'] = CentreonDuration::toString(
                    time() - $row['last_hard_state_change']
                );
                $row['num_metrics'] = isset(
                    $datas_graph[$row['host_id'] . '.' . $row['service_id']]
                ) ? $datas_graph[$row['host_id'] . '.' . $row['service_id']] : 0;
                $selected['service_selected'][] = $row;
            }
        } elseif ($cmd == 4) {
            $hosts_selected_str = '';
            $hosts_selected_str_append = '';
            foreach ($selected_values as $value) {
                $str = explode(';', $value);
                $hosts_selected_str .= $hosts_selected_str_append . $str[0];
                $hosts_selected_str_append = ', ';
            }

            $query = "SELECT * FROM hosts";
            $query_where = " WHERE host_id IN (" . $hosts_selected_str . ")";
            if (!$centreon_bg->is_admin) {
                $query_where .= " AND EXISTS(
                    SELECT * FROM centreon_acl
                    WHERE centreon_acl.group_id IN (" . $centreon_bg->grouplistStr . ")
                    AND hosts.host_id = centreon_acl.host_id
                )";
            }

            $dbResult = $db_storage->query($query . $query_where);
            while (($row = $dbResult->fetch())) {
                $row['host_state'] = $row['state'];
                $row['state_str'] = $this->getHostStateStr($row['state']);
                $row['last_state_change_duration'] = CentreonDuration::toString(
                    time() - $row['last_state_change']
                );
                $row['last_hard_state_change_duration'] = CentreonDuration::toString(
                    time() - $row['last_hard_state_change']
                );
                $selected['host_selected'][] = $row;
            }
        }

        return $selected;
    }

    public function getFormatPopupProvider($rule_id, $args, $widget_id, $uniq_id, $cmd, $selection)
    {
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id'], $widget_id, $uniq_id);

        $selected = $this->loadSelection(null, $cmd, $selection);
        $args['host_selected'] = $selected['host_selected'];
        $args['service_selected'] = $selected['service_selected'];

        return $this->_provider->getFormatPopup($args);
    }

    public function save($rule_id, $datas)
    {
        $this->_db->beginTransaction();

        $nrule_id = $rule_id;
        $dbResult = $this->_db->query(
            "SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" .
            $this->_db->escape($rule_id) . "' LIMIT 1"
        );
        if (!($row = $dbResult->fetch())) {
            $this->_db->query(
                "INSERT INTO mod_open_tickets_rule (`alias`, `provider_id`, `activate`) VALUES (
                    '" . $this->_db->escape($datas['rule_alias']) . "',
                    '" . $this->_db->escape($datas['provider_id']) . "', 
                    '1'
                )"
            );
            $nrule_id = $this->_db->lastinsertId('mod_open_tickets_rule');
        } else {
            $this->_db->query(
                "UPDATE mod_open_tickets_rule SET 
                    `alias` = '" . $this->_db->escape($datas['rule_alias']) ."',
                    `provider_id` = '" . $datas['provider_id'] . "'
                WHERE rule_id = '" . $this->_db->escape($rule_id) . "'"
            );
            $this->_db->query(
                "DELETE FROM mod_open_tickets_form_clone WHERE rule_id = '" . $this->_db->escape($rule_id) . "'"
            );
            $this->_db->query(
                "DELETE FROM mod_open_tickets_form_value WHERE rule_id = '" . $this->_db->escape($rule_id) . "'"
            );
        }

        foreach ($datas['simple'] as $uniq_id => $value) {
            $this->_db->query(
                "INSERT INTO mod_open_tickets_form_value (`uniq_id`, `value`, `rule_id`) VALUES (
                    '" . $this->_db->escape($uniq_id) . "',
                    '" . $this->_db->escape($value) . "',
                    '" . $this->_db->escape($nrule_id) . "'
                )"
            );
        }

        foreach ($datas['clones'] as $uniq_id => $orders) {
            foreach ($orders as $order => $values) {
                foreach ($values as $key => $value) {
                    $this->_db->query(
                        "INSERT INTO mod_open_tickets_form_clone (
                            `uniq_id`, `label`, `value`, `rule_id`, `order`
                        ) VALUES (
                            '" . $this->_db->escape($uniq_id) . "',
                            '" . $this->_db->escape($key) . "',
                            '" . $this->_db->escape($value) . "',
                            '" . $this->_db->escape($nrule_id) . "',
                            '" . $this->_db->escape($order) . "'
                        )"
                    );
                }
            }
        }

        $this->_db->commit();
    }

    public function getRuleList()
    {
        $result = array();
        $dbResult = $this->_db->query(
            "SELECT r.rule_id, r.activate, r.alias FROM mod_open_tickets_rule r ORDER BY r.alias"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['rule_id']] = $row['alias'];
        }

        return $result;
    }

    public function get($rule_id)
    {
        $result = array();
        if (is_null($rule_id)) {
            return $result;
        }

        $dbResult = $this->_db->query(
            "SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" . $this->_db->escape($rule_id) . "' LIMIT 1"
        );
        if (!($row = $dbResult->fetch())) {
            return $result;
        }
        $result['provider_id'] = $row['provider_id'];
        $result['rule_alias'] = $row['alias'];

        $result['clones'] = array();
        $dbResult = $this->_db->query(
            "SELECT * FROM mod_open_tickets_form_clone
            WHERE rule_id = '" . $this->_db->escape($rule_id) . "'
            ORDER BY uniq_id, `order` ASC"
        );
        while (($row = $dbResult->fetch())) {
            if (!isset($result['clones'][$row['uniq_id']])) {
                $result['clones'][$row['uniq_id']] = array();
            }
            if (!isset($result['clones'][$row['uniq_id']][$row['order']])) {
                $result['clones'][$row['uniq_id']][$row['order']] = array();
            }
            $result['clones'][$row['uniq_id']][$row['order']][$row['label']] = $row['value'];
        }

        $dbResult = $this->_db->query(
            "SELECT * FROM mod_open_tickets_form_value WHERE rule_id = '" . $this->_db->escape($rule_id) . "'"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['uniq_id']] = $row['value'];
        }

        return $result;
    }

    /**
     * Enable rules
     *
     * @param array $select
     * @return void
     */
    public function enable($select)
    {
        $this->_setActivate($select, 1);
    }

    /**
     * Disable rules
     *
     * @param array $select
     * @return void
     */
    public function disable($select)
    {
        $this->_setActivate($select, 0);
    }

    /**
     * Duplicate rules
     *
     * @param array $select
     * @param array $duplicateNb
     * @return void
     */
    public function duplicate($select = array(), $duplicateNb = array())
    {
        $this->_db->beginTransaction();
        foreach ($select as $ruleId => $val) {
            $res = $this->_db->query(
                "SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" . $ruleId . "' LIMIT 1"
            );
            if (!$res->rowCount()) {
                throw new Exception(sprintf('Rule ID: % not found', $ruleId));
            }
            $row = $res->fetch();

            $i = 1;
            if (isset($duplicateNb[$ruleId]) && $duplicateNb[$ruleId] > 0) {
                for ($j = 1; $j <= $duplicateNb[$ruleId]; $j++) {
                    $name = $row['alias'] . "_" . $j;
                    $res2 = $this->_db->query(
                        "SELECT `rule_id`
                        FROM `mod_open_tickets_rule`
                        WHERE `alias` = '" . $this->_db->escape($name) . "'"
                    );
                    while ($res2->rowCount()) {
                        $res2->free();
                        $i++;
                        $name = $row['alias'] . "_" . $i;
                        $res2 = $this->_db->query(
                            "SELECT `rule_id`
                            FROM `mod_open_tickets_rule`
                            WHERE `alias` = '" . $this->_db->escape($name) . "'"
                        );
                    }
                    $this->_db->query(
                        "INSERT INTO mod_open_tickets_rule (`alias`, `provider_id`, `activate`) VALUES (
                            '" . $this->_db->escape($name) . "',
                            " . $row['provider_id'] . ",
                            " . $row['activate'] . "
                        )"
                    );
                    $nrule_id = $this->_db->lastinsertId('mod_open_tickets_rule');

                    // Duplicate form clone
                    $res2 = $this->_db->query("SELECT * FROM mod_open_tickets_form_clone WHERE rule_id=" . $ruleId);
                    while (($row2 = $res2->fetch())) {
                        $this->_db->query(
                            "INSERT INTO mod_open_tickets_form_clone (
                                `uniq_id`, `label`, `value`, `rule_id`, `order`
                            ) VALUES (
                                '" . $this->_db->escape($row2['uniq_id']) . "',
                                '" . $this->_db->escape($row2['label']) . "',
                                '" . $this->_db->escape($row2['value']) . "',
                                " . $nrule_id . ",
                                '" . $row2['order'] . "'
                            )"
                        );
                    }

                    // Duplicate macros
                    $res2 = $this->_db->query("SELECT * FROM mod_open_tickets_form_value WHERE rule_id=" . $ruleId);
                    while (($row3 = $res2->fetch())) {
                        $this->_db->query(
                            "INSERT INTO mod_open_tickets_form_value (`uniq_id`, `value`, `rule_id`) VALUES (
                                '" . $row3['uniq_id'] . "',
                                '" . $this->_db->escape($row3['value']) . "',
                                " . $nrule_id . "
                            )"
                        );
                    }
                }
            }
        }
        $this->_db->commit();
    }

    /**
     * Delete rules
     *
     * @param array select
     * @return void
     */
    public function delete($select)
    {
        $query = "DELETE FROM mod_open_tickets_rule WHERE rule_id IN (";
        $ruleList = "";
        foreach ($select as $key => $value) {
            if ($ruleList) {
                $ruleList .= ",";
            }
            $ruleList .= "'" . $key . "'";
        }
        $query .= $ruleList;
        $query .= ")";
        if (!$ruleList) {
            return null;
        }
        $this->_db->query($query);
    }

    public function getHostgroup($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " hg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT hg_id, hg_name FROM hostgroup WHERE " . $where . " hg_activate = '1' ORDER BY hg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hg_id']] = $row['hg_name'];
        }

        return $result;
    }

    public function getContactgroup($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " cg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT cg_id, cg_name FROM contactgroup WHERE " . $where . " cg_activate = '1' ORDER BY cg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['cg_id']] = $row['cg_name'];
        }

        return $result;
    }

    public function getServicegroup($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " sg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT sg_id, sg_name FROM servicegroup WHERE " . $where . " sg_activate = '1' ORDER BY sg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sg_id']] = $row['sg_name'];
        }

        return $result;
    }

    public function getHostcategory($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " hc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT hc_id, hc_name
            FROM hostcategories
            WHERE " . $where . " hc_activate = '1'
            ORDER BY hc_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hc_id']] = $row['hc_name'];
        }

        return $result;
    }

    public function getHostseverity($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " hc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT hc_id, hc_name
            FROM hostcategories
            WHERE " . $where . " level IS NOT NULL
            AND hc_activate = '1'
            ORDER BY level ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hc_id']] = $row['hc_name'];
        }

        return $result;
    }

    public function getServicecategory($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " sc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT sc_id, sc_name
            FROM service_categories
            WHERE " . $where . " sc_activate = '1'
            ORDER BY sc_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sc_id']] = $row['sc_name'];
        }

        return $result;
    }

    public function getServiceseverity($filter)
    {
        $result = array();
        $where = '';
        if (!is_null($filter) && $filter != '') {
            $where = " sc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            "SELECT sc_id, sc_name
            FROM service_categories
            WHERE " . $where . " level IS NOT NULL
            AND sc_activate = '1'
            ORDER BY level ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sc_id']] = $row['sc_name'];
        }

        return $result;
    }

    private function getServiceStateStr($state)
    {
        $result = 'CRITICAL';

        if ($state == 0) {
            $result = 'OK';
        } elseif ($state == 1) {
            $result = 'WARNING';
        } elseif ($state == 2) {
            $result = 'CRITICAL';
        } elseif ($state == 3) {
            $result = 'UNKNOWN';
        } elseif ($state == 4) {
            $result = 'PENDING';
        }
        return $result;
    }

    private function getHostStateStr($state)
    {
        $result = 'DOWN';

        if ($state == 0) {
            $result = 'UP';
        } elseif ($state == 1) {
            $result = 'DOWN';
        } elseif ($state == 2) {
            $result = 'UNREACHABLE';
        }
        return $result;
    }
}
