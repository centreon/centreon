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

class OtrsProvider extends AbstractProvider {
    protected $_otrs_connected = 0;
    protected $_otrs_session = null;
    
    const OTRS_QUEUE_TYPE = 10;
    const OTRS_PRIORITY_TYPE = 11;
    const OTRS_STATE_TYPE = 12;
    const OTRS_TYPE_TYPE = 13;
    
    const ARG_QUEUE = 1;
    const ARG_PRIORITY = 2;
    const ARG_STATE = 3;
    const ARG_TYPE = 4;
    const ARG_CUSTOMERUSER = 5;
    const ARG_SUBJECT = 6;
    const ARG_BODY = 7;
    const ARG_FROM = 8;
    
    protected $_internal_arg_name = array(
        self::ARG_QUEUE => 'Queue',
        self::ARG_PRIORITY => 'Priority',
        self::ARG_STATE => 'State',
        self::ARG_TYPE => 'Type',
        self::ARG_CUSTOMERUSER => 'CustomerUser',
        self::ARG_SUBJECT => 'Subject',
        self::ARG_BODY => 'Body',
        self::ARG_FROM => 'From',
    );

    function __destruct() {
    }
    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/otrs';
        $this->default_data['rest_link'] = 'nph-genericinterface.pl/Webservice';
        $this->default_data['webservice_name'] = 'centreon';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;
        $this->default_data['body'] = '
{$user.alias} open ticket at {$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}

{$custom_message}

{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_selected_lists.ihtml" separator=""}

{if $host_selected|@count gt 0}
{foreach from=$host_selected item=host}
Host: {$host.name}
State: {$host.state_str}
Duration: {$host.last_hard_state_change_duration}
Output: {$host.output|substr:0:1024}

{/foreach}
{/if}

{if $service_selected|@count gt 0} 
{foreach from=$service_selected item=service}
Host: {$service.host_name}
Service: {$service.description}
State: {$service.state_str}
Duration: {$service.last_hard_state_change_duration}
Output: {$service.output|substr:0:1024}
{/foreach}
{/if}
';
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_SUBJECT, 'Value' => 'Centreon problem'),
            array('Arg' => self::ARG_BODY, 'Value' => '{$body}'),
            array('Arg' => self::ARG_FROM, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_QUEUE, 'Value' => '{$select.orts_queue.id}'),
            array('Arg' => self::ARG_PRIORITY, 'Value' => '{$select.otrs_priority.id}'),
            array('Arg' => self::ARG_STATE, 'Value' => '{$select.otrs_state.id}'),
            array('Arg' => self::ARG_TYPE, 'Value' => '{$select.otrs_type.id}'),
            array('Arg' => self::ARG_CUSTOMERUSER, 'Value' => '{$select.otrs_customeruser.id}'),
        );
    }
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();
        
        $this->default_data['url'] = 'http://{$address}/index.pl?Action=AgentTicketZoom;TicketNumber={$ticket_id}';        
        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'otrs_queue', 'Label' => _('Otrs queue'), 'Type' => self::OTRS_QUEUE_TYPE, 'Filter' => '', 'Mandatory' => 'yes'),
            array('Id' => 'otrs_priority', 'Label' => _('Otrs priority'), 'Type' => self::OTRS_PRIORITY_TYPE, 'Filter' => '', 'Mandatory' => 'yes'),
            array('Id' => 'otrs_state', 'Label' => _('Otrs state'), 'Type' => self::OTRS_STATE_TYPE, 'Filter' => '', 'Mandatory' => 'yes'),
            array('Id' => 'otrs_type', 'Label' => _('Otrs type'), 'Type' => self::OTRS_TYPE_TYPE, 'Filter' => '', 'Mandatory' => ''),
        );
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('address', "Please set 'Address' value");
        $this->_checkFormValue('rest_link', "Please set 'Rest Link' value");
        $this->_checkFormValue('webservice_name', "Please set 'Webservice Name' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('password', "Please set 'Password' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormValue('macro_ticket_time', "Please set 'Macro Ticket Time' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Otrs/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("otrs" => _("OTRS")));
        
        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' . $this->_getFormValue('path') . '" />';
        $rest_link_html = '<input size="50" name="rest_link" type="text" value="' . $this->_getFormValue('rest_link') . '" />';
        $webservice_name_html = '<input size="50" name="webservice_name" type="text" value="' . $this->_getFormValue('webservice_name') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';
        $body_html = '<textarea rows="8" cols="70" name="body">' . $this->_getFormValue('body') . '</textarea>';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'path' => array('label' => _("Path"), 'html' => $path_html),
            'rest_link' => array('label' => _("Rest link") . $this->_required_field, 'html' => $rest_link_html),
            'webservice_name' => array('label' => _("Webservice name") . $this->_required_field, 'html' => $webservice_name_html),
            'username' => array('label' => _("Username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->_required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'body' => array('label' => _("Body") . $this->_required_field, 'html' => $body_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_QUEUE . '">' . _('Queue') . '</options>' .
        '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</options>' .
        '<option value="' . self::ARG_STATE . '">' . _('State') . '</options>' .
        '<option value="' . self::ARG_TYPE . '">' . _('Type') . '</options>' .
        '<option value="' . self::ARG_CUSTOMERUSER . '">' . _('Customer user') . '</options>' .
        '<option value="' . self::ARG_FROM . '">' . _('From') . '</options>' .
        '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</options>' .
        '<option value="' . self::ARG_BODY . '">' . _('Body') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['path'] = $this->_submitted_config['path'];
        $this->_save_config['simple']['rest_link'] = $this->_submitted_config['rest_link'];
        $this->_save_config['simple']['webservice_name'] = $this->_submitted_config['webservice_name'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
        $this->_save_config['simple']['body'] = $this->change_html_tags($this->_submitted_config['body']);
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }
    
    protected function getGroupListOptions() {        
        $str = '<option value="' . self::OTRS_QUEUE_TYPE . '">Otrs queue</options>' .
        '<option value="' . self::OTRS_PRIORITY_TYPE . '">Otrs priority</options>' .
        '<option value="' . self::OTRS_STATE_TYPE . '">Otrs state</options>' .
        '<option value="' . self::OTRS_TYPE_TYPE . '">Otrs type</options>';
        return $str;
    }
    
    protected function assignOtrsQueue($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listQueueOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }
        
        
        // We'll need some change. Despite the code.
        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['completename']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['completename'])) {
                $result[$row['id']] = $this->to_utf8($row['completename']);
            }
        }
        
        $this->saveSession('otrs_queue', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
        
    protected function assignOthers($entry, &$groups_order, &$groups) {
        if ($entry['Type'] == self::OTRS_QUEUE_TYPE) {
            $this->assignOtrsQueue($entry, $groups_order, $groups);
        }
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        return $result;
    }
    
    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id) {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {                    
                if ($value['Type'] == self::OTRS_QUEUE_TYPE) {
                    $session_name = 'otrs_queue';
                }
            }
        }
        
        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }
        
        $result = $this->getSession($session_name);
        
        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value)  {
            if ($value['id'] == $selected_id) {                
                return $value;
            }
        }
        
        return array();
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $this->assignSubmittedValues($tpl);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
 
        $tpl->assign('string', $this->change_html_tags($this->rule_data['body'], 0));
        $content = $tpl->fetch('eval.ihtml');
        
        $tpl->assign('body', $content);
        
        $ticket_arguments = array();
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $result_str = $tpl->fetch('eval.ihtml');
                
                if ($result_str == '') {
                    $result_str = null;
                }
                
                $ticket_arguments[$this->_internal_arg_name[$value['Arg']]] = $result_str;
            }
        }
        
        $code = $this->createTicketOtrs($ticket_arguments);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }
        
        try {
            $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`, `ticket_value`) VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($contact['name']) . "', '" . 
                $db_storage->escape($this->_otrs_call_response['TicketNumber']) . "')";            
            $db_storage->query($query);
            $result['ticket_id'] = $this->_otrs_call_response['TicketNumber'];
            $result['ticket_is_ok'] = 1;
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
        }
        
        return $result;
    }

    /*
     *
     * REST API
     *
     */
    protected function setWsError($error) {
        $this->ws_error = $error;
    }
    
    protected function listQueueOtrs() {
        if ($this->_otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }
        
        // No Queue Request yet!
        $this->setWsError("no queue method");
        return -1;
        
        
        return 0;
    }
    
    protected function createTicketOtrs($ticket_arguments) {
        if ($this->_otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }
        
        $argument = array(
            'SessionID' => $this->_otrs_session, 
            'Ticket' => array(
                'Title' => $ticket_arguments['Subject'],
                //'QueueID' => xxx,
                'Queue' => 'Raw', // $ticket_arguments['Queue']
                //'StateID' => xxx,
                'State' => 'open', // $ticket_arguments['State']
                //'PriorityID' => xxx,
                'Priority' => '3 normal', // $ticket_arguments['Priority']
                //'TypeID' => 123, 
                //'Type' => 'ppp', // $ticket_arguments['Type']
                //'OwnerID'       => 123,
                //'Owner'         => 'some user login',
                //'ResponsibleID' => 123,
                //'Responsible'   => 'some user login',
                'CustomerUser'   => 'jd',
            ),
            'Article' => array(
                'From' => 'toto@plop.fr', //$ticket_arguments['From'], // Must be an email
                'Subject' => $ticket_arguments['Subject'],
                'Body' => $ticket_arguments['Body'],
                'ContentType' => 'text/plain; charset=utf8', 
            )
        );

        if ($this->callRest('TicketCreate', $argument) == 1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function loginOtrs() {
        if ($this->_otrs_connected == 1) {
            return 0;
        }

        if (!extension_loaded("curl")) {
            $this->setWsError("cannot load curl extension");
            return -1;
        }
        
        $argument = array('UserLogin' => $this->rule_data['username'], 'Password' => $this->rule_data['password']);
        if ($this->callRest('SessionCreate', $argument) == 1) {
            return -1;
        }
        
        $this->_otrs_session = $this->_otrs_call_response['SessionID'];
        $this->_otrs_connected = 1;
        return 0;
    }
    
    protected function callRest($function, $argument) {
        $this->_otrs_call_response = null;
       
        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        
        $argument_json = json_encode($argument);
        $base_url = $proto . '://' . $this->rule_data['address'] . $this->rule_data['path'] . '/' . $this->rule_data['rest_link'] . '/' . $this->rule_data['webservice_name'] . '/' . $function . '/';
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($argument_json))
        );
        $result = curl_exec($ch);
        if ($result == false) {
            curl_close($ch);
            $this->setWsError(curl_error());
            return 1;
        }
                
        $decoded_result = json_decode($result, TRUE);
        if (is_null($decoded_result) || $decoded_result == false) {
            $this->setWsError($result);
            return 1;
        }
        
        curl_close($ch);
        
        if (isset($decoded_result['Error'])) {
            $this->setWsError($decoded_result['Error']['ErrorMessage']);
            return 1;
        }
        
        $this->_otrs_call_response = $decoded_result;
        return 0;
    }
}
