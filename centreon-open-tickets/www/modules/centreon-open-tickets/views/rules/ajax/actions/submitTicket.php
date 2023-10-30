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

function get_contact_information()
{
    global $db, $centreon_bg;

    $result = array('alias' => '', 'email' => '', 'name' => '');
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

    require_once $centreon_open_tickets_path . 'providers/' . $provider_name .
        '/' . $provider_name . 'Provider.class.php';
    $classname = $provider_name . 'Provider';
    $provider_class = new $classname(
        $rule,
        $centreon_path,
        $centreon_open_tickets_path,
        $rule_id,
        $get_information['form'],
        $provider['provider_id']
    );
    return $provider_class;
}

function do_chain_rules($rule_list, $db_storage, $contact_infos, $selected)
{
    $loop_check = array();

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

if (is_null($provider_name)
    || !file_exists(
        $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php'
    )
) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';
    return ;
}
if (!isset($get_information['form']['widgetId'])
    || is_null($get_information['form']['widgetId'])
    || $get_information['form']['widgetId'] == ''
) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set widgetId';
    return ;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname(
    $rule,
    $centreon_path,
    $centreon_open_tickets_path,
    $get_information['rule_id'],
    $get_information['form'],
    $get_information['provider_id']
);
$centreon_provider->setWidgetId($get_information['form']['widgetId']);
$centreon_provider->setUniqId($get_information['form']['uniqId']);

// We get Host or Service
require_once $centreon_path . 'www/class/centreonDuration.class.php';

$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new centreonDBManager('centstorage');

$selected = $rule->loadSelection(
    $db_storage,
    $get_information['form']['cmd'],
    $get_information['form']['selection']
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
        do_chain_rules($centreon_provider->getChainRuleList(), $db_storage, $contact_infos, $selected);

        require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
        $oreon = $_SESSION['centreon'];
        $external_cmd = new CentreonExternalCommand($oreon);
        $method_external_name = 'set_process_command';
        if (method_exists($external_cmd, $method_external_name) == false) {
            $method_external_name = 'setProcessCommand';
        }

        foreach ($selected['host_selected'] as $value) {
            $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [
                    sprintf(
                        $command,
                        $value['name'],
                        $centreon_provider->getMacroTicketId(),
                        $resultat['result']['ticket_id']
                    ),
                    $value['instance_id']
                ]
            );
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_HOST_PROBLEM;%s;%s;%s;%s;%s;%s";
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
                        $value['instance_id']
                    ]
                );
            }
            if ($centreon_provider->doesScheduleCheck()) {
                $command = "SCHEDULE_FORCED_HOST_CHECK;%s;%d";
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['name'],
                            time()
                        ),
                        $value['instance_id']
                    ]
                );
            }
        }
        foreach ($selected['service_selected'] as $value) {
            $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
            call_user_func_array(
                [$external_cmd, $method_external_name],
                [
                    sprintf(
                        $command,
                        $value['host_name'],
                        $value['description'],
                        $centreon_provider->getMacroTicketId(),
                        $resultat['result']['ticket_id']
                    ),
                    $value['instance_id']
                ]
            );
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_SVC_PROBLEM;%s;%s;%s;%s;%s;%s;%s";
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
                        $value['instance_id']
                    ]
                );
            }
            if ($centreon_provider->doesScheduleCheck()) {
                $command = "SCHEDULE_FORCED_SVC_CHECK;%s;%s;%d";
                call_user_func_array(
                    [$external_cmd, $method_external_name],
                    [
                        sprintf(
                            $command,
                            $value['host_name'],
                            $value['description'],
                            time()
                        ),
                        $value['instance_id']
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
