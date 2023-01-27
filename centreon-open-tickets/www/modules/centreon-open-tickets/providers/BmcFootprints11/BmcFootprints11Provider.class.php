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

class BmcFootprints11Provider extends AbstractProvider
{
    const ARG_TITLE = 1;
    const ARG_DESCRIPTION = 2;
    const ARG_STATUS = 3;
    const ARG_PROJECTID = 4;
    const ARG_PRIORITYNUMBER = 5;
    const ARG_ASSIGNEE = 6;

    protected $internal_arg_name = [
        self::ARG_TITLE => 'Title',
        self::ARG_DESCRIPTION => 'Description',
        self::ARG_STATUS => 'Status',
        self::ARG_PROJECTID => 'ProjectID',
        self::ARG_PRIORITYNUMBER => 'PriorityNumber',
        self::ARG_ASSIGNEE => 'Assignee'
    ];

    /**
     * Set default extra value
     *
     * @return void
     */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['wspath'] = '/MRcgi/MRWebServices.pl';
        $this->default_data['action'] = '/MRWebServices';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => self::ARG_DESCRIPTION, 'Value' => '{$body}'),
            array('Arg' => self::ARG_STATUS, 'Value' => 'Open'),
            array('Arg' => self::ARG_PROJECTID, 'Value' => '1'),
            array('Arg' => self::ARG_ASSIGNEE, 'Value' => '{$user.alias}'),
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
        $this->checkFormValue('action', "Please set 'Action' value");
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
        $tpl = $this->initSmartyTemplate('providers/BmcFootprints11/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("bmc" => _("BMC Footprints 11")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $wspath_html = '<input size="50" name="wspath" type="text" value="' .
            $this->getFormValue('wspath') . '" />';
        $action_html = '<input size="50" name="action" type="text" value="' .
            $this->getFormValue('action') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' .
            $this->getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<div class="md-checkbox md-checkbox-inline">' .
            '<input type="checkbox" id="https" name="https" value="yes" ' .
            ($this->getFormValue('https') === 'yes' ? 'checked' : '') .
            '/><label class="empty-label" for="https"></label></div>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" />';

        $array_form = [
            'address' => ['label' => _("Address") . $this->required_field, 'html' => $address_html],
            'wspath' => ['label' => _("Webservice Path") . $this->required_field, 'html' => $wspath_html],
            'action' => ['label' => _("Action") . $this->required_field, 'html' => $action_html],
            'username' => ['label' => _("Username") . $this->required_field, 'html' => $username_html],
            'password' => ['label' => _("Password") . $this->required_field, 'html' => $password_html],
            'https' => ['label' => _("Use https"), 'html' => $https_html],
            'timeout' => ['label' => _("Timeout"), 'html' => $timeout_html],
            'mappingticket' => ['label' => _("Mapping ticket arguments")],
            'mappingticketprojectfield' => ['label' => _("Mapping ticket project field")]
        ];

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">' .
        '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
        '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</options>' .
        '<option value="' . self::ARG_STATUS . '">' . _('Status') . '</options>' .
        '<option value="' . self::ARG_PROJECTID . '">' . _('Project ID') . '</options>' .
        '<option value="' . self::ARG_PRIORITYNUMBER . '">' . _('Priority Number') . '</options>' .
        '<option value="' . self::ARG_ASSIGNEE . '">' . _('Assignee') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        // mapping Ticket ProjectField
        $mappingTicketProjectFieldName_html = '<input id="mappingTicketProjectFieldName_#index#" ' .
            'name="mappingTicketProjectFieldName[#index#]" size="20"  type="text" />';
        $mappingTicketProjectFieldValue_html = '<input id="mappingTicketProjectFieldValue_#index#" ' .
            'name="mappingTicketProjectFieldValue[#index#]" size="20"  type="text" />';
        $array_form['mappingTicketProjectField'] = array(
            array('label' => _("Name"), 'html' => $mappingTicketProjectFieldName_html),
            array('label' => _("Value"), 'html' => $mappingTicketProjectFieldValue_html),
        );

        $tpl->assign('form', $array_form);

        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');

        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
        $this->config['clones']['mappingTicketProjectField'] = $this->getCloneValue('mappingTicketProjectField');
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
        $this->save_config['simple']['wspath'] = $this->submitted_config['wspath'];
        $this->save_config['simple']['action'] = $this->submitted_config['action'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['https'] = (
            isset($this->submitted_config['https']) && $this->submitted_config['https'] == 'yes'
        ) ? $this->submitted_config['https'] : '';
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
        $this->save_config['clones']['mappingTicketProjectField'] = $this->getCloneSubmitted(
            'mappingTicketProjectField',
            array('Name', 'Value')
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

                $ticket_arguments[$this->internal_arg_name[$value['Arg']]] = $result_str;
            }
        }
        $ticket_project_fields = array();
        if (isset($this->rule_data['clones']['mappingTicketProjectField'])) {
            foreach ($this->rule_data['clones']['mappingTicketProjectField'] as $value) {
                if ($value['Name'] == '' ||  $value['Value'] == '') {
                    continue;
                }
                $array_tmp = array();
                $tpl->assign('string', $value['Name']);
                $array_tmp = array('Name' => $tpl->fetch('eval.ihtml'));

                $tpl->assign('string', $value['Value']);
                $array_tmp['Value'] = $tpl->fetch('eval.ihtml');

                $ticket_project_fields[] = $array_tmp;
            }
        }

        $code = $this->createTicket($ticket_arguments, $ticket_project_fields);
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
                'subject' => $ticket_arguments['Subject'],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array(
                        'arguments' => $ticket_arguments,
                        'project_fields' => $ticket_project_fields
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

    protected function createTicket($ticket_arguments, $ticket_project_fields)
    {
        $project_fields = "";

        foreach ($ticket_project_fields as $entry) {
            $type = 'string';
            if (preg_match('/^[0-9]+$/', $entry['Value'])) {
                $type = 'integer';
            }
            $project_fields .= '<' . $entry['Name'] . ' xsi:type="xsd:' . $type . '">' .
                $entry['Value'] . '</' . $entry['Name'] . '>';
        }

        if ($project_fields != '') {
            $project_fields = '<projfields>' . $project_fields . '</projfields>';
        }

        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        $url = $proto . '://' . $this->rule_data['address'] . $this->rule_data['action'];

        $data = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
    <MRWebServices__createIssue
        xmlns="' . $url . '">
        <c-gensym3 xsi:type="xsd:string">' . $this->rule_data['username'] . '</c-gensym3>
        <c-gensym5 xsi:type="xsd:string"><![CDATA[' . $this->rule_data['password'] . ']]></c-gensym5>
        <c-gensym7 xsi:type="xsd:string"/>
        <c-gensym9>
            <assignees
                soapenc:arrayType="xsd:string[1]" xsi:type="soapenc:Array">
                <item xsi:type="xsd:string">' .
                    $ticket_arguments[$this->internal_arg_name[self::ARG_ASSIGNEE]] . '</item>
            </assignees>
            ' . $project_fields .
            (isset($ticket_arguments[$this->internal_arg_name[self::ARG_PRIORITYNUMBER]]) ?
                '<priorityNumber xsi:type="xsd:int">' .
                    $ticket_arguments[$this->internal_arg_name[self::ARG_PRIORITYNUMBER]] .
                    '</priorityNumber>' : '') . '
            <status xsi:type="xsd:string">' .
                $ticket_arguments[$this->internal_arg_name[self::ARG_STATUS]] . '</status>
            <projectID xsi:type="xsd:int">' .
                $ticket_arguments[$this->internal_arg_name[self::ARG_PROJECTID]] . '</projectID>
            <title xsi:type="xsd:string"><![CDATA[' .
                $ticket_arguments[$this->internal_arg_name[self::ARG_TITLE]] . ']]></title>
            <description xsi:type="xsd:string"><![CDATA[' .
                $ticket_arguments[$this->internal_arg_name[self::ARG_DESCRIPTION]] . ']]></description>
        </c-gensym9>
    </MRWebServices__createIssue>
</soap:Body>
</soap:Envelope>
';

        if ($this->callSOAP($data, $url) == 1) {
            return -1;
        }

        return 0;
    }

    protected function callSOAP($data, $url)
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
                'SOAPAction: ' . $url . '#MRWebServices__createIssue',
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
        *    <?xml version="1.0" encoding="UTF-8" ?>
        *    <SOAP-ENV:Envelope
        *        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        *        xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
        *        xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
        *        xmlns:xsd="http://www.w3.org/2001/XMLSchema"
        *        SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        *    <SOAP-ENV:Body>
        *        <namesp1:MRWebServices__createIssueResponse
        *            xmlns:namesp1="http://10.33.48.225/MRWebServices">
        *            <return xsi:type="xsd:string">2399</return>
        *        </namesp1:MRWebServices__createIssueResponse>
        *    </SOAP-ENV:Body>
        *    </SOAP-ENV:Envelope>
        *
        * NOK:
        *    <?xml version="1.0" encoding="UTF-8"?>
        *    <SOAP-ENV:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        *    <SOAP-ENV:Body>
        *       <SOAP-ENV:Fault>
        *            <faultcode>SOAP-ENV:Server</faultcode>
        *            <faultstring>Une page d'erreur s'est affiche  l'utilisateur des Services Web.
        *             Dtails: Utilisateur 'toto' n'existe pas dans FootPrints Service Core.
        *            Suivi de la pile: Suivi de la pile:
        *            file:/usr/local/footprintsservicecore/cgi/MRWebServices.pl, line:74, sub:SOAP::Transport::HTTP::CGI::handle
        *            file:/mnt/data/footprintsservicecore/footprints_perl/lib/site_perl/5.10.0/SOAP/Transport/HTTP.pm, line:369, sub:SOAP::Transport::HTTP::Server::handle
        *            file:/mnt/data/footprintsservicecore/footprints_perl/lib/site_perl/5.10.0/SOAP/Transport/HTTP.pm, line:286, sub:SOAP::Server::handle
        *            file:/mnt/data/footprintsservicecore/footprints_perl/lib/site_perl/5.10.0/SOAP/Lite.pm, line:2282, sub:(eval)
        *            file:/mnt/data/footprintsservicecore/footprints_perl/lib/site_perl/5.10.0/SOAP/Lite.pm, line:2310, sub:(eval)
        *            file:/mnt/data/footprintsservicecore/footprints_perl/lib/site_perl/5.10.0/SOAP/Lite.pm, line:2322, sub:MRWebServices::MRWebServices__createIssue
        *            file:/usr/local/footprintsservicecore//cgi/SUBS/MRWebServices/createIssue.pl, line:59, sub:MRWebServices::MRWebServices__checkWebServicesLogin
        *            file:/usr/local/footprintsservicecore//cgi/SUBS/MRWebServices/checkWebServicesLogin.pl, line:47, sub:FP::Errorpage
        *            file:/usr/local/footprintsservicecore//cgi/SUBS/Errorpage.pl, line:125, sub:MRWebServices::__ANON__
        *            file:(eval 1330), line:6, sub:FP::printStackTrace
        *            </faultstring>
        *        </SOAP-ENV:Fault>
        *    </SOAP-ENV:Body></SOAP-ENV:Envelope>
        */

        if (!preg_match('/<return.*?>(.*?)<\/return>/msi', $result, $matches)) {
            $this->setWsError($result);
            return 1;
        }

        $this->_ticket_number = $matches[1];
        return 0;
    }
}
