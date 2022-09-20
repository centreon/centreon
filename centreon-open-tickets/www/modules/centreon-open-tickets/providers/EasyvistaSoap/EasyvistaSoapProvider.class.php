<?php
/*
 * Copyright 2017-2019 Centreon (http://www.centreon.com/)
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

class EasyvistaSoapProvider extends AbstractProvider
{
    protected $proxy_enabled = 1;
    protected $attach_files = 1;

    public const ARG_ACCOUNT = 1;
    public const ARG_CATALOG_GUID = 2;
    public const ARG_CATALOG_CODE = 3;
    public const ARG_ASSET_ID = 4;
    public const ARG_ASSET_TAG = 5;
    public const ARG_ASSET_NAME = 6;
    public const ARG_URGENCY_ID = 7;
    public const ARG_SEVERITY_ID = 8;
    public const ARG_EXTERNAL_REFERENCE = 9;
    public const ARG_PHONE = 10;
    public const ARG_REQUESTOR_IDENTIFICATION = 11;
    public const ARG_REQUESTOR_MAIL = 12;
    public const ARG_REQUESTOR_NAME = 13;
    public const ARG_LOCATION_ID = 14;
    public const ARG_LOCATION_CODE = 15;
    public const ARG_DEPARTMENT_ID = 16;
    public const ARG_DEPARTMENT_CODE = 17;
    public const ARG_RECIPIENT_ID = 18;
    public const ARG_RECIPIENT_IDENTIFICATION = 19;
    public const ARG_RECIPIENT_MAIL = 20;
    public const ARG_RECIPIENT_NAME= 21;
    public const ARG_ORIGIN = 22;
    public const ARG_DESCRIPTION = 23;
    public const ARG_PARENT_REQUEST = 24;
    public const ARG_CI_ID = 25;
    public const ARG_CI_ASSET_TAG = 26;
    public const ARG_CI_NAME = 27;
    public const ARG_SUBMIT_DATE = 28;

    protected $internal_arg_name = array(
        self::ARG_ACCOUNT => array(
            'formid' => 'Account',
            'soapname' => 'Account'
        ),
        self::ARG_CATALOG_GUID => array('formid' => 'CatalogGUID', 'soapname' => 'Catalog_GUID'),
        self::ARG_CATALOG_CODE => array('formid' => 'CatalogCode', 'soapname' => 'Catalog_Code'),
        self::ARG_ASSET_ID => array('formid' => 'AssetID', 'soapname' => 'AssetID'),
        self::ARG_ASSET_TAG => array('formid' => 'AssetTag', 'soapname' => 'AssetTag'),
        self::ARG_ASSET_NAME => array('formid' => 'AssetName', 'soapname' => 'ASSET_NAME'),
        self::ARG_URGENCY_ID => array('formid' => 'UrgencyId', 'soapname' => 'Urgency_ID'),
        self::ARG_SEVERITY_ID => array('formid' => 'SeverityId', 'soapname' => 'Severity_ID'),
        self::ARG_EXTERNAL_REFERENCE => array('formid' => 'ExternalReference', 'soapname' => 'External_reference'),
        self::ARG_PHONE => array('formid' => 'Phone', 'soapname' => 'Phone'),
        self::ARG_REQUESTOR_IDENTIFICATION => array(
            'formid' => 'RequestorIdentification',
            'soapname' => 'Requestor_Identification'
        ),
        self::ARG_REQUESTOR_MAIL => array('formid' => 'RequestorMail', 'soapname' => 'Requestor_Mail'),
        self::ARG_REQUESTOR_NAME => array('formid' => 'RequestorName', 'soapname' => 'Requestor_Name'),
        self::ARG_LOCATION_ID => array('formid' => 'LocationID', 'soapname' => 'Location_ID'),
        self::ARG_LOCATION_CODE => array('formid' => 'LocationCode', 'soapname' => 'Location_Code'),
        self::ARG_DEPARTMENT_ID => array('formid' => 'DepartmentID', 'soapname' => 'Department_ID'),
        self::ARG_DEPARTMENT_CODE => array('formid' => 'DepartmentCode', 'soapname' => 'Department_Code'),
        self::ARG_RECIPIENT_ID => array('formid' => 'RecipientID', 'soapname' => 'Recipient_ID'),
        self::ARG_RECIPIENT_IDENTIFICATION => array(
            'formid' => 'RecipientIdentification',
            'soapname' => 'Recipient_Identification'
        ),
        self::ARG_RECIPIENT_MAIL => array('formid' => 'RecipientMail', 'soapname' => 'Recipient_Mail'),
        self::ARG_RECIPIENT_NAME => array('formid' => 'RecipientName', 'soapname' => 'Recipient_Name'),
        self::ARG_ORIGIN => array('formid' => 'Origin', 'soapname' => 'Origin'),
        self::ARG_DESCRIPTION => array('formid' => 'Description', 'soapname' => 'Description'),
        self::ARG_PARENT_REQUEST => array('formid' => 'ParentRequest', 'soapname' => 'ParentRequest'),
        self::ARG_CI_ID => array('formid' => 'CiID', 'soapname' => 'CI_ID'),
        self::ARG_CI_ASSET_TAG => array('formid' => 'CiAssetTag', 'soapname' => 'CI_ASSET_TAG'),
        self::ARG_CI_NAME => array('formid' => 'CiName', 'soapname' => 'CI_NAME'),
        self::ARG_SUBMIT_DATE => array('formid' => 'SubmitDate', 'soapname' => 'SUBMIT_DATE'),
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
        $this->default_data['wspath'] = '/WebService/SmoBridge.php';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_ACCOUNT, 'Value' => 'Account name'),
            array('Arg' => self::ARG_DESCRIPTION, 'Value' => '{$body}'),
            array('Arg' => self::ARG_CATALOG_GUID, 'Value' => 'Catalog_GUID'),
        );
    }

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = 'http://{$address}/TicketNumber={$ticket_id}';
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
        $this->checkFormValue('wspath', "Please set 'Webservice Path' value");
        $this->checkFormValue('timeout', "Please set 'Timeout' value");
        $this->checkFormValue('username', "Please set 'Username' value");
        $this->checkFormValue('password', "Please set 'Password' value");
        $this->checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->checkFormInteger('timeout', "'Timeout' must be a number");
        $this->checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        $this->checkFormInteger('proxy_port', "'Proxy port' must be a number");

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
        $tpl = $this->initSmartyTemplate('providers/EasyvistaSoap/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("easyvista" => _("Easyvista")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $wspath_html = '<input size="50" name="wspath" type="text" value="' .
            $this->getFormValue('wspath') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' .
            $this->getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<div class="md-checkbox md-checkbox-inline">' .
            '<input type="checkbox" id="https" name="https" value="yes" ' .
            ($this->getFormValue('https') === 'yes' ? 'checked' : '') . '/>' .
            '<label class="empty-label" for="https"></label></div>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->required_field, 'html' => $address_html),
            'wspath' => array('label' => _("Webservice Path") . $this->required_field, 'html' => $wspath_html),
            'username' => array('label' => _("Username") . $this->required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">' .
        '<option value="' . self::ARG_ACCOUNT . '">' . _('Account') . '</options>' .
        '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</options>' .
        '<option value="' . self::ARG_CATALOG_GUID . '">' . _('Catalog GUID') . '</options>' .
        '<option value="' . self::ARG_CATALOG_CODE . '">' . _('Catalog Code') . '</options>' .
        '<option value="' . self::ARG_URGENCY_ID . '">' . _('Urgency ID') . '</options>' .
        '<option value="' . self::ARG_SEVERITY_ID . '">' . _('Severity ID') . '</options>' .
        '<option value="' . self::ARG_ASSET_ID . '">' . _('Asset ID') . '</options>' .
        '<option value="' . self::ARG_ASSET_TAG . '">' . _('Asset Tag') . '</options>' .
        '<option value="' . self::ARG_ASSET_NAME . '">' . _('Asset Name') . '</options>' .
        '<option value="' . self::ARG_EXTERNAL_REFERENCE . '">' . _('External Reference') . '</options>' .
        '<option value="' . self::ARG_PHONE . '">' . _('Phone') . '</options>' .
        '<option value="' . self::ARG_REQUESTOR_IDENTIFICATION . '">' . _('Requestor Identification') . '</options>' .
        '<option value="' . self::ARG_REQUESTOR_MAIL . '">' . _('Requestor Mail') . '</options>' .
        '<option value="' . self::ARG_REQUESTOR_NAME . '">' . _('Requestor Name') . '</options>' .
        '<option value="' . self::ARG_LOCATION_ID . '">' . _('Location ID') . '</options>' .
        '<option value="' . self::ARG_LOCATION_CODE . '">' . _('Location Code') . '</options>' .
        '<option value="' . self::ARG_DEPARTMENT_ID . '">' . _('Department ID') . '</options>' .
        '<option value="' . self::ARG_DEPARTMENT_CODE . '">' . _('Department Code') . '</options>' .
        '<option value="' . self::ARG_RECIPIENT_ID . '">' . _('Recipient ID') . '</options>' .
        '<option value="' . self::ARG_RECIPIENT_IDENTIFICATION . '">' . _('Recipient Identification') . '</options>' .
        '<option value="' . self::ARG_RECIPIENT_MAIL . '">' . _('Recipient Mail') . '</options>' .
        '<option value="' . self::ARG_RECIPIENT_NAME . '">' . _('Recipient Name') . '</options>' .
        '<option value="' . self::ARG_ORIGIN . '">' . _('Origin') . '</options>' .
        '<option value="' . self::ARG_PARENT_REQUEST . '">' . _('Parent Request') . '</options>' .
        '<option value="' . self::ARG_CI_ID . '">' . _('CI ID') . '</options>' .
        '<option value="' . self::ARG_CI_ASSET_TAG . '">' . _('CI Asset Tag') . '</options>' .
        '<option value="' . self::ARG_CI_NAME . '">' . _('CI Name') . '</options>' .
        '<option value="' . self::ARG_SUBMIT_DATE . '">' . _('Submit Date') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
    }

    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function getConfigContainer2Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/EasyvistaSoap/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("easyvista" => _("Easyvista")));

        $updatefields_html = '<input size="50" name="ez_updatefields" type="text" value="' .
            $this->getFormValue('ez_updatefields') . '" />';
        $array_form = array(
            'ez_updatefields' => array('label' => _("Update fields"), 'html' => $updatefields_html),
        );

        $tpl->assign('form', $array_form);
        $this->config['container2_html'] .= $tpl->fetch('conf_container2extra.ihtml');
    }

    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['wspath'] = $this->submitted_config['wspath'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['https'] = (
            isset($this->submitted_config['https']) && $this->submitted_config['https'] == 'yes'
        ) ? $this->submitted_config['https'] : '';
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['simple']['ez_updatefields'] = $this->submitted_config['ez_updatefields'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);
        return $result;
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

                $ticket_arguments[$this->internal_arg_name[$value['Arg']]['formid']] = $result_str;
            }
        }

        $code = $this->createTicket($ticket_arguments);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }
        $this->attachFiles($ticket_arguments);

        if (isset($this->rule_data['ez_updatefields']) && $this->rule_data['ez_updatefields'] != '') {
            $tpl->assign('string', $this->rule_data['ez_updatefields']);
            $this->rule_data['ez_updatefields'] = $tpl->fetch('eval.ihtml');
            $this->updateTicket($ticket_arguments);
        }

        $this->saveHistory(
            $db_storage,
            $result,
            array(
                'contact' => $contact,
                'host_problems' => $host_problems,
                'service_problems' => $service_problems,
                'ticket_value' => $this->_ticket_number,
                'subject' => $ticket_arguments['CatalogGUID'],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array(
                        'arguments' => $ticket_arguments
                    )
                )
            )
        );

        return $result;
    }

    /*
     *
     * SOAP API
     *
     */
    protected function setWsError($error)
    {
        $this->ws_error = $error;
    }

    protected function updateTicket($ticket_arguments)
    {
        $data = '<?xml version="1.0"?>
<soap:Envelope
  soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<tns:EZV_UpdateRequest xmlns:tns="https://na1.easyvista.com/WebService">
    <tns:Account><![CDATA[' .
            $ticket_arguments[$this->internal_arg_name[self::ARG_ACCOUNT]['formid']] . ']]></tns:Account>
    <tns:Login><![CDATA[' . $this->rule_data['username'] . ']]></tns:Login>
    <tns:Password><![CDATA[' . $this->rule_data['password'] . ']]></tns:Password>
    <tns:RFC_Number><![CDATA[' . $this->_ticket_number . ']]></tns:RFC_Number>
    <tns:fields_to_update><![CDATA[' . $this->rule_data['ez_updatefields'] . ']]></tns:fields_to_update>
    <tns:Request_id />
    <tns:External_reference />
</tns:EZV_UpdateRequest>
</soap:Body>
</soap:Envelope>
';

        $this->callSOAP($data, 'tns:EZV_UpdateRequest');
    }

    protected function attachFiles($ticket_arguments)
    {
        $attach_files = $this->getUploadFiles();
        foreach ($attach_files as $file) {
            $base64_content = base64_encode(file_get_contents($file['filepath']));
            $data = '<?xml version="1.0"?>
<soap:Envelope
  soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<tns:EZV_AttachDocToRequest xmlns:tns="https://na1.easyvista.com/WebService">
    <tns:Account><![CDATA[' .
            $ticket_arguments[$this->internal_arg_name[self::ARG_ACCOUNT]['formid']] . ']]></tns:Account>
    <tns:Login><![CDATA[' . $this->rule_data['username'] . ']]></tns:Login>
    <tns:Password><![CDATA[' . $this->rule_data['password'] . ']]></tns:Password>
    <tns:path_docname><![CDATA[' . $file['filename'] . ']]></tns:path_docname>
    <tns:BinaryStream><![CDATA[' . $base64_content . ']]></tns:BinaryStream>
    <tns:RFC_Number><![CDATA[' . $this->_ticket_number . ']]></tns:RFC_Number>
    <tns:External_reference />
    <tns:Description />
</tns:EZV_AttachDocToRequest>
</soap:Body>
</soap:Envelope>
';

            $this->callSOAP($data, 'tns:EZV_AttachDocToRequest');
        }
    }

    protected function createTicket($ticket_arguments)
    {
        $attributes = '';
        $account = '';
        foreach ($this->internal_arg_name as $key => $value) {
            if ($value['soapname'] == 'Account') {
                $account = '<tns:Account><![CDATA[' . $ticket_arguments[$value['formid']] . ']]></tns:Account>';
                continue;
            }
            $attributes .= (isset($ticket_arguments[$value['formid']]) ?
                '<tns:' . $value['soapname'] . '><![CDATA[' . $ticket_arguments[$value['formid']] .
                ']]></tns:' . $value['soapname'] . '>' :  '<tns:' . $value['soapname'] . '/>');
        }

        $data = '<?xml version="1.0"?>
<soap:Envelope
  soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<tns:EZV_CreateRequest xmlns:tns="https://na1.easyvista.com/WebService">' .
        $account . '
    <tns:Login><![CDATA[' . $this->rule_data['username'] . ']]></tns:Login>
    <tns:Password><![CDATA[' . $this->rule_data['password'] . ']]></tns:Password>' .
        $attributes .'
</tns:EZV_CreateRequest>
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
        *    <?xml version="1.0" encoding="UTF-8"?>
        *    <SOAP-ENV:Envelope SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
        *                       xmlns:xsd="http://www.w3.org/2001/XMLSchema"
        *                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        *                       xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
        *                       xmlns:si="http://soapinterop.org/xsd"><SOAP-ENV:Body>
        *   <ns1:EZV_CreateRequestResponse xmlns:ns1="https://na1.easyvista.com/WebService">
        *       <return xsi:type="xsd:string">-1</return>
        *   </ns1:EZV_CreateRequestResponse>
        *   </SOAP-ENV:Body></SOAP-ENV:Envelope>
        */
        if (!preg_match('/<return.*?>(.*?)<\/return>/msi', $this->soap_result, $matches)) {
            $this->setWsError($result);
            return -1;
        }
        $return_value = $matches[1];
        if (preg_match('/^-[0-9]+/', $return_value)) {
            $map_error = array('-1' => 'invalid Account value', '-2' => 'Login/Password invalid',
                '-3' => 'invalid parameter', -4 => 'workflow not found');
            $msg_error = 'unknown error';
            if (isset($map_error[$return_value])) {
                $msg_error = $map_error[$return_value];
            }
            $this->setWsError($msg_error);
            return -1;
        }

        $this->_ticket_number = $return_value;
        return 0;
    }

    protected function callSOAP($data, $soap_action)
    {
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

        self::setProxy(
            $ch,
            array(
                'proxy_address' => $this->getFormValue('proxy_address', false),
                'proxy_port' => $this->getFormValue('proxy_port', false),
                'proxy_username' => $this->getFormValue('proxy_username', false),
                'proxy_password' => $this->getFormValue('proxy_password', false)
            )
        );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type:  text/xml;charset=UTF-8',
                'SOAPAction: ' . $soap_action,
                'Content-Length: ' . strlen($data)
            )
        );
        $this->soap_result = curl_exec($ch);

        if ($this->soap_result == false) {
            $this->setWsError(curl_error($ch));
            curl_close($ch);
            return 1;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            $this->setWsError($this->soap_result);
            return 1;
        }

        return 0;
    }
}
