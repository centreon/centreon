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

class RequestTracker2Provider extends AbstractProvider
{
    protected $proxy_enabled = 1;

    public const RT_QUEUE_TYPE = 10;
    public const RT_CUSTOMFIELD_TYPE = 11;

    public const ARG_QUEUE = 1;
    public const ARG_SUBJECT = 2;
    public const ARG_REQUESTOR = 3;
    public const ARG_CC = 4;
    public const ARG_CONTENT = 5;

    protected $internal_arg_name = array(
        self::ARG_QUEUE => 'Queue',
        self::ARG_SUBJECT => 'Priority',
        self::ARG_REQUESTOR => 'Requestor',
        self::ARG_CC => 'Cc',
        self::ARG_CONTENT => 'Content',
    );

    function __destruct()
    {
    }

    /**
     * Set default extra value
     *
     * @return void
     */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/REST/2.0/';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_SUBJECT,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/' .
                    'Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_REQUESTOR, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_QUEUE, 'Value' => '{$select.rt_queue.value}'),
        );
    }

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain(0);
        $this->default_data['url'] = 'http://{$address}/SelfService/Display.html?id={$ticket_id}';
        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'rt_queue',
                'Label' => _('Rt queue'),
                'Type' => self::RT_QUEUE_TYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
        );
    }

    /**
     * Check form
     *
     * @return a string
     */
    protected function checkConfigForm()
    {
        $this->check_error_message = '';
        $this->check_error_message_append = '';
        $this->checkFormValue('address', "Please set 'Address' value");
        $this->checkFormValue('path', "Please set 'Path' value");
        $this->checkFormValue('timeout', "Please set 'Timeout' value");
        $this->checkFormValue('token', "Please set 'Token' value");
        $this->checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->checkFormInteger('timeout', "'Timeout' must be a number");
        $this->checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/RequestTracker2/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("rt" => _("RequestTracker")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' .
            $this->getFormValue('path') . '" />';
        $token_html = '<input size="50" name="token" type="password" value="' .
            $this->getFormValue('token') . '" autocomplete="off" />';
        $https_html = '<div class="md-checkbox md-checkbox-inline">' .
            '<input type="checkbox" id="https" name="https" value="yes" ' .
            ($this->getFormValue('https') === 'yes' ? 'checked' : '') . '/>' .
            '<label class="empty-label" for="https"></label></div>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->required_field, 'html' => $address_html),
            'path' => array('label' => _("Path") . $this->required_field, 'html' => $path_html),
            'token' => array('label' => _("Token") . $this->required_field, 'html' => $token_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
            'mappingticketdynamicfield' => array('label' => _("Mapping ticket dynamic field")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">' .
        '<option value="' . self::ARG_QUEUE . '">' . _('Queue') . '</options>' .
        '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</options>' .
        '<option value="' . self::ARG_REQUESTOR . '">' . _('Requestor') . '</options>' .
        '<option value="' . self::ARG_CC . '">' . _('Cc') . '</options>' .
        '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        // mapping Ticket DynamicField
        $mappingTicketDynamicFieldName_html = '<input id="mappingTicketDynamicFieldName_#index#" ' .
            'name="mappingTicketDynamicFieldName[#index#]" size="30"  type="text" />';
        $mappingTicketDynamicFieldValue_html = '<input id="mappingTicketDynamicFieldValue_#index#" ' .
            'name="mappingTicketDynamicFieldValue[#index#]" size="30"  type="text" />';
        $array_form['mappingTicketDynamicField'] = array(
            array('label' => _("Name"), 'html' => $mappingTicketDynamicFieldName_html),
            array('label' => _("Value"), 'html' => $mappingTicketDynamicFieldValue_html),
        );

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
        $this->config['clones']['mappingTicketDynamicField'] = $this->getCloneValue('mappingTicketDynamicField');
    }

    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function getConfigContainer2Extra()
    {
    }

    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['path'] = $this->submitted_config['path'];
        $this->save_config['simple']['token'] = $this->submitted_config['token'];
        $this->save_config['simple']['https'] = (isset($this->submitted_config['https'])
            && $this->submitted_config['https'] == 'yes')
            ? $this->submitted_config['https'] : '';
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
        $this->save_config['clones']['mappingTicketDynamicField'] = $this->getCloneSubmitted(
            'mappingTicketDynamicField',
            array('Name', 'Value')
        );
    }

    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::RT_QUEUE_TYPE . '">Rt queue</options>' . 
               '<option value="' . self::RT_CUSTOMFIELD_TYPE . '">Rt custom field</options>';
        return $str;
    }

    protected function assignRtQueue($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        list($code, $items) = $this->listQueueRt();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = [];
        foreach ($items as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['Name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['Name'])) {
                $result[$row['id']] = $this->to_utf8($row['Name']);
            }
        }

        $saveResults = ['results' => $items];
        $this->saveSession($entry['Id'], $saveResults);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignRtCustomField($entry, &$groups_order, &$groups)
    {
        // $entry['Filter']: to get the custom list
        list($code, $items) = $this->listCustomFieldRt($entry['Filter']);

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = [];
        foreach ($items as $row) {
            $result[$row['id']] = $this->to_utf8($row['value']);
        }

        $saveResults = ['results' => $items];
        $this->saveSession($entry['Id'], $saveResults);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::RT_QUEUE_TYPE) {
            $this->assignRtQueue($entry, $groups_order, $groups);
        } else if ($entry['Type'] == self::RT_CUSTOMFIELD_TYPE) {
            $this->assignRtCustomField($entry, $groups_order, $groups);
        }
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);
        return $result;
    }

    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id)
    {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {
                $session_name = $value['Id'];
            }
        }

        if (is_null($session_name) && $selected_id == -1) {
            return [];
        }
        if ($selected_id == -1) {
            return ['id' => null, 'value' => null];
        }

        $result = $this->getSession($session_name);

        if (is_null($result)) {
            return [];
        }

        foreach ($result['results'] as $value) {
            if ($value['id'] == $selected_id) {
                return $value;
            }
        }

        return [];
    }

    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems)
    {
        $result = array(
            'ticket_id' => null,
            'ticket_error_message' => null,
            'ticket_is_ok' => 0,
            'ticket_time' => time()
        );

        $tpl = $this->initSmartyTemplate();

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);

        $this->assignSubmittedValues($tpl);

        $ticket_arguments = array();
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $result_str = $tpl->fetch('eval.ihtml');

                if ($result_str == '') {
                    $result_str = null;
                }

                $ticket_arguments[$this->internal_arg_name[$value['Arg']]] = $result_str;
            }
        }
        $ticket_dynamic_fields = array();
        if (isset($this->rule_data['clones']['mappingTicketDynamicField'])) {
            foreach ($this->rule_data['clones']['mappingTicketDynamicField'] as $value) {
                if ($value['Name'] == '' ||  $value['Value'] == '') {
                    continue;
                }
                $array_tmp = array();
                $tpl->assign('string', $value['Name']);
                $array_tmp = array('Name' => $tpl->fetch('eval.ihtml'));

                $tpl->assign('string', $value['Value']);
                $array_tmp['Value'] = $tpl->fetch('eval.ihtml');

                $ticket_dynamic_fields[] = $array_tmp;
            }
        }

        $code = $this->createTicketRt($ticket_arguments, $ticket_dynamic_fields);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }

        $this->saveHistory(
            $db_storage,
            $result,
            array(
                'contact' => $contact,
                'host_problems' => $host_problems,
                'service_problems' => $service_problems,
                'ticket_value' => $this->call_response['id'],
                'subject' => $ticket_arguments['Subject'],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array(
                        'arguments' => $ticket_arguments,
                        'dynamic_fields' => $ticket_dynamic_fields
                    )
                )
            )
        );

        return $result;
    }

    /*
     *
     * REST API
     *
     */
    protected function setWsError($error)
    {
        $this->ws_error = $error;
    }

    protected function listQueueRt()
    {
        $items = array();
        $page = 1;
        $per_page = 100;
        while (1) {
            $query = 'queues/all?fields=id,Name&per_page=' . $per_page . '&page=' . $page;
            if ($this->callRest($query) == 1) {
                return [-1, $items];
            }

            $items = array_merge($items, $this->call_response['items']);
            if ($this->call_response['total'] < ($page * $per_page)) {
                break;
            }

            $page++;
        }

        return [0, $items];
    }

    protected function listCustomFieldRt($filter)
    {
        $items = [];
        if (is_null($filter) || $filter === '') {
            $this->setWsError("please set filter for the list");
            return [-1, $items];
        }

        $argument = [['field' => 'Name', 'operator' => 'LIKE', 'value' => $filter]];
        if ($this->callRest('customfields?fields=Name', $argument) == 1) {
            return [-1, $items];
        }

        $customField = array_shift($this->call_response['items']);
        /*
         * Format:
         *    {
         *       "id": 17,
         *       "type": "customfield",
         *       "Name": "Site"
         *    }
         */
        if (is_null($customField)) {
            $this->setWsError("cannot get a custom field with filter '$filter'");
            return [-1, $items];
        }

        if ($this->callRest('customfield/' . $customField['id']) == 1) {
            return [-1, $items];
        }

        $duplicated = [];
        foreach ($this->call_response['Values'] as $value) {
            if (isset($duplicated[$value])) {
                continue;
            }

            $items[] = ['id' => $value, 'value' => $value, 'customFieldId' => $customField['id']];
            $duplicated[$value] = 1;
        }

        return [0, $items];
    }

    protected function createTicketRt($ticket_arguments, $ticket_dynamic_fields)
    {
        $argument = array(
            'Queue' => $ticket_arguments[$this->internal_arg_name[self::ARG_QUEUE]],
            'Subject' => $ticket_arguments[$this->internal_arg_name[self::ARG_SUBJECT]],
            'Requestor' => $ticket_arguments[$this->internal_arg_name[self::ARG_REQUESTOR]],
            'Content' => $ticket_arguments[$this->internal_arg_name[self::ARG_CONTENT]],
        );

        if (
            isset($ticket_arguments[$this->internal_arg_name[self::ARG_CC]])
            && $ticket_arguments[$this->internal_arg_name[self::ARG_CC]] != ''
        ) {
            $argument['Cc'] = $ticket_arguments[$this->internal_arg_name[self::ARG_CC]];
        }

        if (count($ticket_dynamic_fields) > 0) {
            $argument['CustomFields'] = [];
            foreach ($ticket_dynamic_fields as $field) {
                $argument['CustomFields'][$field['Name']] = $field['Value'];
            }
        }

        $fp = fopen('/var/opt/rh/rh-php71/log/php-fpm/debug.txt', 'a+');
        fwrite($fp, print_r($argument, true));
        fclose($fp);
        if ($this->callRest('ticket', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function callRest($function, $argument = null)
    {
        if (!extension_loaded("curl")) {
            $this->setWsError("cannot load curl extension");
            return 1;
        }

        $this->call_response = null;

        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }

        $base_url = $proto . '://' . $this->rule_data['address'] . $this->rule_data['path'] . $function;
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }

        $method = 'GET';
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        $headers[] = 'Authorization: token ' . $this->getFormValue('token', false);
        if (!is_null($argument)) {
            $argument_json = json_encode($argument);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
            $headers[] = 'Content-Length: ' . strlen($argument_json);
            $method = 'POST';
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        self::setProxy(
            $ch,
            array(
                'proxy_address' => $this->getFormValue('proxy_address', false),
                'proxy_port' => $this->getFormValue('proxy_port', false),
                'proxy_username' => $this->getFormValue('proxy_username', false),
                'proxy_password' => $this->getFormValue('proxy_password', false),
            )
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if ($result == false) {
            $this->setWsError(curl_error($ch));
            curl_close($ch);
            return 1;
        }

        // 401 it's an error (unauthorized maybe)
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!preg_match_all('/^2/', $http_code)) {
            curl_close($ch);
            $this->setWsError($http_code . ' code error');
            return 1;
        }

        $decoded_result = json_decode($result, true);
        if (is_null($decoded_result) || $decoded_result == false) {
            $this->setWsError($result);
            return 1;
        }

        curl_close($ch);

        $this->call_response = $decoded_result;
        return 0;
    }
}
