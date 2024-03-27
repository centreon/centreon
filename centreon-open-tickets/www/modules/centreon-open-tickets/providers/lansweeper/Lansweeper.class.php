<?php
/*
 * Copyright 2019 Centreon (http://www.centreon.com/)
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

class LansweeperProvider extends AbstractProvider
{
    protected $close_advanced = 1;
    protected $proxy_enabled = 1;

    public const ARG_SUBJECT = 1;
    public const ARG_TYPE = 2;
    public const ARG_PRIORITY = 3;
    public const ARG_TEAM = 4;
    public const ARG_USERNAME = 5;
    public const ARG_DISPLAY_NAME = 6;
    public const ARG_EMAIL = 7;
    public const ARG_AGENT_USERNAME = 8;
    public const ARG_AGENT_EMAIL = 9;
    public const ARG_AGENT_INITIATED = 10;
    public const ARG_PERSONAL = 11;
    public const ARG_DESCRIPTION = 12;
    

    protected $internal_arg_name = [
        self::ARG_DESCRIPTION => 'description',
        self::ARG_SUBJECT => 'subject',
        self::ARG_TYPE => 'type',
        self::ARG_PRIORITY => 'priority',
        self::ARG_TEAM => 'team',
        self::ARG_USERNAME => 'username',
        self::ARG_DISPLAY_NAME => 'display_name',
        self::ARG_EMAIL => 'email',
        self::ARG_AGENT_USERNAME => 'agent_username',
        self::ARG_AGENT_EMAIL => 'agent_email',
        self::ARG_AGENT_INITIATED => 'agent_initiated',
        self::ARG_PERSONAL => 'personal',
    ];  

    /*
    * Set default values for our rule form options
    */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['port'] = '443';
        $this->default_data['api_path'] = '/api.aspx';
        $this->default_data['protocol'] = 'https';
        $this->default_data['api_key'] = '';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = [
            [
                'Arg' => self::ARG_SUBJECT,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ],
            [
                'Arg' => self::ARG_DESCRIPTION,
                'Value' => '{$body}'
            ],
            [
                'Arg' => self::ARG_TYPE,
                'Value' => '{$select.lansweeper_type.id}'
            ],
            [
                'Arg' => self::ARG_PRIORITY,
                'Value' => '{$select.lansweeper_priority.id}'
            ],
            [
                'Arg' => self::ARG_TEAM,
                'Value' => '{$select.lansweeper_team.id}'
            ],
            [
                'Arg' => self::ARG_USERNAME,
                'Value' => '{$user.alias}'
            ],
            [
                'Arg' => self::ARG_DISPLAY_NAME,
                'Value' => '{$select.lansweeper_display_name.value}'
            ],
            [
                'Arg' => self::ARG_EMAIL,
                'Value' => '{$user.email}'
            ],
            [
                'Arg' => self::ARG_AGENT_USERNAME,
                'Value' => '{$select.lansweeper_agent_username.value}'
            ],
            [
                'Arg' => self::ARG_AGENT_EMAIL,
                'Value' => '{$select.lansweeper_agent_email.value}'
            ],
            [
                'Arg' => self::ARG_AGENT_INITIATED,
                'Value' => '{$select.lansweeper_agent_initiated.value}'
            ],
            [
                'Arg' => self::ARG_PERSONAL,
                'Value' => '{$select.lansweeper_personal.value}'
            ]
        ];
    }

    /*
    * Set default values for the widget popup when opening a ticket
    *
    * @return void
    */
    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = 'tobedetermined';

        $this->default_data['clones']['groupList'] = [
            [
                'Id' => 'lansweeper_type',
                'Label' => _('Type'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],[
                'Id' => 'lansweeper_team',
                'Label' => _('Team'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],[
                'Id' => 'lansweeper_priority',
                'Label' => _('Priority'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ]
        ];

        $this->default_data['clones']['customList'] = [
            [
                'Id' => 'lansweeper_type',
                'Value' => 'Hardware',
                'Label' => 'Hardware',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Internet',
                'Label' => 'Internet',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Intranet',
                'Label' => 'Intranet',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Network',
                'Label' => 'Network',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Operating System',
                'Label' => 'Operating System',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Sales',
                'Label' => 'Sales',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Services',
                'Label' => 'Services',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_type',
                'Value' => 'Support',
                'Label' => 'Support',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_priority',
                'Value' => 'Low',
                'Label' => 'Low',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_priority',
                'Value' => 'Medium',
                'Label' => 'Medium',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_priority',
                'Value' => 'High',
                'Label' => 'High',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_tema',
                'Value' => 'Sales',
                'Label' => 'Sales',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_tema',
                'Value' => 'Information Technology',
                'Label' => 'Information Technology',
                'Default' => ''
            ],[
                'Id' => 'lansweeper_tema',
                'Value' => 'Administration',
                'Label' => 'Administration',
                'Default' => ''
            ]
        ];
    }

    /*
    * Verify if every mandatory form field is filled with data
    *
    * @throw \Exception when a form field is not set
    */
    protected function checkConfigForm()
    {
        $this->check_error_message = '';
        $this->check_error_message_append = '';

        $this->checkFormValue('address', 'Please set "Address" value');
        $this->checkFormValue('port', 'Please set "Port" value');
        $this->checkFormValue('api_path', 'Please set "API path" value');
        $this->checkFormValue('protocol', 'Please set "Protocol" value');
        $this->checkFormValue('api_key', 'Please set "API key" value');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    /*
    * Initiate your html configuration and let Smarty display it in the rule form
    */
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/Lansweeper/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', array('EasyVistaRest' => _("Lansweeper")));
        $tpl->assign('webServiceUrl', './api/internal.php');

        /*
        * we create the html that is going to be displayed
        */
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $api_path_html = '<input size="50" name="api_path" type="text" value="' .
            $this->getFormValue('api_path') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="' .
            $this->getFormValue('protocol') . '" />';
        $api_key_html = '<input size="50" name="api_key" type="password" value="' .
            $this->getFormValue('api_key') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" :>';
        $port_html = '<input size="50" name="port" type="text" value="' .
            $this->getFormValue('port') . '" :>';

        // this array is here to link a label with the html code that we've wrote above
        $array_form = array(
            'address' => array(
                'label' => _('Address') . $this->required_field,
                'html' => $address_html
            ),
            'api_path' => array(
                'label' => _('API path') . $this->required_field,
                'html' => $api_path_html
            ),
            'protocol' => array(
                'label' => _('Protocol') . $this->required_field,
                'html' => $protocol_html
            ),
            'api_key' => array(
                'label' => _('API key') . $this->required_field,
                'html' => $api_key_html
            ),
            'timeout' => array(
                'label' => _('Timeout'),
                'html' => $timeout_html
            ),
            'port' => array(
                'label' => _('Port'),
                'html' => $port_html
            ),
            //we add a key to our array
            'mappingTicketLabel' => array(
                'label' => _('Mapping ticket arguments')
            )
        );

        // html
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" ' .
            'name="mappingTicketValue[#index#]" size="20" type="text"';

        // html code for a dropdown list where we will be able to select something from the following list
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" ' .
            'name="mappingTicketArg[#index#]" type="select-one">' .
            '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</option>' .
            '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</option>' .
            '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</option>' .
            '<option value="' . self::ARG_TYPE . '">' ._('Type') . '</option>' .
            '<option value="' . self::ARG_USERNAME . '">' . _('Username') . '</option>' .
            '<option value="' . self::ARG_DISPLAY_NAME . '">' ._('Display name') . '</option>' .
            '<option value="' . self::ARG_EMAIL . '">' . _('Email') . '</option>' .
            '<option value="' . self::ARG_AGENT_INITIATED . '">' . _('Agent initiated') . '</option>' .
            '<option value="' . self::ARG_PERSONAL . '">' . _('Personal') . '</option>' .
            '<option value="' . self::ARG_AGENT_EMAIL . '">' . _('Agent email') . '</option>' .
            '<option value="' . self::ARG_AGENT_USERNAME . '">' . _('Agent username') . '</option>' .
            '<option value="' . self::ARG_TEAM . '">' . _('Team') . '</option>' .
            '</select>';

        // we asociate the label with the html code but for the arguments that we've been working on lately
        $array_form['mappingTicket'] = array(
            array(
                'label' => _('Argument'),
                'html' => $mappingTicketArg_html
            ),
            array(
                'label' => _('Value'),
                'html' => $mappingTicketValue_html
            )
        );

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
    }

    protected function getConfigContainer2Extra()
    {
    }

    /*
    * Saves the rule form in the database
    */
    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['api_path'] = $this->submitted_config['api_path'];
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['api_key'] = $this->submitted_config['api_key'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['simple']['port'] = $this->submitted_config['port'];
        // saves the ticket arguments
        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted('mappingTicket', ['Arg', 'Value']);
    }

    /*
    * Adds new types to the list of types
    *
    * @return {string} $str html code that add an option to a select
    */
    protected function getGroupListOptions()
    {
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
    }

    /*
    * checks if all mandatory fields have been filled
    *
    * @return {array} telling us if there is a missing parameter
    */
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);

        return $result;
    }

    /*
    * brings all parameters together in order to build the ticket arguments and save
    * ticket data in the database
    *
    * @param {object} $db_storage centreon storage database informations
    * @param {array} $contact centreon contact informations
    * @param {array} $host_problems centreon host information
    * @param {array} $service_problems centreon service information
    * @param {array} $extraTicketArguments
    *
    * @return {array} $result will tell us if the submit ticket action resulted in a ticket being opened
    */
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems, $extraTicketArguments = [])
    {
        // initiate a result array
        $result = array(
            'ticket_id' => null,
            'ticket_error_message' => null,
            'ticket_is_ok' => 0,
            'ticket_time' => time()
        );

        // initiate smarty variables
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup(
            $this->centreon_open_tickets_path,
            $tpl,
            'providers/Abstract/templates',
            $this->centreon_path
        );

        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        // assign submitted values from the widget to the template
        $this->assignSubmittedValues($tpl);

        $ticketArguments = $extraTicketArguments;
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            // for each ticket argument in the rule form, we retrieve its value
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $resultString = $tpl->fetch('eval.ihtml');
                
                if ($resultString == '') {
                    $resultString = null;
                }

                $ticketArguments[$this->internal_arg_name[$value['Arg']]] = $resultString;
            }
        }

        // we try to open the ticket
        try {
            $ticketId = $this->createTicket($ticketArguments);
        } catch (\Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }

        // we save ticket data in our database
        $this->saveHistory($db_storage, $result, array(
            'contact' => $contact,
            'host_problems' => $host_problems,
            'service_problems' => $service_problems,
            'ticket_value' => $ticketId,
            'subject' => $ticketArguments[$this->internal_arg_name[self::ARG_SUBJECT]],
            'data_type' => self::DATA_TYPE_JSON,
            'data' => json_encode($ticketArguments)
        ));
        return $result;
    }

    public static function test($info)
    {
        // not implemented because there's no known url to test the api connection
    }

    protected function curlQuery($info)
    {
        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        $info[]

        $curl = curl_init();

        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address')
            . $this->getFormValue('api_path') . $info['query_endpoint'];

        $info['headers'] = [
            "content-type: application/json"
        ];

        if ($this->getFormValue(('use_token') == 1)) {
            array_push($info['headers'], "Authorization: Bearer " . $this->getFormValue('token'));
        }

        // initiate our curl options
        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $info['headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, $info['method']);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->getFormValue('timeout'));

        if ($this->getFormValue('use_token') != 1) {
            curl_setopt($curl, CURLOPT_USERPWD, $this->getFormValue('account') . ":" . $this->getFormValue('token'));
        }

        // add postData if needed
        if (!empty($info['data'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($info['data']));
        }

        // change curl method with a custom one (PUT, DELETE) if needed
        if (isset($info['custom_request'])) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $info['custom_request']);
        }

        // if proxy is set, we add it to curl
        if (
            $this->getFormValue('proxy_address') != ''
            && $this->getFormValue('proxy_port') != ''
        ) {
            curl_setopt(
                $curl,
                CURLOPT_PROXY,
                $this->getFormValue('proxy_address') . ':' . $this->getFormValue('proxy_port')
            );

            // if proxy authentication configuration is set, we add it to curl
            if (
                $this->getFormValue('proxy_username') != ''
                && $this->getFormValue('proxy_password') != ''
            ) {
                curl_setopt(
                    $curl,
                    CURLOPT_PROXYUSERPWD,
                    $this->getFormValue('proxy_username') . ':' . $this->getFormValue('proxy_password')
                );
            }
        }

        // execute curl and get status information
        $curlResult = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // 200 for get operations and 201 for post
        if ($httpCode != 200 && $httpCode != 201) {
            throw new \Exception("An error happened with endpoint: " . $apiAddress
                . ". Easyvista response is: " . $curlResult);
        }

        return json_decode($curlResult, true);
    }

    protected function createTicket($ticketArguments)
    {
        // $file = fopen("/var/log/php-fpm/ezv", "a") or die ("Unable to open file!");

        $info = [
            'Action' => 'AddTicket',
            self::ARG_DESCRIPTION => $ticketArguments[$this->internal_arg_name[self::ARG_DESCRIPTION]],
            self::ARG_SUBJECT => $ticketArguments[$this->internal_arg_name[self::ARG_SUBJECT]]
        ];

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_TYPE]])) {
            $info[self::ARG_TYPE] = $ticketArguments[$this->internal_arg_name[self::ARG_TYPE]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_TEAM]])) {
            $info[self::ARG_TEAM] = $ticketArguments[$this->internal_arg_name[self::ARG_TEAM]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_EMAIL]])) {
            $info[self::ARG_EMAIL] = $ticketArguments[$this->internal_arg_name[self::ARG_EMAIL]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_AGENT_INITIATED]])) {
            $info[self::ARG_AGENT_INITIATED] = $ticketArguments[$this->internal_arg_name[self::ARG_AGENT_INITIATED]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_PERSONAL]])) {
            $info[self::ARG_PERSONAL] = $ticketArguments[$this->internal_arg_name[self::ARG_PERSONAL]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_AGENT_EMAIL]])) {
            $info[self::ARG_AGENT_EMAIL] = $ticketArguments[$this->internal_arg_name[self::ARG_AGENT_EMAIL]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_AGENT_USERNAME]])) {
            $info[self::ARG_AGENT_USERNAME] = $ticketArguments[$this->internal_arg_name[self::ARG_AGENT_USERNAME]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_USERNAME]])) {
            $info[self::ARG_USERNAME] = $ticketArguments[$this->internal_arg_name[self::ARG_USERNAME]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_DISPLAY_NAME]])) {
            $info[self::ARG_DISPLAY_NAME] = $ticketArguments[$this->internal_arg_name[self::ARG_DISPLAY_NAME]];
        }

        if (!empty($ticketArguments[$this->internal_arg_name[self::ARG_PRIORITY]])) {
            $info[self::ARG_PRIORITY] = $ticketArguments[$this->internal_arg_name[self::ARG_PRIORITY]];
        }

//         fwrite($file, print_r("\n ticketargs \n",true));
//         fwrite($file, print_r($ticketArguments,true));
        
// fwrite($file, print_r("\n info \n",true));
// fwrite($file, print_r(json_encode($info['data']),true));
        $result=$this->curlQuery($info);
        preg_match('~' . $this->getFormValue('address') . $this->getFormValue('api_path') . $info['query_endpoint'] . '/(.*)$~', $result['HREF'], $match);
        $ticketId=$match[1];
// fclose($file);

        // return 1234;
        return $ticketId;
    }

    protected function closeTicketLansweeper($ticketId)
    {
        // add the api endpoint and method to our info array
        $info = [
            'Action' => 'EditTicket',
            'TicketId' => $ticketId,
            'State' => 'Closed'
        ];

        try {
            $this->curlQuery($info);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return 0;
    }

    /*
    * check if the close option is enabled, if so, try to close every selected ticket
    *
    * @param {array} $tickets
    *
    * @return void
    */
    public function closeTicket(&$tickets)
    {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                try {
                    $this->closeTicketLansweeper($k);
                    $tickets[$k]['status'] = 2;
                } catch (\Exception $e) {
                    $tickets[$k]['status'] = -1;
                    $tickets[$k]['msg_error'] = $e->getMessage();
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }

    // webservice methods
    public function getHostgroups($centreon_path, $data) {
        $hostCount = count($data['host_list']);
        $listIds = "";

        $queryValues = [];
        foreach ($data['host_list'] as $hostId) {
            $listIds .= ':hId_' . $hostId . ', ';
            $queryValues[':hId_' . $hostId] = (int)$hostId;
        }

        $listIds = rtrim($listIds, ', ');

        require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
        $db_storage = new CentreonDBManager('centstorage');

        $query = "SELECT name FROM hostgroups WHERE hostgroup_id IN"
            . " (SELECT hostgroup_hg_id FROM centreon.hostgroup_relation WHERE host_host_id IN (" . $listIds .")"
            . " GROUP BY hostgroup_hg_id HAVING count(hostgroup_hg_id) = :host_count)";
        
        $dbQuery = $db_storage->prepare($query);
        foreach ($queryValues as $bindName => $bindValue) {
            $dbQuery->bindValue($bindName, $bindValue, PDO::PARAM_INT);
        }
        $dbQuery->bindValue(':host_count', $hostCount, PDO::PARAM_INT).

        $dbQuery->execute();

        $result = [];
        while ($row = $dbQuery->fetch()) {
            array_push($result, $row['name']);
        }

        return $result;
    }
}
