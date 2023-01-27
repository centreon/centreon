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

$resultat = array(
    "code" => 0,
    "msg" => 'ok',
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

if (is_null($provider_name)
    || !file_exists(
        $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php'
    )
) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';
    return ;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name .
    '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname(
    $rule,
    $centreon_path,
    $centreon_open_tickets_path,
    $get_information['rule_id'],
    $get_information['form'],
    $get_information['provider_id']
);

// We get Host or Service
$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new centreonDBManager('centstorage');

$problems = array();
$tickets = array();

# check services and hosts
$selected_str = '';
$selected_str_append = '';
$hosts_selected_str = '';
$hosts_selected_str_append = '';
$hosts_done = array();
$services_done = array();
foreach ($selected_values as $value) {
    $str = explode(';', $value);
    $selected_str .= $selected_str_append . 'services.host_id = ' .
        $str[0] . ' AND services.service_id = ' . $str[1];
    $selected_str_append = ' OR ';

    if (!isset($hosts_done[$str[0]])) {
        $hosts_selected_str .= $hosts_selected_str_append . $str[0];
        $hosts_selected_str_append = ', ';
        $hosts_done[$str[0]] = 1;
    }
}

$query = "(SELECT DISTINCT
        services.description, hosts.name as host_name, hosts.instance_id, mot.ticket_value, mot.timestamp
    FROM services, hosts, mod_open_tickets_link as motl, mod_open_tickets as mot
    WHERE (" . $selected_str . ') AND services.host_id = hosts.host_id';
if (!$centreon_bg->is_admin) {
    $query .= " AND EXISTS(
        SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (" .
            $centreon_bg->grouplistStr . "
        )
        AND hosts.host_id = centreon_acl.host_id
        AND services.service_id = centreon_acl.service_id)";
}
$query .= " AND motl.host_id = hosts.host_id
            AND motl.service_id = services.service_id
            AND motl.ticket_id = mot.ticket_id
            AND mot.timestamp > services.last_hard_state_change
    ) UNION ALL (
        SELECT DISTINCT
            NULL as description,
            hosts.name as host_name,
            hosts.instance_id,
            mot.ticket_value,
            mot.timestamp
        FROM hosts, mod_open_tickets_link as motl, mod_open_tickets as mot
        WHERE hosts.host_id IN (" . $hosts_selected_str . ")";
if (!$centreon_bg->is_admin) {
    $query .= " AND EXISTS(
        SELECT * FROM centreon_acl
        WHERE centreon_acl.group_id IN (
        " . $centreon_bg->grouplistStr . "
        ) AND hosts.host_id = centreon_acl.host_id)";
}
$query .= " AND motl.host_id = hosts.host_id
            AND motl.service_id IS NULL
            AND motl.ticket_id = mot.ticket_id
            AND mot.timestamp > hosts.last_hard_state_change
    ) ORDER BY `host_name`, `description`, `timestamp` DESC";

$hosts_done = array();

$dbResult = $db_storage->query($query);
while ($row = $dbResult->fetch()) {
    if (isset($hosts_done[$row['host_name'] . ';' . $row['description']])) {
        continue;
    }

    $problems[] = $row;
    $tickets[$row['ticket_value']] = array('status' => 0, 'msg_error' => null);
    $hosts_done[$row['host_name'] . ';' . $row['description']] = 1;
}

try {
    $centreon_provider->closeTicket($tickets);
    require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
    $oreon = $_SESSION['centreon'];
    $external_cmd = new CentreonExternalCommand($oreon);
    $method_external_name = 'set_process_command';
    if (method_exists($external_cmd, $method_external_name) == false) {
        $method_external_name = 'setProcessCommand';
    }

    $removed_tickets = array();
    $error_msg = array();

    foreach ($problems as $row) {
        // an error in ticket close
        if (isset($tickets[$row['ticket_value']]) && $tickets[$row['ticket_value']]['status'] == -1) {
            $error_msg[] = $tickets[$row['ticket_value']]['msg_error'];
            // We close in centreon if ContinueOnError is ok
            if ($centreon_provider->doCloseTicket() &&
                $centreon_provider->doCloseTicketContinueOnError() == 0) {
                continue;
            }
        }

        // ticket is really closed
        if ($tickets[$row['ticket_value']]['status'] == 2 && !isset($removed_tickets[$row['ticket_value']])) {
            $removed_tickets[$row['ticket_value']] = 1;
        }
        if (is_null($row['description']) || $row['description'] == '') {
            $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
            call_user_func_array(
                array($external_cmd, $method_external_name),
                array(
                    sprintf($command, $row['host_name'], $centreon_provider->getMacroTicketId(), ''),
                    $row['instance_id']
                )
            );
            $command = "REMOVE_HOST_ACKNOWLEDGEMENT;%s";
            call_user_func_array(
                array($external_cmd, $method_external_name),
                array(
                    sprintf($command, $row['host_name']),
                    $row['instance_id']
                )
            );
            continue;
        }

        $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
        call_user_func_array(
            array($external_cmd, $method_external_name),
            array(
                sprintf($command, $row['host_name'], $row['description'], $centreon_provider->getMacroTicketId(), ''),
                $row['instance_id']
            )
        );
        if ($centreon_provider->doAck()) {
            $command = "REMOVE_SVC_ACKNOWLEDGEMENT;%s;%s";
            call_user_func_array(
                array($external_cmd, $method_external_name),
                array(
                    sprintf($command, $row['host_name'], $row['description']),
                    $row['instance_id']
                )
            );
        }
    }

    $external_cmd->write();
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
    $db->rollback();
}

$resultat['msg'] = '
<table class="table">
    <tr>
        <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">' . _('Close Tickets') . '</td>
    </tr>
    <tr>
        <td class="FormRowField" style="padding-left:15px;">Tickets closed: ' .
            join(",", array_keys($removed_tickets)) . '.</td>
    </tr>';

if ($centreon_provider->doCloseTicket() && count($error_msg) > 0) {
    $resultat['msg'] .= '<tr>
        <td class="FormRowField" style="padding-left:15px; color: red">Issue to close tickets: ' .
            join("<br/>", $error_msg) . '.</td>
    </tr>';
}
$resultat['msg'] .= '</table>';
