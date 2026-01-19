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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;

/**
 * updateHostMacro updates ticket custom macro value to an empty value to fully remove the ticket
 * 
 * @param string $macroName name of the macro that must be updated
 * @param int $hostId id of host
 * @throws CollectionException|ConnectionException|ValueObjectException
 * @return void
 */
function updateHostMacro(string $macroName, int $hostId): void {
    global $db;

    // check if host has the macro set up
    $query = <<<'SQL'
            SELECT host_macro_id
            FROM on_demand_macro_host
            WHERE host_macro_name = :macro_name AND host_host_id = :host_id
        SQL;
    $row = $db->fetchAssociative($query, QueryParameters::create([
        QueryParameter::string('macro_name', $macroName),
        QueryParameter::int('host_id', $hostId)
    ]));

    if ($row) {
        $macroId = (int) $row['host_macro_id'];
        $query = <<<'SQL'
                UPDATE on_demand_macro_host
                SET host_macro_value = ''
                WHERE host_macro_id = :macro_id
            SQL;
        $db->update($query, QueryParameters::create([
            QueryParameter::int('macro_id', $macroId)
        ]));
    }
}

/**
 * updateServiceMacro updates ticket custom macro value to an empty value to fully remove the ticket
 * 
 * @param string $macroName name of the macro that must be updated
 * @param int $serviceId id of service
 * @throws CollectionException|ConnectionException|ValueObjectException
 * @return void
 */
function updateServiceMacro(string $macroName, int $serviceId): void {
    global $db;

    // check if service has the macro set up
    $query = <<<'SQL'
            SELECT svc_macro_id
            FROM on_demand_macro_service
            WHERE svc_macro_name = :macro_name AND svc_svc_id = :service_id
        SQL;
    $row = $db->fetchAssociative($query, QueryParameters::create([
        QueryParameter::string('macro_name', $macroName),
        QueryParameter::int('service_id', $serviceId)
    ]));

    if ($row) {
        $macroId = (int) $row['svc_macro_id'];
        $query = <<<'SQL'
                UPDATE on_demand_macro_service
                SET svc_macro_value = ''
                WHERE svc_macro_id = :macro_id
            SQL;
        $db->update($query, QueryParameters::create([
            QueryParameter::int('macro_id', $macroId)
        ]));
    }
}

$resultat = ['code' => 0, 'msg' => 'ok'];

// Load provider class
if (is_null($get_information['provider_id']) || is_null($get_information['form'])) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set provider_id or form';

    return;
}

$provider_name = null;
foreach ($register_providers as $name => $id) {
    if ($id == $get_information['provider_id']) {
        $provider_name = $name;
        break;
    }
}

if (is_null($provider_name)
    || ! file_exists(
        $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php'
    )
) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';

    return;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name
    . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname(
    $rule,
    $centreon_path,
    $centreon_open_tickets_path,
    $get_information['rule_id'],
    $get_information['form'],
    $get_information['provider_id'],
    $provider_name
);

// We get Host or Service
$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new CentreonDBManager('centstorage');

$problems = [];
$tickets = [];

// check services and hosts
$selected_str = '';
$selected_str_append = '';
$hosts_selected_str = '';
$hosts_selected_str_append = '';
$hosts_done = [];
$services_done = [];
foreach ($selected_values as $value) {
    $str = explode(';', $value);
    $selected_str .= $selected_str_append . 'services.host_id = '
        . $str[0] . ' AND services.service_id = ' . $str[1];
    $selected_str_append = ' OR ';

    if (! isset($hosts_done[$str[0]])) {
        $hosts_selected_str .= $hosts_selected_str_append . $str[0];
        $hosts_selected_str_append = ', ';
        $hosts_done[$str[0]] = 1;
    }
}

$query = '(SELECT DISTINCT
        services.description, services.service_id, hosts.name as host_name, hosts.host_id, hosts.instance_id, mot.ticket_value, mot.timestamp
    FROM services, hosts, mod_open_tickets_link as motl, mod_open_tickets as mot
    WHERE (' . $selected_str . ') AND services.host_id = hosts.host_id';
if (! $centreon_bg->is_admin) {
    $query .= ' AND EXISTS(
        SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN ('
            . $centreon_bg->grouplistStr . '
        )
        AND hosts.host_id = centreon_acl.host_id
        AND services.service_id = centreon_acl.service_id)';
}
$query .= ' AND motl.host_id = hosts.host_id
            AND motl.service_id = services.service_id
            AND motl.ticket_id = mot.ticket_id
    ) UNION ALL (
        SELECT DISTINCT
            NULL as description,
            NULL as service_id,
            hosts.name as host_name,
            hosts.host_id,
            hosts.instance_id,
            mot.ticket_value,
            mot.timestamp
        FROM hosts, mod_open_tickets_link as motl, mod_open_tickets as mot
        WHERE hosts.host_id IN (' . $hosts_selected_str . ')';
if (! $centreon_bg->is_admin) {
    $query .= ' AND EXISTS(
        SELECT * FROM centreon_acl
        WHERE centreon_acl.group_id IN (
        ' . $centreon_bg->grouplistStr . '
        ) AND hosts.host_id = centreon_acl.host_id)';
}
$query .= ' AND motl.host_id = hosts.host_id
            AND motl.service_id IS NULL
            AND motl.ticket_id = mot.ticket_id
    ) ORDER BY `host_name`, `description`, `timestamp` DESC';

$hosts_done = [];
try {
    $dbResult = $db_storage->fetchAllAssociative($query);
} catch (ConnectionException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while fetching tickets to close: ' . $e->getMessage(),
        exception: $e
    );
    $resultat['code'] = 1;
    $resultat['msg'] = 'Error while fetching tickets to close: ' . $e->getMessage();

    return;
}

foreach ($dbResult as $row) {
    if (isset($hosts_done[$row['host_name'] . ';' . $row['description']])) {
        continue;
    }

    $problems[] = $row;
    $tickets[$row['ticket_value']] = ['status' => 0, 'msg_error' => null];
    $hosts_done[$row['host_name'] . ';' . $row['description']] = 1;
}

try {
    $centreon_provider->closeTicket($tickets);
    require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
    $oreon = $_SESSION['centreon'];
    $external_cmd = new CentreonExternalCommand();
    $method_external_name = 'set_process_command';
    if (method_exists($external_cmd, $method_external_name) == false) {
        $method_external_name = 'setProcessCommand';
    }

    $removed_tickets = [];
    $error_msg = [];
    $macroName = $centreon_provider->getMacroTicketId();

    $ownTransaction = ! $db->isTransactionActive();
    if ($ownTransaction) {
        $db->startTransaction();
    }

    foreach ($problems as $row) {
        // an error in ticket close
        if (isset($tickets[$row['ticket_value']]) && $tickets[$row['ticket_value']]['status'] == -1) {
            $error_msg[] = $tickets[$row['ticket_value']]['msg_error'];
            // We close in centreon if ContinueOnError is ok
            if ($centreon_provider->doCloseTicket()
                && $centreon_provider->doCloseTicketContinueOnError() == 0) {
                continue;
            }
        }

        // ticket is really closed
        if ($tickets[$row['ticket_value']]['status'] == 2 && ! isset($removed_tickets[$row['ticket_value']])) {
            $removed_tickets[$row['ticket_value']] = 1;
        }
        if (is_null($row['description']) || $row['description'] == '') {
            $fullMacroName = '$_HOST' . $macroName . '$';
            updateHostMacro($fullMacroName, $row['host_id']);
            $command = 'CHANGE_CUSTOM_HOST_VAR;%s;%s;%s';
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [sprintf($command, $row['host_name'], $macroName, ''), $row['instance_id']]
            );
            $command = 'REMOVE_HOST_ACKNOWLEDGEMENT;%s';
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [sprintf($command, $row['host_name']), $row['instance_id']]
            );
            continue;
        }

        $fullMacroName = '$_SERVICE' . $macroName . '$';
        updateServiceMacro($fullMacroName, $row['service_id']);
        $command = 'CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s';
        call_user_func_array(
            [$external_cmd, $method_external_name],
            [sprintf($command, $row['host_name'], $row['description'], $macroName, ''), $row['instance_id']]
        );
        if ($centreon_provider->doAck()) {
            $command = 'REMOVE_SVC_ACKNOWLEDGEMENT;%s;%s';
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [sprintf($command, $row['host_name'], $row['description']), $row['instance_id']]
            );
        }
    }

    $external_cmd->write();
    if ($ownTransaction) {
        $db->commitTransaction();
    }
} catch (CollectionException|ConnectionException|ValueObjectException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while closing tickets: ' . $e->getMessage(),
        exception: $e
    );

    try {
        if (($ownTransaction ?? false) && $db->isTransactionActive()) {
            $db->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Failed to roll back transaction while closing tickets: ' . $rollbackException->getMessage(),
            exception: $rollbackException
        );
    }

    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
}

$resultat['msg'] = '
<table class="table">
    <tr>
        <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">' . _('Close Tickets') . '</td>
    </tr>
    <tr>
        <td class="FormRowField" style="padding-left:15px;">Tickets closed: '
            . join(',', array_keys($removed_tickets)) . '.</td>
    </tr>';

if ($centreon_provider->doCloseTicket() && $error_msg !== []) {
    $resultat['msg'] .= '<tr>
        <td class="FormRowField" style="padding-left:15px; color: red">Issue to close tickets: '
            . join('<br/>', $error_msg) . '.</td>
    </tr>';
}
$resultat['msg'] .= '</table>';
