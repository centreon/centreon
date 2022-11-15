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

function smarty_function_host_get_hostgroups($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_hostgroups_result', array());
        return ;
    }
    $db = new CentreonDBManager('centstorage');

    $result = array();
    $dbResult = $db->query(
        "SELECT hostgroups.* FROM hosts_hostgroups, hostgroups
        WHERE hosts_hostgroups.host_id = " . $params['host_id'] . "
        AND hosts_hostgroups.hostgroup_id = hostgroups.hostgroup_id"
    );
    while (($row = $dbResult->fetch())) {
        $result[$row['hostgroup_id']] = $row['name'];
    }
    $smarty->assign('host_get_hostgroups_result', $result);
}

function smarty_function_host_get_severity($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php' ;
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_severity_result', array());
        return ;
    }
    $db = new CentreonDBManager();

    $result = array();
    $dbResult = $db->query(
        "SELECT
            hc_id, hc_name, level
        FROM hostcategories_relation, hostcategories
        WHERE hostcategories_relation.host_host_id = " . $params['host_id'] . "
        AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id
        AND level IS NOT NULL AND hc_activate = '1'
        ORDER BY level DESC
        LIMIT 1"
    );
    while (($row = $dbResult->fetch())) {
        $result[$row['hc_id']] = array('name' => $row['hc_name'], 'level' => $row['level']);
    }
    $smarty->assign('host_get_severity_result', $result);
}

/*
 * Smarty example:
 *      {host_get_hostcategories host_id="104"}
 *      host categories linked:
 *      {foreach from=$host_get_hostcategories_result key=hc_id item=i}
 *          id: {$hc_id} name = {$i.name}
 *      {/foreach}
 *
 */
function smarty_function_host_get_hostcategories($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_hostcategories_result', array());
        return ;
    }
    $db = new CentreonDBManager();

    $loop = array();
    $array_stack = array($params['host_id']);
    $result = array();
    while ($host_id = array_shift($array_stack)) {
        if (isset($loop[$host_id])) {
            continue;
        }
        $loop[$host_id] = 1;
        $dbResult = $db->query(
            "SELECT htr.host_tpl_id, hcr.hostcategories_hc_id, hostcategories.hc_name
            FROM host
            LEFT JOIN host_template_relation htr ON host.host_id = htr.host_host_id
            LEFT JOIN hostcategories_relation hcr ON htr.host_host_id = hcr.host_host_id
            LEFT JOIN hostcategories ON hostcategories.hc_id = hcr.hostcategories_hc_id
            AND hostcategories.hc_activate = '1'
            WHERE host.host_id = " . $host_id . " ORDER BY `order` ASC"
        );
        while (($row = $dbResult->fetch())) {
            if (!is_null($row['host_tpl_id']) && $row['host_tpl_id'] != '') {
                array_unshift($array_stack, $row['host_tpl_id']);
            }
            if (!is_null($row['hostcategories_hc_id']) && $row['hostcategories_hc_id'] != '') {
                $result[$row['hostcategories_hc_id']] = array('name' => $row['hc_name']);
            }
        }
    }
    $smarty->assign('host_get_hostcategories_result', $result);
}

/*
 *  Smarty example:
 *      {service_get_servicecategories service_id="1928"}
 *      service categories linked:
 *      {foreach from=$service_get_servicecategories_result key=sc_id item=i}
 *         id: {$sc_id} name = {$i.name}, description = {$i.description}
 *      {/foreach}
 *
 */
function smarty_function_service_get_servicecategories($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['service_id'])) {
        $smarty->assign('service_get_servicecategories_result', array());
        return ;
    }
    $db = new CentreonDBManager();

    $loop = array();
    $array_stack = array($params['service_id']);
    $result = array();
    while ($service_id = array_shift($array_stack)) {
        if (isset($loop[$service_id])) {
            continue;
        }
        $loop[$service_id] = 1;
        $dbResult = $db->query(
            "SELECT service.service_template_model_stm_id, sc.sc_id, sc.sc_name, sc.sc_description
            FROM service
            LEFT JOIN service_categories_relation scr ON service.service_id = scr.service_service_id
            LEFT JOIN service_categories sc ON sc.sc_id = scr.sc_id AND sc.sc_activate = '1'
            WHERE service.service_id = " . $service_id
        );
        while (($row = $dbResult->fetch())) {
            if (!is_null($row['service_template_model_stm_id']) && $row['service_template_model_stm_id'] != '') {
                array_unshift($array_stack, $row['service_template_model_stm_id']);
            }
            if (!is_null($row['sc_id']) && $row['sc_id'] != '') {
                $result[$row['sc_id']] = array('name' => $row['sc_name'], 'description' => $row['sc_description']);
            }
        }
    }
    $smarty->assign('service_get_servicecategories_result', $result);
}

/*
 *  Smarty example:
 *      {service_get_servicegroups host_id="104" service_id="1928"}
 *      service groups linked:
 *      {foreach from=$service_get_servicegroups_result key=sg_id item=i}
 *         id: {$sg_id} name = {$i.name}, alias = {$i.alias}
 *      {/foreach}
 *
 */
function smarty_function_service_get_servicegroups($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['service_id'])) {
        $smarty->assign('service_get_servicegroups_result', array());
        return ;
    }
    $db = new CentreonDBManager();

    $result = array();
    $service_id_tpl = $params['service_id'];
    if (isset($params['host_id'])) {
        $dbResult = $db->query(
            "SELECT service.service_template_model_stm_id, sg.sg_id, sg.sg_name, sg.sg_alias
            FROM servicegroup_relation sgr
            LEFT JOIN servicegroup sg ON sgr.servicegroup_sg_id = sg.sg_id
            AND sg.sg_activate = '1'
            LEFT JOIN service ON service.service_id = sgr.service_service_id
            WHERE sgr.host_host_id = " . $params['host_id'] . "
            AND sgr.service_service_id = " . $params['service_id']
        );
        while (($row = $dbResult->fetch())) {
            $service_id_tpl = $row['service_template_model_stm_id'];
            if (!is_null($row['sg_id']) && $row['sg_id'] != '') {
                $result[$row['sg_id']] = array('name' => $row['sg_name'], 'alias' => $row['sg_alias']);
            }
        }
    }

    $loop_array = array();
    while (!is_null($service_id_tpl) && $service_id_tpl != '') {
        if (isset($loop_array[$service_id_tpl])) {
            break;
        }
        $loop_array[$service_id_tpl] = 1;
        $dbResult = $db->query(
            "SELECT service.service_template_model_stm_id, sg.sg_id, sg.sg_name, sg.sg_alias
            FROM servicegroup_relation sgr
            LEFT JOIN servicegroup sg ON sgr.servicegroup_sg_id = sg.sg_id
            AND sg.sg_activate = '1'
            LEFT JOIN service ON service.service_id = sgr.service_service_id
            WHERE sgr.service_service_id = " . $service_id_tpl
        );
        while (($row = $dbResult->fetch())) {
            $service_id_tpl = $row['service_template_model_stm_id'];
            if (!is_null($row['sg_id']) && $row['sg_id'] != '') {
                $result[$row['sg_id']] = array('name' => $row['sg_name'], 'alias' => $row['sg_alias']);
            }
        }
    }

    $smarty->assign('service_get_servicegroups_result', $result);
}

function smarty_function_host_get_macro_value_in_config($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_macro_value_in_config_result', '');
        return ;
    }
    if (!isset($params['macro_name'])) {
        $smarty->assign('host_get_macro_value_in_config_result', '');
        return ;
    }
    $db = new CentreonDBManager();

    // Look macro in current host
    $dbResult = $db->query(
        "SELECT host_macro_value
        FROM on_demand_macro_host
        WHERE host_host_id = " . $params['host_id'] . "
        AND host_macro_name = '\$_HOST" . $params['macro_name'] . "\$'"
    );
    if (($row = $dbResult->fetch())) {
        $smarty->assign('host_get_macro_value_in_config_result', $row['host_macro_value']);
        return ;
    }

    // Look macro in host template relation
    $loop = array();
    $array_stack = array(array('host_id' => $params['host_id'], 'macro_value' => null));
    $result = '';
    while (($host_entry = array_pop($array_stack))) {
        if (isset($loop[$host_entry['host_id']])) {
            continue;
        }
        if (!is_null($host_entry['macro_value'])) {
            $result = $host_entry['macro_value'];
            break;
        }
        $loop[$host_entry['host_id']] = 1;
        $dbResult = $db->query(
            "SELECT
                host_tpl_id, macro.host_macro_value
            FROM host_template_relation
            LEFT JOIN on_demand_macro_host macro ON macro.host_host_id = host_template_relation.host_tpl_id
            AND macro.host_macro_name = '\$_HOST" . $params['macro_name'] . "\$'
            WHERE host_template_relation.host_host_id = " . $host_entry['host_id'] . "
            ORDER BY `order` DESC"
        );
        while (($row = $dbResult->fetch())) {
            $entry = array('host_id' => $row['host_tpl_id'], 'macro_value' => null);
            if (!is_null($row['host_macro_value'])) {
                $entry['macro_value'] = $row['host_macro_value'];
            }

            array_push($array_stack, $entry);
        }
    }
    $smarty->assign('host_get_macro_value_in_config_result', $result);
}

function smarty_function_host_get_macro_values_in_config($params, &$smarty)
{
    include_once __DIR__ . '/../../centreon-open-tickets.conf.php';
    include_once __DIR__ . '/../../class/centreonDBManager.class.php';

    if (!isset($params['host_id'])) {
        $smarty->assign('host_get_macro_values_in_config_result', array());
        return ;
    }
    if (!isset($params['macro_name'])) {
        $smarty->assign('host_get_macro_values_in_config_result', array());
        return ;
    }
    $db = new CentreonDBManager();
    $result = array();

    // Get level 1
    $dbresult1 = $db->query(
        "SELECT
            host_tpl_id, macro.host_macro_value
        FROM host_template_relation
        LEFT JOIN on_demand_macro_host macro ON macro.host_host_id = host_template_relation.host_tpl_id
        AND macro.host_macro_name = '\$_HOST" . $params['macro_name'] . "\$'
        WHERE host_template_relation.host_host_id = " . $params['host_id'] . "
        ORDER BY `order` ASC"
    );
    while (($row_entry_level1 = $dbresult1->fetch())) {
        $loop = array();
        $array_stack = array(array(
            'host_id' => $row_entry_level1['host_tpl_id'],
            'macro_value' => $row_entry_level1['host_macro_value']
        ));
        while (($host_entry = array_pop($array_stack))) {
            if (isset($loop[$host_entry['host_id']])) {
                continue;
            }
            if (!is_null($host_entry['macro_value'])) {
                $result[] = $host_entry['macro_value'];
                break;
            }
            $loop[$host_entry['host_id']] = 1;
            $dbResult = $db->query(
                "SELECT
                    host_tpl_id, macro.host_macro_value
                FROM host_template_relation
                LEFT JOIN on_demand_macro_host macro ON macro.host_host_id = host_template_relation.host_tpl_id
                AND macro.host_macro_name = '\$_HOST" . $params['macro_name'] . "\$'
                WHERE host_template_relation.host_host_id = " . $host_entry['host_id'] . "
                ORDER BY `order` DESC"
            );
            while (($row = $dbResult->fetch())) {
                $entry = array('host_id' => $row['host_tpl_id'], 'macro_value' => null);
                if (!is_null($row['host_macro_value'])) {
                    $entry['macro_value'] = $row['host_macro_value'];
                }

                array_push($array_stack, $entry);
            }
        }
    }

    $smarty->assign('host_get_macro_values_in_config_result', $result);
}

function smarty_function_sortgroup($params, &$smarty)
{
    asort($params['group']);
    $smarty->assign('sortgroup_result', $params['group']);
}
