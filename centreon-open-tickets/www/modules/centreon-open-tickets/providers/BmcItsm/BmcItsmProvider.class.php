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

class BmcItsmProvider extends AbstractProvider
{
    protected $_set_empty_xml = 1;

    protected $_itsm_fields = array(
        'Assigned_Group', 'Assigned_Group_Shift_Name', 'Assigned_Support_Company', 'Assigned_Support_Organization',
        'Assignee', 'Categorization_Tier_1', 'Categorization_Tier_2', 'Categorization_Tier_3', 'CI_Name',
        'Closure_Manufacturer', 'Closure_Product_Category_Tier1', 'Closure_Product_Category_Tier2',
        'Closure_Product_Category_Tier3', 'Closure_Product_Model_Version', 'Closure_Product_Name', 'Department',
        'First_Name', 'Impact', 'Last_Name', 'Lookup_Keyword', 'Manufacturer', 'Product_Categorization_Tier_1',
        'Product_Categorization_Tier_2', 'Product_Categorization_Tier_3', 'Product_Model_Version', 'Product_Name',
        'Reported_Source', 'Resolution', 'Resolution_Category_Tier_1', 'Resolution_Category_Tier_2',
        'Resolution_Category_Tier_3', 'Service_Type', 'Status', 'z1D_Action', 'Flag_Create_Request', 'Description',
        'Detailed_Decription', 'Urgency', 'z1D_WorklogDetails', 'z1D_Details', 'z1D_Activity_Type',
        'z1D_ActivityDate_tab', 'z1D_CommunicationSource', 'z1D_Secure_Log', 'z1D_View_Access', 'AccessMode',
        'AppInstanceServer', 'AppInterfaceForm', 'AppLogin', 'AppPassword', 'Area_Business', 'Assigned_Group_ID',
        'Assigned_To', 'Assignee_Groups', 'Assignee_Login_ID', 'Attachment_4_attachmentName',
        'Attachment_4_attachmentData', 'Attachment_4_attachmentOrigSize', 'Attachment_5_attachmentName',
        'Attachment_5_attachmentData', 'Attachment_5_attachmentOrigSize', 'Attachment_6_attachmentName',
        'Attachment_6_attachmentData', 'Attachment_6_attachmentOrigSize', 'Attachment_7_attachmentName',
        'Attachment_7_attachmentData', 'Attachment_7_attachmentOrigSize', 'Attachment_8_attachmentName',
        'Attachment_8_attachmentData', 'Attachment_8_attachmentOrigSize', 'Attachment_9_attachmentName',
        'Attachment_9_attachmentData', 'Attachment_9_attachmentOrigSize', 'BiiARS_01', 'BiiARS_02', 'BiiARS_03',
        'BiiARS_04', 'BiiARS_05', 'bOrphanedRoot', 'CC_Business', 'cell_name', 'Client_Sensitivity', 'Client_Type',
        'ClientLocale', 'Company', 'Component_ID', 'Contact_Company', 'Created_By', 'Created_From_flag', 'DatasetId',
        'DataTags', 'Default_City', 'Default_Country', 'Desk_Location', 'Direct_Contact_Company',
        'Direct_Contact_Department', 'Direct_Contact_First_Name', 'Direct_Contact_Internet_E-mail',
        'Direct_Contact_Last_Name', 'Direct_Contact_Middle_Initial', 'Direct_Contact_Organization',
        'Direct_Contact_Phone_Number', 'Direct_Contact_Site', 'Extension_Business', 'first_name2',
        'Generic_Categorization_Tier_1', 'Global_OR_Custom_Mapping', 'Impact_OR_Root', 'Incident_Number',
        'Incident_Entry_ID', 'InstanceId', 'Internet_E-mail', 'last_name2', 'Local_Business', 'Login_ID',
        'Mail_Station', 'MaxRetries', 'mc_ueid', 'Middle_Initial', 'OptionForClosingIncident', 'Organization',
        'Person_ID', 'Phone_Number', 'policy_name', 'PortNumber', 'Priority', 'Priority_Weight', 'Protocol',
        'ReconciliationIdentity', 'Region', 'Reported_Date', 'Required_Resolution_DateTime', 'Resolution_Method',
        'root_component_id_list', 'root_incident_id_list', 'Schema_Name', 'Short_Description', 'Site', 'Site_Group',
        'Site_ID', 'SRID', 'SRInstanceID', 'SRMS_Registry_Instance_ID', 'SRMSAOIGuid', 'status_incident',
        'Status_Reason', 'status_reason2', 'Submitter', 'TemplateID', 'TemplateID2', 'Unavailability_Type',
        'Unavailability_Priority', 'Unknown_User', 'use_case', 'Vendor_Group', 'Vendor_Group_ID', 'Vendor_Name',
        'Vendor_Organization', 'Vendor_Ticket_Number', 'VIP', 'z1D_Char01', 'z1D_Permission_Group_ID',
        'z1D_Permission_Group_List', 'z1D_Char02', 'z1D_CIUAAssignGroup', 'z1D_CIUASupportCompany',
        'z1D_CIUASupportOrg', 'z1D_Command', 'z1D_SRMInteger', 'z1D_SupportGroupID', 'z1D_UAAssignmentMethod',
         'z2AF_Act_Attachment_1_attachmentName', 'z2AF_Act_Attachment_1_attachmentData',
         'z2AF_Act_Attachment_1_attachmentOrigSize', 'z2Attachment_2_attachmentName', 'z2Attachment_2_attachmentData',
         'z2Attachment_2_attachmentOrigSize', 'z2Attachment_3_attachmentName', 'z2Attachment_3_attachmentData',
         'z2Attachment_3_attachmentOrigSize', 'zTmpEventGUID'
    );

    protected $internal_arguments = array(
        'Action' => array('id' => 1, 'soap' => 'z1D_Action'),
        'Service Type' => array('id' => 2, 'soap' => 'Service_Type'),
        'Subject' => array('id' => 3, 'soap' => 'Description'),
        'Content' => array('id' => 4, 'soap' => 'Detailed_Decription'),
        'Urgency' => array('id' => 5, 'soap' => 'Urgency'),
        'Impact' => array('id' => 6, 'soap' => 'Impact'),
        'First Name' => array('id' => 7, 'soap' => 'First_Name'),
        'Last Name' => array('id' => 8, 'soap' => 'Last_Name'),
        'Dataset ID' => array('id' => 9, 'soap' => 'DatasetId'),
        'Status' => array('id' => 10, 'soap' => 'Status'),
        'Source' => array('id' => 11, 'soap' => 'Reported_Source'),
        'Type Service' => array('id' => 12, 'soap' => 'Service_Type'),
        'Assigned Group' => array('id' => 13, 'soap' => 'Assigned_Group'),
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
        $this->default_data['endpoint'] = 'http://127.0.0.1/arsys/services/' .
            'ARService?server=XXXX&webService=HPD_IncidentInterface_Create_WS';
        $this->default_data['namespace'] = 'IncidentInterface_Create_WS';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => $this->internal_arguments['Subject']['id'],
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => $this->internal_arguments['Content']['id'], 'Value' => '{$body}'),
            array('Arg' => $this->internal_arguments['Action']['id'], 'Value' => 'CREATE'),
            array('Arg' => $this->internal_arguments['Status']['id'], 'Value' => 'Assigned'),
            array('Arg' => $this->internal_arguments['Source']['id'], 'Value' => 'Supervision'),
            array('Arg' => $this->internal_arguments['Type Service']['id'], 'Value' => 'Infrastructure Event'),
        );
    }

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['message_confirm'] = '
<table class="table">
    <tr>
        <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
    </tr>
{if $ticket_is_ok == 1}
    <tr>
        <td class="FormRowField" style="padding-left:15px;">New ticket opened: {$ticket_id}.</td>
    </tr>
{else}
    <tr>
        <td class="FormRowField" style="padding-left:15px;">Error to open the ticket: <xmp>{$ticket_error_message}</xmp>
        </td>
    </tr>
{/if}
</table>
';
        $this->default_data['message_confirm'] = $this->default_data['message_confirm'];
        $this->default_data['url'] = 'http://{$address}/index.pl?Action=AgentTicketZoom;TicketNumber={$ticket_id}';
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

        $this->checkFormValue('endpoint', "Please set 'Endpoint' value");
        $this->checkFormValue('namespace', "Please set 'Namespace' value");
        $this->checkFormValue('timeout', "Please set 'Timeout' value");
        $this->checkFormValue('username', "Please set 'Username' value");
        $this->checkFormValue('password', "Please set 'Password' value");
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
        $tpl = $this->initSmartyTemplate('providers/BmcItsm/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("bmcitsm" => _("BMC ITSM")));

        // Form
        $endpoint_html = '<input size="50" name="endpoint" type="text" value="' .
            $this->getFormValue('endpoint') . '" />';
        $namespace_html = '<input size="50" name="namespace" type="text" value="' .
            $this->getFormValue('namespace') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' .
            $this->getFormValue('password') . '" autocomplete="off" />';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" />';

        $array_form = array(
            'endpoint' => array('label' => _("Endpoint") . $this->required_field, 'html' => $endpoint_html),
            'namespace' => array('label' => _("Namespace"), 'html' => $namespace_html),
            'username' => array('label' => _("Username") . $this->required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->required_field, 'html' => $password_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">';
        ksort($this->internal_arguments);
        foreach ($this->internal_arguments as $label => $array) {
            $mappingTicketArg_html .= '<option value="' . $array['id'] . '">' . _($label) . '</options>';
        }
        $mappingTicketArg_html .= '</select>';
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
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);
        return $result;
    }

    protected function saveConfigExtra()
    {
        $this->save_config['simple']['endpoint'] = $this->submitted_config['endpoint'];
        $this->save_config['simple']['namespace'] = $this->submitted_config['namespace'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
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

                foreach ($this->internal_arguments as $arg) {
                    if ($arg['id'] == $value['Arg']) {
                        $ticket_arguments[$arg['soap']] = $result_str;
                        break;
                    }
                }
            }
        }

        $code = $this->createTicketBmcItsm($ticket_arguments);
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
                'ticket_value' => $this->_ticket_number,
                'subject' => $ticket_arguments['Description'],
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
     * REST API
     *
     */
    protected function setWsError($error)
    {
        $this->ws_error = $error;
    }

    protected function createTicketBmcItsm($ticket_arguments)
    {
        $data = "<?xml version=\"1.0\"?>
<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:" .
            $this->rule_data['namespace'] . "\">
   <soapenv:Header>
      <urn:AuthenticationInfo>
         <urn:userName>" . $this->rule_data['username'] . "</urn:userName>
         <urn:password>" . $this->rule_data['password'] . "</urn:password>
         <!--Optional:-->
         <urn:authentication></urn:authentication>
         <!--Optional:-->
         <urn:locale></urn:locale>
         <!--Optional:-->
         <urn:timeZone></urn:timeZone>
      </urn:AuthenticationInfo>
   </soapenv:Header>
   <soapenv:Body>
      <urn:HelpDesk_Submit_Service>
";
        foreach ($this->_itsm_fields as $field) {
            if (isset($ticket_arguments[$field]) && $ticket_arguments[$field] != '') {
                $data .= "<urn:" . $field . ">" . $ticket_arguments[$field] . "</urn:" . $field . ">";
            } elseif ($this->_set_empty_xml == 1) {
                $data .= "<urn:" . $field . "></urn:" . $field . ">";
            }
        }
        $data .= "</urn:HelpDesk_Submit_Service>
   </soapenv:Body>
</soapenv:Envelope>
";

        if ($this->callSOAP($data) == 1) {
            return -1;
        }

        return 0;
    }

    protected function callSOAP($data)
    {
        $this->otrs_call_response = null;

        $base_url = $this->rule_data['endpoint'];
        $ch = curl_init($base_url);
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
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type:  text/xml;charset=UTF-8',
                'SOAPAction: urn:' . $this->rule_data['namespace'] . '/HelpDesk_Submit_Service',
                'Content-Length: ' . strlen($data)
            )
        );
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result == false) {
            $this->setWsError(curl_error($ch));
            return 1;
        }

        /*
        * OK:
        *    <?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soapenv:Body><ns0:HelpDesk_Submit_ServiceResponse xmlns:ns0="urn:HPD_IncidentInterface_Create_WS" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        *    <ns0:Incident_Number>INC000001907092</ns0:Incident_Number>
        *    </ns0:HelpDesk_Submit_ServiceResponse></soapenv:Body></soapenv:Envelope>
        * NOK:
        *    <?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soapenv:Body>
        *    <soapenv:Fault><faultcode>soapenv:Server.userException</faultcode><faultstring>java.lang.NullPointerException</faultstring><detail><ns1:hostname xmlns:ns1="http://xml.apache.org/axis/">xxxx.localdomain</ns1:hostname></detail></soapenv:Fault></soapenv:Body></soapenv:Envelope>
        */

        if (!preg_match('/Incident_Number>(.*?)</', $result, $matches)) {
            $this->setWsError($result);
            return 1;
        }

        $this->_ticket_number = $matches[1];
        return 0;
    }
}
