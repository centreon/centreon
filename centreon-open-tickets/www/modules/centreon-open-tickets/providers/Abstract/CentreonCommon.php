<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
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

function smarty_function_host_get_hostgroups($params, &$smarty) {
    require_once(dirname(__FILE__) . '/../../centreon-open-tickets.conf.php');
    require_once(dirname(__FILE__) . '/../../class/centreonDBManager.class.php'); 
    
    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_hostgroups_result', array());
        return ;
    }
    $db = new CentreonDBManager('centstorage');
    
    $result = array();
    $query = "SELECT hostgroups.* FROM hosts_hostgroups, hostgroups 
        WHERE hosts_hostgroups.host_id = " . $params['host_id'] . 
        " AND hosts_hostgroups.hostgroup_id = hostgroups.hostgroup_id";
    $DBRESULT = $db->query($query);
    while (($row = $DBRESULT->fetchRow())) {
        $result[$row['hostgroup_id']] = $row['name'];
    }
    $smarty->assign('host_get_hostgroups_result', $result);
}

function smarty_function_host_get_severity($params, &$smarty) {
    require_once(dirname(__FILE__) . '/../../centreon-open-tickets.conf.php');
    require_once(dirname(__FILE__) . '/../../class/centreonDBManager.class.php'); 

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_severity_result', array());
        return ;
    }
    $db = new CentreonDBManager();
    
    $result = array();
    $query = "SELECT 
                    hc_id, hc_name, level
                FROM hostcategories_relation, hostcategories
                WHERE hostcategories_relation.host_host_id = " . $params['host_id'] . "
                    AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id
                    AND level IS NOT NULL AND hc_activate = '1'
                ORDER BY level DESC
                LIMIT 1";
    $DBRESULT = $db->query($query);
    while (($row = $DBRESULT->fetchRow())) {
        $result[$row['hc_id']] = array('name' => $row['hc_name'], 'level' => $row['level']);
    }
    $smarty->assign('host_get_severity_result', $result);
}