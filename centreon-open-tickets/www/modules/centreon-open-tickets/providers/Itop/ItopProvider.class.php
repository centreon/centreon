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
class ItopProvider extends AbstractProvider
{
    protected $proxy_enabled = 1;
    protected $close_advanced = 1;

    public const ITOP_ORGANIZATION_TYPE = 10;
    public const ITOP_CALLER_TYPE = 11;
    public const ITOP_SERVICE_TYPE = 12;
    public const ITOP_SERVICE_SUBCATEGORY_TYPE = 13;

    public const ARG_CONTENT = 1;
    public const ARG_TITLE = 2;
    public const ARG_ORGANIZATION = 3;
    public const ARG_CALLER = 4;
    public const ARG_ORIGIN = 5;
    public const ARG_SERVICE = 6;
    public const ARG_SERVICE_SUBCATEGORY = 7;
    public const ARG_IMPACT = 8;
    public const ARG_URGENCY = 9;

    protected $internal_arg_name = [
        self::ARG_CONTENT => 'content',
        self::ARG_TITLE => 'title',
        self::ARG_ORGANIZATION => 'organization',
        self::ARG_CALLER => 'caller',
        self::ARG_ORIGIN => 'origin',
        self::ARG_SERVICE => 'service',
        self::ARG_SERVICE_SUBCATEGORY => 'service_subcategory',
        self::ARG_IMPACT => 'impact',
        self::ARG_URGENCY => 'urgency'
    ];

    /*
    * Set default values for our rule form options
    *
    * @return {void}
    */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '10.30.2.22/itop/web';
        $this->default_data['api_version'] = '1.4';
        $this->default_data['username'] = '';
        $this->default_data['password'] = '';
        $this->default_data['protocol'] = 'https';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_CONTENT,
                'Value' => '{$body}'
            ),
            array(
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array(
                'Arg' => self::ARG_ORGANIZATION,
                'Value' => '{$select.itop_organization.id}'
            ),
            array(
                'Arg' => self::ARG_CALLER,
                'Value' => '{$select.itop_caller.id}'
            ),
            array(
                'Arg' => self::ARG_ORIGIN,
                'Value' => '{$select.itop_origin.value}'
            ),
            array(
                'Arg' => self::ARG_SERVICE,
                'Value' => '{$select.itop_service.id}'
            ),
            array(
                'Arg' => self::ARG_SERVICE_SUBCATEGORY,
                'Value' => '{$select.itop_service_subcategory.id}'
            ),
            array(
                'Arg' => self::ARG_IMPACT,
                'Value' => '{$select.itop_impact.value}'
            ),
            array(
                'Arg' => self::ARG_URGENCY,
                'Value' => '{$select.itop_urgency.value}'
            )
        );
    }

    /*
    * Set default values for the widget popup when opening a ticket
    *
    * @return {void}
    */
    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html = 0);

        $this->default_data['url'] = '{$protocol}://{$address}/pages/UI.php?operation=details'
            . '&class=UserRequest&id={$ticket_id}';

        $this->default_data['format_popup'] = '
<table class="table">
<tr>
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
<tr>
    <td class="FormRowField" style="padding-left:15px;">{$custom_message.label}</td>
    <td class="FormRowValue" style="padding-left:15px;">
        <textarea id="custom_message" name="custom_message" cols="50" rows="6"></textarea>
    </td>
</tr>
{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
{include file="file:$centreon_open_tickets_path/providers/Itop/templates/format_popup_requiredFields.ihtml"}
</table>
';

        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'itop_organization',
                'Label' => _('Organization'),
                'Type' => self::ITOP_ORGANIZATION_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_caller',
                'Label' => _('Caller'),
                'Type' => self::ITOP_CALLER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_service',
                'Label' => _('Service'),
                'Type' => self::ITOP_SERVICE_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_service_subcategory',
                'Label' => _('Service Subcategory'),
                'Type' => self::ITOP_SERVICE_SUBCATEGORY_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_origin',
                'Label' => _('Origin'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_urgency',
                'Label' => _('Urgency'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'itop_impact',
                'Label' => _('Impact'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            )
        );

        $this->default_data['clones']['customList'] = array(
            array(
                'Id' => 'itop_origin',
                'Value' => 'mail',
                'Label' => 'email',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_origin',
                'Value' => 'monitoring',
                'Label' => 'monitoring',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_origin',
                'Value' => 'phone',
                'Label' => 'phone',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_origin',
                'Value' => 'portal',
                'Label' => 'portal',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_origin',
                'Value' => 'mail',
                'Label' => 'email',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_impact',
                'Value' => '1',
                'Label' => 'A department',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_impact',
                'Value' => '2',
                'Label' => 'A service',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_impact',
                'Value' => '3',
                'Label' => 'A person',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_urgency',
                'Value' => '1',
                'Label' => 'critical',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_urgency',
                'Value' => '2',
                'Label' => 'high',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_urgency',
                'Value' => '3',
                'Label' => 'medium',
                'Default' => ''
            ),
            array(
                'Id' => 'itop_urgency',
                'Value' => '4',
                'Label' => 'low',
                'Default' => ''
            )
        );
    }

    /*
    * Verify if every mandatory form field is filled with data
    *
    * @return {void}
    *
    * @throw \Exception when a form field is not set
    */
    protected function checkConfigForm()
    {
        $this->check_error_message = '';
        $this->check_error_message_append = '';

        $this->checkFormValue('address', 'Please set the "Address" value');
        $this->checkFormValue('api_version', 'Please set the "API version" value');
        $this->checkFormValue('username', 'Please set the "Username" value');
        $this->checkFormValue('password', 'Please set the "Password" value');
        $this->checkFormValue('protocol', 'Please set the "Protocol" value');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new \Exception($this->check_error_message);
        }
    }

    /*
    * Initiate your html configuration and lets Smarty display it in the rule form
    *
    * return {void}
    */
    protected function getConfigContainer1Extra()
    {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup(
            $this->centreon_open_tickets_path,
            $tpl,
            'providers/Itop/templates',
            $this->centreon_path
        );
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', array('Itop' => _("Itop Rest Api")));
        $tpl->assign('webServiceUrl', './api/internal.php');

        // we create the html that is going to be displayed
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' .
            $this->getFormValue('password') . '" />';
        $api_version_html = '<input size="50" name="api_version" type="text" value ="' .
            $this->getFormValue('api_version') . '" />';
        $protocol_html = '<input size="2" name="protocol" type="text" value="' .
            $this->getFormValue('protocol') . '" />';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" />';

        // this array is here to link a label with the html code that we've wrote above
        $array_form = array(
             'address' => array(
                 'label' => _('Address') . $this->required_field,
                 'html' => $address_html
             ),
             'username' => array(
                 'label' => _('Username') . $this->required_field,
                 'html' => $username_html
             ),
             'password' => array(
                 'label' => _('Password') . $this->required_field,
                 'html' => $password_html
             ),
             'api_version' => array(
                 'label' => _('API version') . $this->required_field,
                 'html' => $api_version_html
             ),
             'protocol' => array(
                 'label' => _("Protocol"),
                 'html' => $protocol_html
             ),
             'timeout' => array(
                 'label' => _("Timeout"),
                 'html' => $timeout_html
             ),
             'mappingticket' => array(
                 'label' => _("Mapping ticket arguments")
             )
        );

        // html code for a dropdown list where we will be able to select something from the following list
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" ' .
            'name="mappingTicketValue[#index#]" size="20" type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" ' .
            'name="mappingTicketArg[#index#]" type="select-one">' .
            '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
            '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
            '<option value="' . self::ARG_ORGANIZATION . '">' . _('Organization') . '</options>' .
            '<option value="' . self::ARG_SERVICE . '">' . _('Service') . '</options>' .
            '<option value="' . self::ARG_SERVICE_SUBCATEGORY . '">' . _('Service Subcategory') . '</options>' .
            '<option value="' . self::ARG_ORIGIN . '">' . _('Origin') . '</options>' .
            '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
            '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</options>' .
            '<option value="' . self::ARG_CALLER . '">' . _('Caller') . '</options>' .
            '</select>';

        $array_form['mappingTicket'] = array(
            array(
                'label' => _("Argument"),
                'html' => $mappingTicketArg_html
            ),
            array(
                'label' => _("Value"),
                'html' => $mappingTicketValue_html),
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
    *
    * @return {void}
    */
    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['api_version'] = $this->submitted_config['api_version'];
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            ['Arg', 'Value']
        );
    }

    /*
    * Adds new types to the list of types
    *
    * @return {string} $str html code that add an option to a select
    */
    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::ITOP_SERVICE_TYPE . '">Service</option>' .
            '<option value="' . self::ITOP_CALLER_TYPE . '">Caller</option>' .
            '<option value="' . self::ITOP_ORGANIZATION_TYPE . '">Organization</option>' .
            '<option value="' . self::ITOP_SERVICE_SUBCATEGORY_TYPE . '">Service subcategory</option>';
        return $str;
    }

    /*
    * configure variables with the data provided by the itop api
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from itop
    *
    * @return {void}
    */
    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::ITOP_ORGANIZATION_TYPE) {
            $this->assignItopOrganizations($entry, $groups_order, $groups);
        } elseif (
            $entry['Type'] == self::ITOP_CALLER_TYPE
            || $entry['Type'] == self::ITOP_SERVICE_TYPE
            || $entry['Type'] == self::ITOP_SERVICE_SUBCATEGORY_TYPE
        ) {
                $this->assignItopAjax($entry, $groups_order, $groups);
        }
    }

    /*
    * handle gathered organizations
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from itop
    *
    * @return {void}
    *
    * throw \Exception if we can't get organizations from itop
    */
    protected function assignItopOrganizations($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
                (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );

        $groups_order[] = $entry['Id'];

        try {
            $listOrganizations = $this->getCache($entry['Id']);
            if (is_null($listOrganizations)) {
                // if no organizations were found in cache, get them from itop and put them in cache for 8 hours
                $listOrganizations = $this->getOrganizations();
                $this->setCache($entry['Id'], $listOrganizations, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }
        $result = array();

        foreach ($listOrganizations['objects'] as $organization) {
            // foreach organization found, if we don't have any filter configured,
            // we just put the id and the name of the organization inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$organization['key']] = $this->to_utf8($organization['fields']['name']);
            continue;
        }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the organization inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $organization['fields']['name'])) {
                $result[$organization['key']] = $this->to_utf8($organization['fields']['name']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * initiate information for dynamic (ajax) fields like callers, services or service subcategories
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from itop
    *
    * @return {void}
    */
    protected function assignItopAjax($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
                (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0),
            'filter' => $entry['Filter']
        );

        $groups_order[] = $entry['Id'];

        $groups[$entry['Id']]['values'] = '';
    }

    /*
    * checks if all mandatory fields have been filled
    *
    * @return {array} telling us if there is a missing parameter
    */
    public function validateFormatPopup()
    {
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
        $tpl = $this->initSmartyTemplate();
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
                    $resultstring = null;
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
            'subject' => $ticketArguments[self::ARG_TITLE],
            'data_type' => self::DATA_TYPE_JSON,
            'data' => json_encode($ticketArguments)
        ));
        return $result;
    }

    /*
    * test if we can reach Itop webservice with the given Configuration
    *
    * @param {array} $info required information to reach the itop api
    *
    * @return {bool}
    *
    * throw \Exception if there are some missing parameters
    * throw \Exception if the connection failed
    */
    public static function test($info)
    {
        // this is called through our javascript code. Those parameters are already checked in JS code.
        // but since this function is public, we check again because anyone could use this function
        if (
            !isset($info['address'])
            || !isset($info['api_version'])
            || !isset($info['username'])
            || !isset($info['password'])
            || !isset($info['protocol'])
        ) {
                throw new \Exception('missing arguments', 13);
        }
        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        $curl = curl_init();
        $apiAddress = $info['protocol'] . '://' . $info['address'] . '/webservices/rest.php?version=' .
        $info['api_version'];

        $data = array(
            'operation' => 'list_operations'
        );

        $query = array(
            'auth_user' => $info['username'],
            'auth_pwd' => $info['password'],
            'json_data' => json_encode($data)
        );

        // initiate our curl options
        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $info['timeout']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($query));
        // execute curl and get status information
        $curlResult = json_decode(curl_exec($curl), true);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            // return false;
            throw new Exception('curl result: ' . $curlResult . '|| HTTP return code: ' . $httpCode, 1);
        }

        if ($curlResult['code'] !== 0) {
            throw new \Exception($curlResult['message']);
        }

        return true;
    }

    /*
    * handle every query that we need to do
    *
    * @param {array} $info required information to reach the itop api
    *
    * @return {array} $curlResult the json decoded data gathered from itop
    *
    * throw \Exception 10 if php-curl is not installed
    * throw \Exception 11 if itop api fails
    */
    protected function curlQuery($data)
    {
        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        $query = array(
            'auth_user' => $this->getFormValue('username'),
            'auth_pwd' => $this->getFormValue('password'),
            'json_data' => json_encode($data)
        );

        $curl = curl_init();
        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address') .
        '/webservices/rest.php?version=' . $this->getFormValue('api_version');
        // initiate our curl options
        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($query));
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->getFormValue('timeout'));

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
        $curlResult = json_decode(curl_exec($curl), true);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception('ERROR: ' . $curlResult . ' || HTTP ERROR: ' . $httpCode, 11);
        }

        if ($curlResult['code'] !== 0) {
            throw new \Exception($curlResult['message'], $curlResult['code']);
        }

        return $curlResult;
    }

    /*
    * get organizations from itop
    *
    * @return {array} $organizations list of organizations
    *
    * throw \Exception if we can't get organizations data
    */
    protected function getOrganizations()
    {
        $key = "SELECT Organization WHERE status='active'";

        $data = array(
            'operation' => 'core/get',
            'class' => 'Organization',
            'key' => $key,
            'output_fields' => 'name'
        );

        try {
            $organizations = $this->curlQuery($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $organizations;
    }

    /*
    * get callers from itop
    *
    * $param {array} $data selected organization and ITOP_CALLER_TYPE group data
    *
    * @return {array} $listCallers list of callers
    *
    * throw \Exception if we can't get callers data
    */
    public function getCallers($data)
    {
        $key = "SELECT Person WHERE status='active'";

        if (preg_match('/(.*?)___(.*)/', $data['organization_value'], $matches)) {
            $key .= " AND org_id='" . $matches[1] . "'";
        } else {
            throw new \Exception('No organization found', 1);
        }

        $filter = $data['groups']['itop_caller']['filter'];
        if (isset($filter) && $filter != '') {
            $key .= " AND friendlyname LIKE '%" . $filter . "%'";
        }

        $data = array(
            'operation' => 'core/get',
            'class' => 'Person',
            'key' => $key,
            'output_fields' => 'friendlyname'
        );

        try {
            $listCallers = $this->getCache('itop_caller_' . $matches[1]);
            if (is_null($listCallers)) {
                // if no callers were found in cache, get them from itop and put them in cache for 8 hours
                $listCallers = $this->curlQuery($data);
                $this->setCache('itop_caller_' . $matches[1], $listCallers, 8 * 3600);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $listCallers;
    }

    /*
    * get services from itop
    *
    * $param {array} $data selected organization and ITOP_SERVICE_TYPE group data
    *
    * @return {array} $listServices list of services
    *
    * throw \Exception if we can't get services data
    */
    public function getServices($data)
    {
        $key = "SELECT Service";

        if (preg_match('/(.*?)___(.*)/', $data['organization_value'], $matches)) {
            $key .= " WHERE org_id='" . $matches[1] . "'";
        } else {
            throw new \Exception('No organization found', 1);
        }

        $filter = $data['groups']['itop_service']['filter'];
        if (isset($filter) && $filter != '') {
            $key .= " AND friendlyname LIKE '%" . $filter . "%'";
        }

        $data = array(
            'operation' => 'core/get',
            'class' => 'Service',
            'key' => $key,
            'output_fields' => 'friendlyname'
        );


        try {
            $listServices = $this->getCache('itop_service_' . $matches[1]);
            if (is_null($listServices)) {
                // if no callers were found in cache, get them from itop and put them in cache for 8 hours
                $listServices = $this->curlQuery($data);
                $this->setCache('itop_service_' . $matches[1], $listServices, 8 * 3600);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $listServices;
    }

    /*
    * get service subcategories from itop
    *
    * $param {array} $data selected service and ITOP_SERVICE_SUBCATEGORY_TYPE group data
    *
    * @return {array} $listServiceSubcategories list of service subcategories
    *
    * throw \Exception if we can't get service subcategories data
    */
    public function getServiceSubcategories($data)
    {
        $key = "SELECT ServiceSubcategory";

        if (preg_match('/(.*?)___(.*)/', $data['service_value'], $matches)) {
            $key .= " WHERE service_id='" . $matches[1] . "'";
        } else {
            throw new \Exception('No service found', 1);
        }

        $filter = $data['groups']['itop_service_subcategory']['filter'];
        if (isset($filter) && $filter != '') {
            $key .= " AND friendlyname LIKE '%" . $filter . "%'";
        }

        $data = array(
            'operation' => 'core/get',
            'class' => 'ServiceSubcategory',
            'key' => $key,
            'output_fields' => 'friendlyname'
        );

        try {
            $listServiceSubcategories = $this->getCache('itop_service_subcategory_' . $matches[1]);
            if (is_null($listServiceSubcategories)) {
                // if no callers were found in cache, get them from itop and put them in cache for 8 hours
                $listServiceSubcategories = $this->curlQuery($data);
                $this->setCache('itop_service_subcategory_' . $matches[1], $listServiceSubcategories, 8 * 3600);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $listServiceSubcategories;
    }

    protected function createTicket($ticketArguments)
    {
        $data = array (
            'operation' => 'core/create',
            'class' => 'UserRequest',
            'output_fields' => 'id',
            'comment' => 'Opened from Centreon',
            'fields' => array (
                'description' => $ticketArguments['content'],
                'title' => $ticketArguments['title']
            )
        );

        if (isset($ticketArguments['organization']) &&
            $ticketArguments['organization'] != '' &&
            $ticketArguments['organization'] != -1) {
                $data['fields']['org_id'] = $ticketArguments['organization'];
        }

        if (isset($ticketArguments['service']) &&
            $ticketArguments['service'] != '' &&
            $ticketArguments['service'] != -1) {
                $data['fields']['service_id'] = $ticketArguments['service'];
        }

        if (isset($ticketArguments['service_subcategory']) &&
            $ticketArguments['service_subcategory'] != '' &&
            $ticketArguments['service_subcategory'] != -1) {
                $data['fields']['servicesubcategory_id'] = $ticketArguments['service_subcategory'];
        }

        if (isset($ticketArguments['caller']) &&
            $ticketArguments['caller'] != '' &&
            $ticketArguments['caller'] != -1) {
                $data['fields']['caller_id'] = $ticketArguments['caller'];
        }


        if (isset($ticketArguments['urgency']) &&
            $ticketArguments['urgency'] != '') {
                $data['fields']['urgency'] = $ticketArguments['urgency'];
        }

        if (isset($ticketArguments['origin']) &&
            $ticketArguments['origin'] != '') {
                $data['fields']['origin'] = $ticketArguments['origin'];
        }

        if (isset($ticketArguments['impact']) &&
            $ticketArguments['impact'] != '') {
                $data['fields']['impact'] = $ticketArguments['impact'];
        }
        $result = $this->curlQuery($data);

        foreach ($result['objects'] as $ticket) {
            $ticketId = $ticket['fields']['id'];
        }

        return $ticketId;
    }

    /*
    * close a ticket in Itop
    *
    * @params {string} $ticketId the ticket id
    *
    * @return {bool}
    *
    * throw \Exception if it can't close the ticket
    */
    protected function closeTicketItop($ticketId)
    {

        $data = array(
            'operation' => 'core/update',
            'class' => 'UserRequest',
            'comment' => 'Closing ticket from Centreon',
            'key' => $ticketId,
            'fields' => array(
                'status' => 'closed'
            )
        );

        try {
            $result = $this->curlQuery($data);
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
    * @return {void}
    */
    public function closeTicket(&$tickets)
    {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                try {
                    $this->closeTicketItop($k);
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
}
