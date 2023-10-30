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

class SerenaProvider extends AbstractProvider
{
    public const ARG_PROJECT_ID = 1;
    public const ARG_SUBJECT = 2;
    public const ARG_CONTENT = 3;
    public const ARG_CATEGORY = 4;
    public const ARG_SUB_CATEGORY = 5;
    public const ARG_SUB_CATEGORY_DETAILS = 6;

    protected $internal_arg_name = array(
        self::ARG_PROJECT_ID => 'project_id',
        self::ARG_SUBJECT => 'subject',
        self::ARG_CONTENT => 'content',
        self::ARG_CATEGORY => 'category',
        self::ARG_SUB_CATEGORY => 'subcategory',
        self::ARG_SUB_CATEGORY_DETAILS => 'subcategory_details',
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
        $this->default_data['endpoint'] = 'http://127.0.0.1//gsoap/gsoap_ssl.dll?XXXXXX';
        $this->default_data['namespace'] = 'XXXXXXX';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_SUBJECT,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/' .
                    'Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_PROJECT_ID, 'Value' => '1'),
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
    <tr><td class="FormRowField" style="padding-left:15px;">New ticket opened: {$ticket_id}.</td></tr>
{else}
    <tr>
    <td class="FormRowField" style="padding-left:15px;">Error to open the ticket: <xmp>{$ticket_error_message}</xmp>
    </td></tr>
{/if}
</table>
';
        $this->default_data['message_confirm'] = $this->default_data['message_confirm'];
        $this->default_data['url'] = '';
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
        $tpl = $this->initSmartyTemplate('providers/Serena/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("serena" => _("Serena")));

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
            'type="select-one">'.
            '<option value="' . self::ARG_PROJECT_ID . '">' . _('Project ID') . '</options>' .
            '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</options>' .
            '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
            '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</options>' .
            '<option value="' . self::ARG_SUB_CATEGORY . '">' . _('Sub-Category') . '</options>' .
            '<option value="' . self::ARG_SUB_CATEGORY_DETAILS . '">' . _('Sub-Category Details') . '</options>' .
        '</select>';
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

                $ticket_arguments[$this->internal_arg_name[$value['Arg']]] = $result_str;
            }
        }

        $code = $this->createTicketSerena($ticket_arguments);
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
                'subject' => $ticket_arguments[
                    $this->internal_arg_name[self::ARG_SUBJECT]
                ],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array('arguments' => $ticket_arguments)
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

    protected function createTicketSerena($ticket_arguments)
    {
        $extended_fields = "";
        $listing = array(
            $this->internal_arg_name[self::ARG_SUB_CATEGORY_DETAILS] => array(
                'dbName' => 'OT_SUB_CATEGORY_DETAILS',
                'displayName' => 'Sub-category details'
            ),
            $this->internal_arg_name[self::ARG_SUB_CATEGORY] => array(
                'dbName' => 'OT_SUB_CATEGORY',
                'displayName' => 'Sub-category'
            ),
            $this->internal_arg_name[self::ARG_CATEGORY] => array(
                'dbName' => 'OT_CATEGORY',
                'displayName' => 'OT_CATEGORY'
            ),
        );
        foreach ($ticket_arguments as $ticket_argument => $value) {
            if (isset($listing[$ticket_argument])) {
                $extended_fields .= "
    <ae:extendedField>
        <ae:id>
            <ae:displayName>" . $listing[$ticket_argument]['displayName']. "</ae:displayName>
            <ae:id></ae:id>
            <ae:uuid></ae:uuid>
            <ae:dbName>" . $listing[$ticket_argument]['dbName']. "</ae:dbName>
        </ae:id>
        <ae:setValueBy>DISPLAY-VALUE</ae:setValueBy>
        <ae:setValueMethod>REPLACE-VALUES</ae:setValueMethod>
        <ae:value>
            <ae:displayValue>" . $value . "</ae:displayValue>
            <ae:internalValue></ae:internalValue>
            <ae:uuid></ae:uuid>
        </ae:value>
    </ae:extendedField>
";
            }
        }

        $data = "<?xml version=\"1.0\"?>
<SOAP-ENV:Envelope
  xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\"
  SOAP-ENV:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">
<SOAP-ENV:Body>
<ae:CreatePrimaryItem xmlns:ae=\"urn:" . $this->rule_data['namespace'] . "\">
    <ae:auth>
        <ae:userId>" . $this->rule_data['username'] . "</ae:userId>
        <ae:password><![CDATA[" . $this->rule_data['password'] . "]]></ae:password>
        <ae:hostname></ae:hostname>
        <ae:loginAsUserId></ae:loginAsUserId>
    </ae:auth>
    <ae:project>
        <ae:displayName></ae:displayName>
        <ae:id>" . $ticket_arguments[$this->internal_arg_name[self::ARG_PROJECT_ID]] . "</ae:id>
        <ae:uuid></ae:uuid>
        <ae:fullyQualifiedName></ae:fullyQualifiedName>
    </ae:project>
    <ae:parentItem></ae:parentItem>
    <ae:item>
        <ae:id>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:tableId></ae:tableId>
                <ae:tableIdItemId></ae:tableIdItemId>
                <ae:issueId></ae:issueId>
        </ae:id>
        <ae:itemType></ae:itemType>
        <ae:project>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:fullyQualifiedName></ae:fullyQualifiedName>
        </ae:project>
        <ae:title><![CDATA[" .
                $ticket_arguments[$this->internal_arg_name[self::ARG_SUBJECT]] . "]]></ae:title>
        <ae:description><![CDATA[" .
                $ticket_arguments[$this->internal_arg_name[self::ARG_CONTENT]] . "]]></ae:description>
        <ae:createdBy>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:loginId></ae:loginId>
        </ae:createdBy>
        <ae:createDate></ae:createDate>
        <ae:modifiedBy>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:loginId></ae:loginId>
        </ae:modifiedBy>
        <ae:modifiedDate></ae:modifiedDate>
        <ae:activeInactive></ae:activeInactive>
        <ae:state>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:isClosed></ae:isClosed>
        </ae:state>
        <ae:owner>
                <ae:displayName></ae:displayName>
                <ae:id></ae:id>
                <ae:uuid></ae:uuid>
                <ae:loginId></ae:loginId>
        </ae:owner>
        <ae:url/>
        <ae:subtasks/>
        " . $extended_fields . "
    </ae:item>
</ae:CreatePrimaryItem>
</SOAP-ENV:Body>
</SOAP-ENV:Envelope>
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
                'SOAPAction: ae:CreatePrimaryItem',
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
        *    <SOAP-ENV:Body>
        *       <ae:CreatePrimaryItemResponse>
        *            <ae:return><ae:id xsi:type="ae:ItemIdentifier"><ae:displayName>INC_003915</ae:displayName>
        * NOK:
        *    <SOAP-ENV:Body>
        *       <SOAP-ENV:Fault>
        *           <faultcode>SOAP-ENV:Client</faultcode><faultstring>Invalid project 0.</faultstring>
        */

        if (!preg_match('/<ae:id xsi:type="ae:ItemIdentifier">.*?<ae:displayName>(.*?)</', $result, $matches)) {
            $this->setWsError($result);
            return 1;
        }

        $this->_ticket_number = $matches[1];
        return 0;
    }
}
