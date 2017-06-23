<?php
/*
 * Copyright 2017 Centreon (http://www.centreon.com/)
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

class EasyvistaProvider extends AbstractProvider {
    protected $_attach_files = 1;
    
    const ARG_ACCOUNT = 1;    
    const ARG_DESCRIPTION = 2;
    const ARG_CATALOG_GUID = 3;
    const ARG_URGENCY_ID = 4;
    const ARG_SEVERITY_ID = 5;
    
    protected $_internal_arg_name = array(
        self::ARG_ACCOUNT => 'Account',
        self::ARG_DESCRIPTION => 'Description',
        self::ARG_CATALOG_GUID => 'CatalogGUID',
        self::ARG_URGENCY_ID => 'UrgencyId',
        self::ARG_SEVERITY_ID => 'SeverityId',
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
        $this->default_data['wspath'] = '/WebService/SmoBridge.php';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_ACCOUNT, 'Value' => 'Account name'),
            array('Arg' => self::ARG_DESCRIPTION, 'Value' => '{$body}'),
            array('Arg' => self::ARG_CATALOG_GUID, 'Value' => 'Catalog_GUID'),
        );
    }
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();
        
        $this->default_data['url'] = 'http://{$address}/TicketNumber={$ticket_id}';        
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
        $this->_checkFormValue('wspath', "Please set 'Webservice Path' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('password', "Please set 'Password' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
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
        $tpl = $this->initSmartyTemplate('providers/BmcFootprints11/templates');
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("bmc" => _("BMC Footprints 11")));
        
        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $wspath_html = '<input size="50" name="wspath" type="text" value="' . $this->_getFormValue('wspath') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'wspath' => array('label' => _("Webservice Path") . $this->_required_field, 'html' => $wspath_html),
            'username' => array('label' => _("Username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->_required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_ACCOUNT . '">' . _('Account') . '</options>' .
        '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</options>' .
        '<option value="' . self::ARG_CATALOG_GUID . '">' . _('Catalog GUID') . '</options>' .
        '<option value="' . self::ARG_URGENCY_ID . '">' . _('Urgency ID') . '</options>' .
        '<option value="' . self::ARG_SEVERITY_ID . '">' . _('Severity ID') . '</options>' .
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
        $this->_save_config['simple']['wspath'] = $this->_submitted_config['wspath'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        return $result;
    }

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
        
        $code = $this->createTicket($ticket_arguments);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }
        $this->attachFiles($ticket_arguments);
        
        $this->saveHistory($db_storage, $result, array('contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems, 
            'ticket_value' => $this->_ticket_number, 'subject' => $ticket_arguments['CatalogGUID'], 
            'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode(array('arguments' => $ticket_arguments))));
        
        return $result;
    }

    /*
     *
     * SOAP API
     *
     */
    protected function setWsError($error) {
        $this->ws_error = $error;
    }
    
    protected function attachFiles($ticket_arguments) {
        $attach_files = $this->getUploadFiles();
        foreach ($attach_files as $file) {
            $base64_content = base64_encode(file_get_contents($file['filepath']));
            $data = '<?xml version="1.0"?>
<soap:Envelope
  soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<tns:EZV_AttachDocToRequest xmlns:tns="https://na1.easyvista.com/WebService">
    <tns:Account><![CDATA[' . $ticket_arguments[$this->_internal_arg_name[self::ARG_ACCOUNT]] . ']]></tns:Account>
    <tns:Login><![CDATA[' . $this->rule_data['username'] . ']]></tns:Login>
    <tns:Password><![CDATA[' . $this->rule_data['password'] . ']]></tns:Password>
    <tns:RFC_Number><![CDATA[' . $this->_ticket_number . ']]></tns:RFC_Number>
    <tns:path_docname><![CDATA[' . $file['filename'] . ']]></tns:path_docname>
    <tns:BinaryStream><![CDATA[' . $base64_content . ']]></tns:BinaryStream>
</tns:EZV_CreateRequest>
</soap:Body>
</soap:Envelope>
';
                
            $this->callSOAP($data, 'tns:EZV_AttachDocToRequest');
        }
    }
    
    protected function createTicket($ticket_arguments) {
        $data = '<?xml version="1.0"?>
<soap:Envelope
  soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<tns:EZV_CreateRequest xmlns:tns="https://na1.easyvista.com/WebService">
    <tns:Account><![CDATA[' . $ticket_arguments[$this->_internal_arg_name[self::ARG_ACCOUNT]] . ']]></tns:Account>
    <tns:Login><![CDATA[' . $this->rule_data['username'] . ']]></tns:Login>
    <tns:Password><![CDATA[' . $this->rule_data['password'] . ']]></tns:Password>' .
    ((isset($ticket_arguments[$this->_internal_arg_name[self::ARG_CATALOG_GUID]])) ? 
        '<tns:Catalog_GUID>' . $ticket_arguments[$this->_internal_arg_name[self::ARG_CATALOG_GUID]] . '</tns:Catalog_GUID>' :  '') .
    ((isset($ticket_arguments[$this->_internal_arg_name[self::ARG_SEVERITY_ID]])) ? 
        '<tns:Severity_ID>' . $ticket_arguments[$this->_internal_arg_name[self::ARG_SEVERITY_ID]] . '</tns:Severity_ID>' :  '') .
    ((isset($ticket_arguments[$this->_internal_arg_name[self::ARG_URGENCY_ID]])) ? 
        '<tns:Urgency_ID>' . $ticket_arguments[$this->_internal_arg_name[self::ARG_URGENCY_ID]] . '</tns:Urgency_ID>' :  '') .
    ((isset($ticket_arguments[$this->_internal_arg_name[self::ARG_URGENCY_ID]])) ? 
        '<tns:Description><![CDATA[' . $ticket_arguments[$this->_internal_arg_name[self::ARG_DESCRIPTION]] . ']]></tns:Description>' :  '') .
'</tns:EZV_CreateRequest>
</soap:Body>
</soap:Envelope>
';
                
        if ($this->callSOAP($data, 'tns:EZV_CreateRequest') == 1) {
            return -1;
        }
        
        /*
        * OK:
        *    TODO
        *
        * NOK:
        *    TODO
        */
        if (!preg_match('/<return.*?>(.*?)<\/return>/msi', $this->soap_result, $matches)) {
            $this->setWsError($result);
            return -1;
        }
        
        $this->_ticket_number = $matches[1];
        return 0;
    }
    
    protected function callSOAP($data, $soap_action) {
        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        $endpoint = $proto . '://' . $this->rule_data['address'] . $this->rule_data['wspath'];           
        $ch = curl_init($endpoint);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type:  text/xml;charset=UTF-8',
            'SOAPAction: ' . $soap_action,
            'Content-Length: ' . strlen($data))
        );
        $this->soap_result = curl_exec($ch);
        curl_close($ch);
        
        if ($this->soap_result == false) {
            $this->setWsError(curl_error($ch));    
            return 1;
        }
        
        return 0;
    }
}
