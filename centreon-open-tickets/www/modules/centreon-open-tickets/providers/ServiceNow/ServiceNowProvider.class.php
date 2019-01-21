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

class ServiceNowProvider extends AbstractProvider {
    protected $accessToken = null;
    protected $refreshToken = null;
    
    const SERVICENOW_LIST_CATEGORY = 20;
    const SERVICENOW_LIST_SUBCATEGORY = 21;
    const SERVICENOW_LIST_IMPACT = 22;
    const SERVICENOW_LIST_URGENCY = 23;
    const SERVICENOW_LIST_ASSIGNMENT_GROUP = 24;
    const SERVICENOW_LIST_ASSIGNED_TO = 25;
    
    const ARG_SHORT_DESCRIPTION = 1;
    const ARG_COMMENTS = 2;
    const ARG_IMPACT = 3;
    const ARG_URGENCY = 4;
    const ARG_CATEGORY = 5;
    const ARG_SUBCATEGORY = 6;
    const ARG_ASSIGNED_TO = 7;
    const ARG_ASSIGNMENT_GROUP = 8;
    
    protected $_internal_arg_name = array(
        self::ARG_SHORT_DESCRIPTION => 'ShortDescription',
        self::ARG_COMMENTS => 'Comments',
        self::ARG_IMPACT => 'Impact',
        self::ARG_URGENCY => 'Urgency',
        self::ARG_CATEGORY => 'Category',
        self::ARG_SUBCATEGORY => 'Subcategory',
        self::ARG_ASSIGNED_TO => 'AssignedTo',
        self::ARG_ASSIGNMENT_GROUP => 'AssignmentGroup',
    );

    /**
    * Set the default extra data
    */
    protected function _setDefaultValueExtra() {
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_SHORT_DESCRIPTION, 'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_COMMENTS, 'Value' => '{$body}'),
            array('Arg' => self::ARG_ASSIGNED_TO, 'Value' => '{$select.servicenow_assigned_to.value}'),
            array('Arg' => self::ARG_ASSIGNMENT_GROUP, 'Value' => '{$select.servicenow_assignment_group.value}'),
            array('Arg' => self::ARG_IMPACT, 'Value' => '{$select.servicenow_impact.value}'),
            array('Arg' => self::ARG_URGENCY, 'Value' => '{$select.servicenow_urgency.value}'),
            array('Arg' => self::ARG_CATEGORY, 'Value' => '{$select.servicenow_category.value}'),
            array('Arg' => self::ARG_SUBCATEGORY, 'Value' => '{$select.servicenow_subcategory.value}'),
        );
    }

    /**
    * Add default data
    */
    protected function _setDefaultValueMain($body_html = 0) {
        parent::_setDefaultValueMain($body_html);

        $this->default_data['url'] = 'https://{$instance_name}.service-now.com/nav_to.do?uri=incident.do?sys_id={$ticket_id}';

        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'servicenow_category', 'Label' => _('Category'), 'Type' => self::SERVICENOW_LIST_CATEGORY, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'servicenow_subcategory', 'Label' => _('Subcategory'), 'Type' => self::SERVICENOW_LIST_SUBCATEGORY, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'servicenow_impact', 'Label' => _('Impact'), 'Type' => self::SERVICENOW_LIST_IMPACT, 'Filter' => '', 'Mandatory' => true),
            array('Id' => 'servicenow_urgency', 'Label' => _('Urgency'), 'Type' => self::SERVICENOW_LIST_URGENCY, 'Filter' => '', 'Mandatory' => true),
            array('Id' => 'servicenow_assignment_group', 'Label' => _('Assignment group'), 'Type' => self::SERVICENOW_LIST_ASSIGNMENT_GROUP, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'servicenow_assigned_to', 'Label' => _('Assigned to'), 'Type' => self::SERVICENOW_LIST_ASSIGNED_TO, 'Filter' => '', 'Mandatory' => '')
        );
    }

    /**
    * Check the configuration form
    */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';

        $this->_checkFormValue('instance_name', 'Please set a instance.');
        $this->_checkFormValue('client_id', 'Please set a OAuth2 client id.');
        $this->_checkFormValue('client_secret', 'Please set a OAuth2 client secret.');
        $this->_checkFormValue('username', 'Please set a OAuth2 username.');
        $this->_checkFormValue('password', 'Please set a OAuth2 password.');

        $this->_checkLists();

        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }

    /**
    * Prepare the extra configuration block
    */
    protected function _getConfigContainer1Extra() {
        $tpl = $this->initSmartyTemplate('providers/ServiceNow/templates');
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("servicenow" => _("Service Now")));
        
        $tpl->assign('webServiceUrl', './api/internal.php');
        
        // Form
        $instance_name_html = '<input size="50" name="instance_name" type="text" value="' . $this->_getFormValue('instance_name') . '" />';
        $client_id_html = '<input size="50" name="client_id" type="text" value="' . $this->_getFormValue('client_id') . '" />';
        $client_secret_html = '<input size="50" name="client_secret" type="password" value="' . $this->_getFormValue('client_secret') . '" autocomplete="off" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';

        $array_form = array(
            'instance_name' => array('label' => _("Instance name") . $this->_required_field, 'html' => $instance_name_html),
            'client_id' => array('label' => _("OAuth Client ID") . $this->_required_field, 'html' => $client_id_html),
            'client_secret' => array('label' => _("OAuth client secret") . $this->_required_field, 'html' => $client_secret_html),
            'username' => array('label' => _("OAuth username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("OAuth password") . $this->_required_field, 'html' => $password_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_SHORT_DESCRIPTION . '">' . _('Short description') . '</options>' .
        '<option value="' . self::ARG_COMMENTS . '">' . _('Comments') . '</options>' .
        '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
        '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</options>' .
        '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</options>' .
        '<option value="' . self::ARG_SUBCATEGORY . '">' . _('Subcategory') . '</options>' .
        '<option value="' . self::ARG_ASSIGNED_TO . '">' . _('Assigned To') . '</options>' .
        '<option value="' . self::ARG_ASSIGNMENT_GROUP . '">' . _('Assignment Group') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }

    protected function _getConfigContainer2Extra() {}

    /**
    * Add specific configuration field
    */
    protected function saveConfigExtra() {
        $this->_save_config['simple']['instance_name'] = $this->_submitted_config['instance_name'];
        $this->_save_config['simple']['client_id'] = $this->_submitted_config['client_id'];
        $this->_save_config['simple']['client_secret'] = $this->_submitted_config['client_secret'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }

    /**
    * Append additional list
    *
    * @return string
    */
    protected function getGroupListOptions() {
        $str = '<option value="' . self::SERVICENOW_LIST_CATEGORY . '">ServiceNow category</options>' .
          '<option value="' . self::SERVICENOW_LIST_SUBCATEGORY . '">ServiceNow subcategory</options>' .
          '<option value="' . self::SERVICENOW_LIST_IMPACT . '">ServiceNow impact</options>' .
          '<option value="' . self::SERVICENOW_LIST_URGENCY . '">ServiceNow urgency</options>' .
          '<option value="' . self::SERVICENOW_LIST_ASSIGNMENT_GROUP . '">ServiceNow assignment group</options>' .
          '<option value="' . self::SERVICENOW_LIST_ASSIGNED_TO . '">ServiceNow assigned to</options>';

        return $str;
    }
    
    protected function assignOtherServiceNow($entry, $method, &$groups_order, &$groups) {
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                     (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        try {
            $listValues = $this->callServiceNow($method, array('Filter' => $entry['Filter']));
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();;
            return 0;
        }
        
        $this->saveSession($entry['Id'], $listValues);
        $groups[$entry['Id']]['values'] = $listValues;
        
        return $listValues;
    }

    /**
    * Add field in popin for create a ticket
    */
    protected function assignOthers($entry, &$groups_order, &$groups) {
        if ($entry['Type'] == self::SERVICENOW_LIST_ASSIGNED_TO) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListSysUser', $groups_order, $groups);
        } else if ($entry['Type'] == self::SERVICENOW_LIST_ASSIGNMENT_GROUP) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListSysUserGroup', $groups_order, $groups);
        } else if ($entry['Type'] == self::SERVICENOW_LIST_IMPACT) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListImpact', $groups_order, $groups);
        } else if ($entry['Type'] == self::SERVICENOW_LIST_URGENCY) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListUrgency', $groups_order, $groups);
        } else if ($entry['Type'] == self::SERVICENOW_LIST_CATEGORY) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListCategory', $groups_order, $groups);
        } else if ($entry['Type'] == self::SERVICENOW_LIST_SUBCATEGORY) {
            $listValues = $this->assignOtherServiceNow($entry, 'getListSubcategory', $groups_order, $groups);
        }
    }

    /**
     * Create a ticket
     *
     * @param CentreonDB $db_storage The centreon_storage database connection
     * @param string $contact The contact who open the ticket
     * @param array $host_problems The list of host issues link to the ticket
     * @param array $service_problems The list of service issues link to the ticket
     * @param array $extra_ticket_arguments Extra arguments
     * @return array The status of action (
     *  'code' => int,
     *  'message' => string
     * )
     */
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
            'ticket_is_ok' => 0, 'ticket_time' => time());
            
        $tpl = $this->initSmartyTemplate();

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
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
                
                $ticket_arguments[$this->_internal_arg_name[$value['Arg']]] = $result_str;
            }
        }

        /* Create ticket */
        try {
            $data = $this->_submitted_config;
            $resultInfo = $this->callServiceNow('createTicket', $data);
        } catch (\Exception $e) {
            $result['ticket_error_message'] = 'Error during create ServiceNow ticket';
        }
        
        $this->saveHistory($db_storage, $result, array('contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems, 
            'ticket_value' => $resultInfo['sysTicketId'], 'subject' => $ticket_arguments[self::ARG_SHORT_DESCRIPTION], 
            'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode(array('arguments' => $ticket_arguments, 'dynamic_fields' => $ticket_dynamic_fields))));
        
        return $result;
    }

    /**
      * Validate the popup for submit a ticket
      */
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');

        $this->validateFormatPopupLists($result);
        return $result;
    }

    /**
     * Get a a access token
     *
     * @param string $instance The ServiceNow instance name
     * @param string $clientId The ServiceNow OAuth client ID
     * @param string $clientSecret The ServiceNow OAuth client secret
     * @param string $username The ServiceNow OAuth username
     * @param string $password The ServiceName OAuth password
     * @return array The tokens
     */
    static protected function getAccessToken($instance, $clientId, $clientSecret, $username, $password) {
        $url = 'https://' . $instance . '.service-now.com/oauth_token.do';
        $postfields = 'grant_type=password';
        $postfields .= '&client_id=' . urlencode($clientId);
        $postfields .= '&client_secret=' . urlencode($clientSecret);
        $postfields .= '&username=' . urlencode($username);
        $postfields .= '&password=' . urlencode($password);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        /* @todo proxy */

        $returnJson = curl_exec($ch);
        if ($returnJson === false) {
            throw new \Exception(curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status !== 200) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $return = json_decode($returnJson, true);
        return array(
            'accessToken' => $return['access_token'],
            'refreshToken' => $return['refresh_token']
        );
    }

    /**
     * Test the service
     *
     * @param array The post information from webservice
     * @return boolean
     */
    static public function test($info) {
        /* Test arguments */
        if (!isset($info['instance']) ||
            !isset($info['clientId']) ||
            !isset($info['clientSecret']) ||
            !isset($info['username']) ||
            !isset($info['password'])) {
            throw new \Exception('Missing arguments.');
        }

        try {
            $tokens = self::getAccessToken(
                $info['instance'],
                $info['clientId'],
                $info['clientSecret'],
                $info['username'],
                $info['password']
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Refresh the access token
     *
     * @return string The access token
     */
    protected function refreshToken() {
        $instance = $this->_getFormValue('instance_name');
        $url = 'https://' . $instance . '.service-now.com/oauth_token.do';
        $postfields = 'grant_type=refresh_token';
        $postfields .= '&client_id=' . urlencode($this->_getFormValue('client_id'));
        $postfields .= '&client_secret=' . urlencode($this->_getFormValue('client_secret'));
        $postfields .= '&refresh_token=' . $this->refreshToken;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        /* @todo proxy */

        $returnJson = curl_exec($ch);
        if ($returnJson === false) {
            throw new \Exception(curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status !== 200) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $return = json_decode($returnJson, true);
        $this->accessToken = $return['access_token'];
        $this->refreshToken = $return['refresh_token'];
    }

    /**
     * Call a service now Rest webservices
     */
    protected function callServiceNow($methodName, $params = array()) {
        if (is_null($this->refreshToken)) {
            $tokens = self::getAccessToken(
                $this->_getFormValue('instance_name'),
                $this->_getFormValue('client_id'),
                $this->_getFormValue('client_secret'),
                $this->_getFormValue('username'),
                $this->_getFormValue('password')
            );
            $this->accessToken = $tokens['access_token'];
            $this->refreshToken = $tokens['refresh_token'];
        } elseif (is_null($this->accessToken)) {
            $this->refreshToken();
        }

        return $this->$methodName($params, $this->accessToken);
    }

    /**
     * Execute the http request
     *
     * @param string $uri The URI to call
     * @param string $accessToken The OAuth access token
     * @param string $method The http method
     * @param string $data The data to send, used in method POST, PUT, PATCH
     */
    protected function runHttpRequest($uri, $accessToken, $method = 'GET', $data = null) {
        $instance = $this->_getFormValue('instance_name');
        $url = 'https://' . $instance . '.service-now.com' . $uri;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (!is_null($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $returnJson = curl_exec($ch);
        if ($returnJson === false) {
            throw new \Exception(curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status < 200 && $status >= 300) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        return json_decode($returnJson, true);
    }

    /**
     * Get the list of user from ServiceNow for Assigned to
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of user
     */
    protected function getListSysUser($param, $accessToken) {
        $uri = '/api/now/table/sys_user';
        $result = $this->runHttpRequest($uri, $accessToken);

        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['active'] === 'true') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['sys_id']] = $entry['name'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['name'])) {
                    $selected[$entry['sys_id']] = $entry['name'];
                }
            }
        }

        return $selected;
    }

    /**
     * Get the list of user group from ServiceNow for Assigned to
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of user group
     */
    protected function getListSysUserGroup($param, $accessToken) {
        $uri = '/api/now/table/sys_user_group';
        $result = $this->runHttpRequest($uri, $accessToken);

        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['active'] === 'true') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['sys_id']] = $entry['name'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['name'])) {
                    $selected[$entry['sys_id']] = $entry['name'];
                }
            }
        }

        return $selected;
    }

    /**
     * Getting the list of impact from ServiceNow
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of impact
     */
    protected function getListImpact($params, $accessToken) {
        $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHtask%5EelementSTARTSWITHimpact';
        $result = $this->runHttpRequest($uri, $accessToken);

        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['inactive'] === 'false') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['value']] = $entry['label'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['label'])) {
                    $selected[$entry['value']] = $entry['label'];
                }
            }
        }
        
        return $selected;
    }

    /**
     * Getting the list of urgency from ServiceNow
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of urgency
     */
    protected function getListUrgency($params, $accessToken) {
        $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHseverity';
        $result = $this->runHttpRequest($uri, $accessToken);
        
        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['inactive'] === 'false') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['value']] = $entry['label'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['label'])) {
                    $selected[$entry['value']] = $entry['label'];
                }
            }
        }
        
        return $selected;
    }

    /**
     * Getting the list of category from ServiceNow
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of category
     */
    protected function getListCategory($params, $accessToken) {
        $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHcategory';
        $result = $this->runHttpRequest($uri, $accessToken);

        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['inactive'] === 'false') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['value']] = $entry['label'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['label'])) {
                    $selected[$entry['value']] = $entry['label'];
                }
            }
        }
        
        return $selected;
    }

    /**
     * Getting the list of subcategory from ServiceNow
     *
     * @param array $param The parameters for filter (no used)
     * @param string $accessToken The access token
     * @return array The list of subcategory
     */
    protected function getListSubcategory($params, $accessToken) {
        $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHsubcategory';
        $result = $this->runHttpRequest($uri, $accessToken);

        $selected = array();
        foreach ($result['result'] as $entry) {
            if ($entry['inactive'] === 'false') {
                if (!isset($param['Filter']) || is_null($param['Filter']) || $param['Filter'] == '') {
                    $selected[$entry['value']] = $entry['label'];
                }
                if (preg_match('/' . $param['Filter'] . '/', $entry['label'])) {
                    $selected[$entry['value']] = $entry['label'];
                }
            }
        }
        
        return $selected;
    }

    protected function createTicket($params, $accessToken) {
        $uri = '/api/now/v1/table/incident';
        $impacts = explode('_', $ticket_arguments[self::ARG_IMPACT], 2);
        $urgencies = explode('_', $ticket_arguments[self::ARG_URGENCY], 2);
        $data = array(
            'impact' => $impacts[0],
            'urgency' => $urgencies[0],
            'short_description' => $ticket_arguments[self::ARG_SHORT_DESCRIPTION]
        );
        if (isset($ticket_arguments[self::ARG_CATEGORY])) {
            $category = explode('_', $ticket_arguments[self::ARG_CATEGORY], 2);
            $data['category'] = $category[0];
        }
        if (isset($ticket_arguments[self::ARG_SUBCATEGORY])) {
            $subcategory = explode('_', $ticket_arguments[self::ARG_SUBCATEGORY], 2);
            $data['subcategory'] = $subcategory[0];
        }
        if (isset($ticket_arguments[self::ARG_ASSIGNED_TO])) {
            $assignedTo = explode('_', $ticket_arguments[self::ARG_ASSIGNED_TO], 2);
            $data['assigned_to'] = $assignedTo[0];
        }
        if (isset($ticket_arguments[self::ARG_ASSIGNMENT_GROUP])) {
            $assignmentGroup = explode('_', $ticket_arguments[self::ARG_ASSIGNMENT_GROUP], 2);
            $data['assignment_group'] = $assignmentGroup[0];
        }
        if (isset($ticket_arguments[self::ARG_BODY])) {
            $data['comments'] = $ticket_arguments[self::ARG_BODY];
        }
        $result = $this->runHttpRequest($uri, $accessToken, 'POST', $data);
        return array(
            'sysTicketId' => $result['result']['sys_id'],
            'ticketId' => $result['result']['number']
        );
    }
}
