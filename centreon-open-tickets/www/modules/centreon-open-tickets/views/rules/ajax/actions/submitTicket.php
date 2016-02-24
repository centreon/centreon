<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
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

function get_contact_alias() {
    global $db, $centreon_bg;
    
    $result = '';
    $DBRESULT = $db->query("SELECT contact_alias FROM contact WHERE contact_id = '" . $centreon_bg->user_id . "' LIMIT 1");
    if (($row = $DBRESULT->fetchRow())) {
        $result = $row['contact_alias'];
    }
    
    return $result;
}

function get_service_state_str($state) {
    $result = 'CRITICAL';
    
    if ($state == 0) {
        $result = 'OK';
    } else if ($result == 1) {
        $result = 'WARNING';
    } else if ($result == 2) {
        $result = 'CRITICAL';
    } else if ($result == 3) {
        $result = 'UNKNOWN';
    } else if ($result == 4) {
        $result = 'PENDING';
    } 
    return $result;
}

function get_host_state_str($state) {
    $result = 'DOWN';
    
    if ($state == 0) {
        $result = 'UP';
    } else if ($result == 1) {
        $result = 'DOWN';
    } else if ($result == 2) {
        $result = 'UNREACHABLE';
    }
    return $result;
}

$resultat = array(
    "code" => 0,
    "msg" => 'ok'
);

// Load provider class
if (is_null($get_information['provider_id']) || is_null($get_information['form'])) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set provider_id or form';
    return ;
}

$provider_name = null;
foreach ($register_providers as $name => $id) {
    if ($id == $get_information['provider_id']) {
        $provider_name = $name;
        break;
    }
}

if (is_null($provider_name) || !file_exists($centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php')) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';
    return ;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname($rule, $centreon_path, $centreon_open_tickets_path, $get_information['rule_id'], $get_information['form']);

// We get Host or Service
require_once $centreon_path . 'www/class/centreonDuration.class.php';

$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new centreonDBManager('centstorage');
$host_problems = array();
$service_problems = array();

if ($get_information['form']['cmd'] == 3) {
    $selected_str = '';
    $selected_str_append = '';
    foreach ($selected_values as $value) {
        $str = explode(';', $value);
        $selected_str .= $selected_str_append . 'services.host_id = ' . $str[0] . ' AND services.service_id = ' . $str[1];
        $selected_str_append = ' OR ';
    }
    
    $query = "SELECT services.*, hosts.name as host_name, hosts.instance_id FROM services, hosts";
    $query_where = " WHERE (" . $selected_str . ') AND services.host_id = hosts.host_id';
    if (!$centreon_bg->is_admin) {
        $query_where .= " AND EXISTS(SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (" . $centreon_bg->grouplistStr . ") AND hosts.host_id = centreon_acl.host_id 
        AND services.service_id = centreon_acl.service_id)";
    }
    
    $DBRESULT = $db_storage->query($query . $query_where);
    while (($row = $DBRESULT->fetchRow())) {
        $row['state_str'] = get_service_state_str($row['state']);
        $row['last_state_change_duration'] = CentreonDuration::toString(time() - $row['last_state_change']);
        $row['last_hard_state_change_duration'] = CentreonDuration::toString(time() - $row['last_hard_state_change']);
        $service_problems[] = $row;
    }
} else if ($get_information['form']['cmd'] == 4) {
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
        $query_where .= " AND EXISTS(SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (" . $centreon_bg->grouplistStr . ") AND hosts.host_id = centreon_acl.host_id)";
    }

    $DBRESULT = $db_storage->query($query . $query_where);
    while (($row = $DBRESULT->fetchRow())) {
        $row['state_str'] = get_host_state_str($row['state']);
        $row['last_state_change_duration'] = CentreonDuration::toString(time() - $row['last_state_change']);
        $row['last_hard_state_change_duration'] = CentreonDuration::toString(time() - $row['last_hard_state_change']);
        $host_problems[] = $row;
    }   
}

try {
    $contact_alias = get_contact_alias();
    $resultat['result'] = $centreon_provider->submitTicket($db_storage, $contact_alias, $host_problems, $service_problems);
    
    if ($resultat['result']['ticket_is_ok'] == 1) { 
        require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
        $oreon = $_SESSION['centreon'];
        $external_cmd = new CentreonExternalCommand($oreon);
        
        foreach ($host_problems as $value) {
            $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
            $external_cmd->set_process_command(sprintf($command, $value['name'], $centreon_provider->getMacroTicketId(), $resultat['result']['ticket_id']), $value['instance_id']);
            $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
            $external_cmd->set_process_command(sprintf($command, $value['name'], $centreon_provider->getMacroTicketTime(), $resultat['result']['ticket_time']), $value['instance_id']);
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_HOST_PROBLEM;%s;%s;%s;%s;%s;%s;%";
                $external_cmd->set_process_command(sprintf($command, $value['name'], 2, 0, 1, $contact_alias, 'open ticket: ' . $resultat['result']['ticket_id']), $value['instance_id']);
            }
        }
        foreach ($service_problems as $value) {
            $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
            $external_cmd->set_process_command(sprintf($command, $value['host_name'], $value['description'], $centreon_provider->getMacroTicketId(), $resultat['result']['ticket_id']), $value['instance_id']);
            $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
            $external_cmd->set_process_command(sprintf($command, $value['host_name'], $value['description'], $centreon_provider->getMacroTicketTime(), $resultat['result']['ticket_time']), $value['instance_id']);
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_SVC_PROBLEM;%s;%s;%s;%s;%s;%s;%s";
                $external_cmd->set_process_command(sprintf($command, $value['host_name'], $value['description'], 2, 0, 1, $contact_alias, 'open ticket: ' . $resultat['result']['ticket_id']), $value['instance_id']);
            }
        }
        
        $external_cmd->write();
    }
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
    $db->rollback();
}

?>
