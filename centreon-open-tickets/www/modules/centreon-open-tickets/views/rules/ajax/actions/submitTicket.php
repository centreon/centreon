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

function get_contact_information()
{
    global $db, $centreon_bg;

    $result = ['alias' => '', 'email' => '', 'name' => ''];
    $dbResult = $db->query(
        "SELECT
            contact_name as `name`,
            contact_alias as `alias`,
            contact_email as email
        FROM contact
        WHERE contact_id = '" . $centreon_bg->user_id . "' LIMIT 1"
    );
    if (($row = $dbResult->fetch())) {
        $result = $row;
    }

    return $result;
}

function get_provider_class($rule_id)
{
    global
        $register_providers,
    $centreon_open_tickets_path,
    $rule,
    $centreon_path,
    $get_information;

    $provider = $rule->getAliasAndProviderId($rule_id);
    $provider_name = null;
    foreach ($register_providers as $name => $id) {
        if (isset($provider['provider_id']) && $id == $provider['provider_id']) {
            $provider_name = $name;
            break;
        }
    }

    if (is_null($provider_name)) {
        return null;
    }

    require_once $centreon_open_tickets_path . 'providers/' . $provider_name
        . '/' . $provider_name . 'Provider.class.php';
    $classname = $provider_name . 'Provider';
    $provider_class = new $classname(
        $rule,
        $centreon_path,
        $centreon_open_tickets_path,
        $rule_id,
        $get_information['form'],
        $provider['provider_id'],
        $provider_name
    );

    return $provider_class;
}

function do_chain_rules($rule_list, $db_storage, $contact_infos, $selected)
{
    $loop_check = [];

    while (($provider = array_shift($rule_list))) {
        $provider_class = get_provider_class($provider['Provider']);
        if (is_null($provider_class)) {
            continue;
        }
        if (isset($loop_check[$provider['Provider']])) {
            continue;
        }

        $loop_check[$provider['Provider']] = 1;
        $provider_class->submitTicket(
            $db_storage,
            $contact_infos,
            $selected['host_selected'],
            $selected['service_selected']
        );
        array_unshift($rule_list, $provider_class->getChainRuleList());
    }
}

/**
 * getMacroId : returns the id of a macro if it is sets directly on the host or service
 * 
 * @param string $type the type of object (can be host or service)
 * @param string $macroName the name of the macro (usually TICKET_ID)
 * @param int $objectId the id of the host or service
 * 
 * @return int|null the id of the macro if directly linked to the host or service
 */
function getTicketMacroId(string $type, string $macroName, int $objectId): ?int {
    global $db;

    if ($type === 'host') {
        $query = "SELECT host_macro_id AS macro_id FROM on_demand_macro_host WHERE host_host_id = :object_id AND host_macro_name = :macro_name";
    } else {
        $query = "SELECT svc_macro_id AS macro_id FROM on_demand_macro_service WHERE svc_svc_id = :object_id AND svc_macro_name = :macro_name";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':object_id', $objectId, PDO::PARAM_INT);
    $stmt->bindParam(':macro_name', $macroName, PDO::PARAM_STR);
    $stmt->execute();


    while ($row = $stmt->fetch()) {
        return (int) $row['macro_id'];
    }

    return null;
}

/**
 * updateMacroValue when a ticket is created it needs to also be stored in the on_demand_macro_xxx table
 * 
 * @param string $type the type of object (can be host or service)
 * @param string $macroValue the value that is going to be updated
 * @param int $macroId the id of the macro that needs to be updated
 * 
 * @return void
 */
function updateMacroValue(string $type, string $macroValue, int $macroId): void {
    global $db;

    if ($type === 'host') {
        $query = "UPDATE on_demand_macro_host SET host_macro_value = :ticket_id WHERE host_macro_id = " . $macroId;
    } else {
        $query = "UPDATE on_demand_macro_service SET svc_macro_value = :ticket_id WHERE svc_macro_id = " . $macroId;
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':ticket_id', $macroValue, PDO::PARAM_STR);
    $stmt->execute();
}

/**
 * getMaxOrder gets the order number for the next custom macro
 * 
 * @param string $type the type of object (must be host or service)
 * @param int $objectId the id of the service or the host
 * 
 * @return int the next available order number
 */
function getMaxOrder(string $type, int $objectId): int {
    global $db;

    if ($type === 'host') {
        $query = "SELECT MAX(macro_order) AS max FROM on_demand_macro_host WHERE host_host_id = :object_id";
    } else {
        $query = "SELECT MAX(macro_order) AS max FROM on_demand_macro_service WHERE svc_svc_id = :object_id";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':object_id', $objectId, PDO::PARAM_INT);

    if ($row = $stmt->fetch()) {
        return (int)$row['max'] + 1;
    }

    return 0;
}

/**
 * insertNewMacroValue add a new macro on the object (service/host)
 * 
 * @param string $type the type of object (must be host or service)
 * @param string $macroName the name of the macro
 * @param string $macroValue the value of the macro
 * @param int $objectId the id of the service or the host
 * 
 * @return void
 */
function insertNewMacroValue(string $type, string $macroName, string $macroValue, int $objectId): void {
    global $db;
    $macroOrder = getMaxOrder($type, $objectId);

    if ($type === 'host') {
        $query = "INSERT INTO on_demand_macro_host (host_macro_name, host_macro_value, is_password, description, host_host_id, macro_order) VALUES (:macro_name, :ticket_id, NULL, '', :object_id, " . $macroOrder . ")";
    } else {
        $query = "INSERT INTO on_demand_macro_service (svc_macro_name, svc_macro_value, is_password, description, svc_svc_id, macro_order) VALUES (:macro_name, :ticket_id, NULL, '', :object_id, " . $macroOrder . ")";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':ticket_id', $macroValue, PDO::PARAM_STR);
    $stmt->bindParam(':object_id', $objectId, PDO::PARAM_INT);
    $stmt->bindParam(':macro_name', $macroName, PDO::PARAM_STR);
    $stmt->execute();
}

/**
 * isServiceUnique checks if the service is linked to a single host (not to multiple hosts or to a hostgroup)
 * 
 * @param int $serviceId the id of the service
 * 
 * @return bool
 */
function isServiceUnique(int $serviceId): bool {
    global $db;
    $query = <<<SQL
        SELECT count(*) AS duplicated_service 
        FROM (
            (
                SELECT hsr_id 
                FROM host_service_relation 
                WHERE service_service_id = :service_id
                    AND hostgroup_hg_id IS NOT NULL
            ) UNION (
                SELECT hsr_id 
                FROM host_service_relation 
                WHERE service_service_id = :service_id
                    AND host_host_id IS NOT NULL
                GROUP BY service_service_id HAVING COUNT(service_service_id) > 1
            )
        ) AS relation;
    SQL;

    $stmt = $db->prepare($query);
    $stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
    $stmt->execute();

    if ($row = $stmt->fetch()) {
        if ((int)$row['duplicated_service'] === 0) {
            return false;
        }
    }

    return true;
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
if (! isset($get_information['form']['widgetId'])
    || is_null($get_information['form']['widgetId'])
    || $get_information['form']['widgetId'] == ''
) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set widgetId';

    return;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

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
$centreon_provider->setWidgetId($get_information['form']['widgetId']);
$centreon_provider->setUniqId($get_information['form']['uniqId']);

// We get Host or Service
require_once $centreon_path . 'www/class/centreonDuration.class.php';

$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new CentreonDBManager('centstorage');

$selected = $rule->loadSelection(
    $db_storage,
    (string) $get_information['form']['cmd'],
    (string) $get_information['form']['selection']
);

$sticky = ! empty($centreon->optGen['monitoring_ack_sticky']) ? 2 : 1;

$notify = ! empty($centreon->optGen['monitoring_ack_notify']) ? 1 : 0;

$persistent = ! empty($centreon->optGen['monitoring_ack_persistent']) ? 1 : 0;

try {
    $contact_infos = get_contact_information();
    $resultat['result'] = $centreon_provider->submitTicket(
        $db_storage,
        $contact_infos,
        $selected['host_selected'],
        $selected['service_selected']
    );

    if ($resultat['result']['ticket_is_ok'] == 1) {
        $macroName = $centreon_provider->getMacroTicketId();
        do_chain_rules($centreon_provider->getChainRuleList(), $db_storage, $contact_infos, $selected);

        require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
        $oreon = $_SESSION['centreon'];
        $external_cmd = new CentreonExternalCommand();
        $method_external_name = 'set_process_command';
        if (method_exists($external_cmd, $method_external_name) == false) {
            $method_external_name = 'setProcessCommand';
        }

        foreach ($selected['host_selected'] as $value) {
            $fullMacroName = '$_HOST' . $macroName . '$';
            $macroId = getTicketMacroId('host', $fullMacroName,  $value['host_id']);

            if (isset($macroId)) {
                updateMacroValue('host', $resultat['result']['ticket_id'],  $macroId);
            } else {
                insertNewMacroValue('host', $fullMacroName, $resultat['result']['ticket_id'], $value['host_id']);
            }

            $command = 'CHANGE_CUSTOM_HOST_VAR;%s;%s;%s';
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [
                    sprintf(
                        $command,
                        $value['name'],
                        $macroName,
                        $resultat['result']['ticket_id']
                    ),
                    $value['instance_id'],
                ]
            );
            if ($centreon_provider->doAck()) {
                $command = 'ACKNOWLEDGE_HOST_PROBLEM;%s;%s;%s;%s;%s;%s';
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['name'],
                            $sticky,
                            $notify,
                            $persistent,
                            $contact_infos['alias'],
                            'open ticket: ' . $resultat['result']['ticket_id']
                        ),
                        $value['instance_id'],
                    ]
                );
            }
            if ($centreon_provider->doesScheduleCheck()) {
                $command = 'SCHEDULE_FORCED_HOST_CHECK;%s;%d';
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['name'],
                            time()
                        ),
                        $value['instance_id'],
                    ]
                );
            }
        }
        foreach ($selected['service_selected'] as $value) {
            $fullMacroName = '$_SERVICE' . $macroName . '$';
            $macroId = getTicketMacroId('service', $fullMacroName,  $value['service_id']);

            if (isset($macroId)) {
                updateMacroValue('service', $resultat['result']['ticket_id'],  $macroId);
            } else {
                // need to avoid creating macros on services linked to multiple hosts or to hg otherwise we can create many open ticket bugs
                if (!isServiceUnique($value['service_id'])) {
                    insertNewMacroValue('service', $fullMacroName, $resultat['result']['ticket_id'], $value['service_id']);
                }
            }

            $command = 'CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s';
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [
                    sprintf(
                        $command,
                        $value['host_name'],
                        $value['description'],
                        $macroName,
                        $resultat['result']['ticket_id']
                    ),
                    $value['instance_id'],
                ]
            );
            if ($centreon_provider->doAck()) {
                $command = 'ACKNOWLEDGE_SVC_PROBLEM;%s;%s;%s;%s;%s;%s;%s';
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['host_name'],
                            $value['description'],
                            $sticky,
                            $notify,
                            $persistent,
                            $contact_infos['alias'],
                            'open ticket: ' . $resultat['result']['ticket_id']
                        ),
                        $value['instance_id'],
                    ]
                );
            }
            if ($centreon_provider->doesScheduleCheck()) {
                $command = 'SCHEDULE_FORCED_SVC_CHECK;%s;%s;%d';
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['host_name'],
                            $value['description'],
                            time()
                        ),
                        $value['instance_id'],
                    ]
                );
            }
        }

        $external_cmd->write();
    }

    $centreon_provider->clearUploadFiles();
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
}
