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

class GlpiProvider extends AbstractProvider {
    protected $_glpi_connected = 0;
    protected $_glpi_session = null;

    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '10.0.0.0';
        $this->default_data['path'] = '/glpi/plugins/webservices/xmlrpc.php';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;
        $this->default_data['body'] = '
test
        ';
    }
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();
        
        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'urgency', 'Label' => _('Urgency'), 'Value' => '1.0', 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'impact', 'Label' => _('Impact'), 'Value' => '1.0', 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
        );
        $this->default_data['clones']['customList'] = array(
            array('Id' => 'urgency', 'Value' => '1', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '2', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '3', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '4', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '5', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '1', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '2', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '3', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '4', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '5', 'Default' => ''),
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
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('password', "Please set 'Password' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormValue('macro_ticket_time', "Please set 'Macro Ticket Time' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
        
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
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Glpi/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("glpi" => _("Glpi")));
        
        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' . $this->_getFormValue('path') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';
        $body_html = '<textarea rows="8" cols="70" name="body">' . $this->_getFormValue('body') . '</textarea>';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'path' => array('label' => _("Path"), 'html' => $path_html),
            'username' => array('label' => _("Username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->_required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'body' => array('label' => _("Body") . $this->_required_field, 'html' => $body_html)
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
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
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
        $this->_save_config['simple']['body'] = $this->change_html_tags($this->_submitted_config['body']);
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        $this->listEntitiesGlpi();
        $this->listGroupsGlpi();
        $this->listItilCategoriesGlpi();
        $result['code'] = 1;
        $result['message'] = $this->rpc_error;
        
        $this->logoutGlpi();
        return $result;
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());
        
        try {
            $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`) VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($user) . "')";            
            $db_storage->query($query);
            $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
            $result['ticket_is_ok'] = 1;
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $this->assignSubmittedValues($tpl);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $tpl->assign('ticket_id', $result['ticket_id']);
        $tpl->assign('string', $this->change_html_tags($this->rule_data['body'], 0));
        $body = $tpl->fetch('eval.ihtml');
        
        // We send the mail
        $tpl->assign('string', $this->rule_data['from']);
        $from = $tpl->fetch('eval.ihtml');
        $headers = "From: " . $from;
        if (isset($this->rule_data['clones']['headerMail'])) {
            foreach ($this->rule_data['clones']['headerMail'] as $values) {
                $headers .= "\r\n" . $values['Name'] . ':' . $values['Value'];
            }
        }

        $tpl->assign('string', $this->rule_data['subject']);
        $subject = $tpl->fetch('eval.ihtml');
        mail($this->rule_data['to'], $subject, $body, $headers);
        
        return $result;
    }

    protected function setRpcError($error) {
        $this->rpc_error = $error;
    }
    
    protected function requestRpc($method, $args=null) {
        $array_result = array('code' => -1);
        if (is_null($args)) {
            $args = array();
        }
        foreach ($args as $key => $value) {
            if (is_null($value)) {
                unset($args[$key]);
            }
        }
        
        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        $host = $this->rule_data['address'];
        
        $url = '/';
        if (!is_null($this->rule_data['path']) || $this->rule_data['path'] != '') {
            $url = $this->rule_data['path'];
        }
        if ($this->_glpi_connected == 1) {
            $url .= '?session=' . $this->_glpi_session;
        }
        
        $fp = fopen('/tmp/debug.txt', 'a+');
        fwrite($fp, "$proto://$host/$url = " . print_r($args, true));
        $request = xmlrpc_encode_request($method, $args);
        $context = stream_context_create(array('http' => array('method'  => "POST",
                                               'header'  => 'Content-Type: text/xml',
                                               'content' => $request)));
        $file = file_get_contents("$proto://$host/$url", false, $context);
        if (!$file) {
            $this->setRpcError("webservice '$method': no response");
            return $array_result;
        }
        $response = xmlrpc_decode($file);
        if (!is_array($response)) {
            $this->setRpcError("webservice '$method': bad response");
            return $array_result;
        }
        if (xmlrpc_is_fault($response)) {
            $this->setRpcError("webservice '$method' error (" . $response['faultCode'] . "): " . mb_convert_encoding($response['faultString'], 'utf-8'));
            return $array_result;
        }
        
        $array_result['response'] = $response;
        $array_result['code'] = 0;
        return $array_result;
    }
    
    protected function listEntitiesGlpi() {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }
        
        $result = $this->requestRpc('glpi.listEntities', array('start' => 0, 'limit' => 100));
        $fp = fopen('/tmp/debug.txt', 'a+');
        fwrite($fp, print_r($result, true));
        if ($result['code'] == -1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function listGroupsGlpi() {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }
        
        $result = $this->requestRpc('glpi.listGroups', array('start' => 0, 'limit' => 100, 'name' => null));
        $fp = fopen('/tmp/debug.txt', 'a+');
        fwrite($fp, print_r($result, true));
        if ($result['code'] == -1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function listItilCategoriesGlpi() {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }
        
        $result = $this->requestRpc('glpi.listObjects', array('start' => 0, 'limit' => 100, 'name' => null, 
                                                              'itemtype' => 'itilcategory', 'show_label' => 1));
        $fp = fopen('/tmp/debug.txt', 'a+');
        fwrite($fp, print_r($result, true));
        if ($result['code'] == -1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function logoutGlpi() {
        if (!$this->_glpi_connected == 0) {
            return 0;
        }
        $result = $this->requestRpc('glpi.doLogout');
        if ($result['code'] == -1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function loginGlpi() {
        if ($this->_glpi_connected == 1) {
            return 0;
        }
        if (!extension_loaded("xmlrpc")) {
            $this->setRpcError("cannot load xmlrpc extension");
            return -1;
        }
        
        $result = $this->requestRpc('glpi.doLogin', array('login_name' => $this->rule_data['username'], 'login_password' => $this->rule_data['password']));
        if ($result['code'] == -1) {
            return -1;
        }
        
        $this->_glpi_session = $result['response']['session'];
        $this->_glpi_connected = 1;
        return 0;
    }
}
