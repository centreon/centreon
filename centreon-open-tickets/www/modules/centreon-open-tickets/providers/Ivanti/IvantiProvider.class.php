<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

use Centreon\Domain\Log\LoggerTrait;

class IvantiProvider extends AbstractProvider
{
    use LoggerTrait;
    public const IVANTI_EMPLOYEE_TYPE = 14;
    public const IVANTI_TEAM_TYPE = 15;
    public const IVANTI_CATEGORY_TYPE = 16;
    public const ARG_TITLE = 1;
    public const ARG_SYMPTOM = 2;
    public const ARG_CATEGORY = 3;
    public const ARG_IMPACT = 4;
    public const ARG_URGENCY = 5;
    public const ARG_EMPLOYEE = 7;
    public const ARG_TEAM = 8;
    public const ARG_STATUS = 9;
    public const ARG_SOURCE = 10;
    public const ARG_PROFILE_LINK = 11;
    public const ARG_ALTERNATE_CONTACT_LINK = 12;
    public const ARG_SERVICE = 13;
    public const ARG_IVANTI_CUSTOM_FIELD = 14;

    protected $close_advanced = 1;

    protected $proxy_enabled = 1;

    /** @var null|array */
    protected $ivantiCallResult;

    protected $internal_arg_name = [
        self::ARG_TITLE => 'title',
        self::ARG_SYMPTOM => 'symptom',
        self::ARG_CATEGORY => 'category',
        self::ARG_IMPACT => 'impact',
        self::ARG_URGENCY => 'urgency',
        self::ARG_EMPLOYEE => 'employee',
        self::ARG_TEAM => 'team',
        self::ARG_STATUS => 'status',
        self::ARG_SOURCE => 'source',
        self::ARG_PROFILE_LINK => 'profile_link',
        self::ARG_ALTERNATE_CONTACT_LINK => 'alternate_contact_link',
        self::ARG_SERVICE => 'service',
        self::ARG_IVANTI_CUSTOM_FIELD => 'ivanti_custom_field',
    ];

    /*
    * checks if all mandatory fields have been filled
    *
    * @return {array} telling us if there is a missing parameter
    */
    public function validateFormatPopup()
    {
        $result = ['code' => 0, 'message' => 'ok'];
        $this->validateFormatPopupLists($result);

        return $result;
    }

    /*
    * test if we can reach Ivanti webservice with the given Configuration
    *
    * @param {array} $info required information to reach the ivanti api
    *
    * @return {bool}
    *
    * throw \Exception if there are some missing parameters
    * throw \Exception if the connection failed
    */
    public static function test($info)
    {
        if (! isset($info['address']) || ! isset($info['apiKey'])) {
            throw new Exception('missing parameters : address or apiKey.');
        }

        if (! extension_loaded('curl')) {
            throw new Exception('PHP curl extension is missing');
        }

        $curl = curl_init();
        $apiPath = rtrim($info['api_path'] ?? '/HEAT/api/odata/businessobject', '/');
        $apiAddress = $info['protocol'] . '://' . $info['address'] . $apiPath . '/incidents?$top=1';

        $headers = [
            'Authorization: rest_api_key=' . $info['apiKey'],
            'Content-Type: application/json',
        ];

        $peerVerify = ($info['peer_verify'] ?? 'yes') === 'yes';
        $verifyHost = $peerVerify ? 2 : 0;
        $caCertPath = $info['ca_cert_path'] ?? '';
        $timeout = max(1, (int) ($info['timeout'] ?? 60));

        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $peerVerify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifyHost);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        // Use custom CA only when verification is enabled
        if ($peerVerify && is_string($caCertPath) && $caCertPath !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
        }

        $curlResult = curl_exec($curl);

        if ($curlResult === false) {
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            throw new Exception("Ivanti transport error ({$curlErrNo}): {$curlError}", 11);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception("Ivanti api connection error: HTTP {$httpCode} - {$curlResult}", 11);
        }

        return true;
    }

    /*
    * check if the close option is enabled, if so, try to close every selected ticket
    *
    * @param {array} $tickets
    *
    * @return void
    */
    public function closeTicket(&$tickets): void
    {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $key => $ticket) {
                try {
                    $ticketId = $ticket['ticket_id'];
                    $this->closeTicketIvanti($ticketId);
                    $tickets[$key]['status'] = 2;
                } catch (Exception $e) {
                    $tickets[$key]['status'] = -1;
                    $tickets[$key]['msg_error'] = $e->getMessage();
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }

    // Set default values for our rule form options
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['api_path'] = '/HEAT/api/odata/businessobject';
        $this->default_data['protocol'] = 'https';
        $this->default_data['apiKey'] = '';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = [
            [
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers'
                    . '/Abstract/templates/display_title.ihtml"}',
            ],
            [
                'Arg' => self::ARG_SYMPTOM,
                'Value' => '{$body}',
            ],
            [
                'Arg' => self::ARG_CATEGORY,
                'Value' => '{$select.ivanti_category.value}',
            ],
            [
                'Arg' => self::ARG_EMPLOYEE,
                'Value' => '{$select.ivanti_employee.value}',
            ],
            [
                'Arg' => self::ARG_TEAM,
                'Value' => '{$select.ivanti_team.value}',
            ],
            [
                'Arg' => self::ARG_IMPACT,
                'Value' => '{$select.impact.value}',
            ],
            [
                'Arg' => self::ARG_URGENCY,
                'Value' => '{$select.urgency.value}',
            ],
            [
                'Arg' => self::ARG_STATUS,
                'Value' => '{$select.status.value}',
            ],
            [
                'Arg' => self::ARG_SOURCE,
                'Value' => '{$select.source.value}',
            ],
            [
                'Arg' => self::ARG_PROFILE_LINK,
                'Value' => '{$select.profile_link.value}',
            ],
            [
                'Arg' => self::ARG_ALTERNATE_CONTACT_LINK,
                'Value' => '{$select.alternate_contact_link.value}',
            ],
            [
                'Arg' => self::ARG_SERVICE,
                'Value' => '{$select.service.value}',
            ],
            [
                'Arg' => self::ARG_IVANTI_CUSTOM_FIELD,
                'Value' => '{$select._cf_xxxxxxxxxx.value}',
            ],
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

        $this->default_data['url'] = '{$protocol}://{$address}/HEAT/login.aspx?Scope=ObjectWorkspace&CommandId=Search&ObjectType=Incident%23&CommandData=RecId%2C%3D%2C0%2C{$ticket_id}%2Cstring%2CAND%2C%7C';

        $this->default_data['clones']['groupList'] = [
            [
                'Id' => 'ivanti_category',
                'Label' => _('Category'),
                'Type' => self::IVANTI_CATEGORY_TYPE,
                'Filter' => '', 'Mandatory' => '',
            ],
            [
                'Id' => 'ivanti_employee',
                'Label' => _('Employee'),
                'Type' => self::IVANTI_EMPLOYEE_TYPE,
                'Filter' => '', 'Mandatory' => '',
            ],
            [
                'Id' => 'ivanti_team',
                'Label' => _('Team'),
                'Type' => self::IVANTI_TEAM_TYPE,
                'Filter' => '', 'Mandatory' => '',
            ],
            [
                'Id' => 'urgency',
                'Label' => _('Urgency'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'impact',
                'Label' => _('Impact'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'source',
                'Label' => _('Source'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'profile_link',
                'Label' => _('Profile link'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => 1,
            ],
            [
                'Id' => 'alternate_contact_link',
                'Label' => _('Alternate contact link'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'status',
                'Label' => _('Status'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => 1,
            ],
            [
                'Id' => 'service',
                'Label' => _('Service'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => '_cf_xxxxxxxxxx',
                'Label' => _('Ivanti Custom Field'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
        ];

        $this->default_data['clones']['customList'] = [
            [
                'Id' => 'impact',
                'Value' => 'Low',
                'Label' => 'Low',
                'Default' => '',
            ],
            [
                'Id' => 'impact',
                'Value' => 'Medium',
                'Label' => 'Medium',
                'Default' => '',
            ],
            [
                'Id' => 'impact',
                'Value' => 'High',
                'Label' => 'High',
                'Default' => '',
            ],
            [
                'Id' => 'urgency',
                'Value' => 'Low',
                'Label' => 'Low',
                'Default' => '',
            ],
            [
                'Id' => 'urgency',
                'Value' => 'Medium',
                'Label' => 'Medium',
                'Default' => '',
            ],
            [
                'Id' => 'urgency',
                'Value' => 'High',
                'Label' => 'High',
                'Default' => '',
            ],
            [
                'Id' => 'status',
                'Value' => 'Logged',
                'Label' => 'Logged',
                'Default' => 1,
            ],
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
        $this->checkFormValue('api_path', 'Please set "API path" value');
        $this->checkFormValue('protocol', 'Please set "Protocol" value');
        $this->checkFormValue('apiKey', 'Please set "API Key" value');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    // Initiate your html configuration and let Smarty display it in the rule form
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/Ivanti/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', ['Ivanti' => _('Ivanti')]);
        $tpl->assign('webServiceUrl', './api/internal.php');

        // we create the html that is going to be displayed
        $address_html = '<input size="50" name="address" type="text" value="'
            . $this->getFormValue('address') . '" />';
        $api_path_html = '<input size="50" name="api_path" type="text" value="'
            . $this->getFormValue('api_path') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="'
            . $this->getFormValue('protocol') . '" />';
        $apiKey_html = '<input size="50" name="apiKey" type="password" value="'
            . $this->getFormValue('apiKey') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="'
            . $this->getFormValue('timeout') . '" />';

        // this array is here to link a label with the html code that we've wrote above
        $array_form = [
            'address' => ['label' => _('Address') . $this->required_field, 'html' => $address_html],
            'api_path' => ['label' => _('API path') . $this->required_field, 'html' => $api_path_html],
            'protocol' => ['label' => _('Protocol') . $this->required_field, 'html' => $protocol_html],
            'apiKey' => ['label' => _('API Key') . $this->required_field, 'html' => $apiKey_html],
            'timeout' => ['label' => _('Timeout'), 'html' => $timeout_html],
            // we add a key to our array
            'mappingTicketLabel' => ['label' => _('Mapping ticket arguments')],
        ];

        // html
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" '
            . 'name="mappingTicketValue[#index#]" size="20" type="text"';

        // html code for a dropdown list where we will be able to select something from the following list
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" '
            . 'name="mappingTicketArg[#index#]" type="select-one">'
            . '<option value="' . self::ARG_TITLE . '">' . _('Subject') . '</option>'
            . '<option value="' . self::ARG_SYMPTOM . '">' . _('Symptom') . '</option>'
            . '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</option>'
            . '<option value="' . self::ARG_EMPLOYEE . '">' . _('Employee') . '</option>'
            . '<option value="' . self::ARG_TEAM . '">' . _('Team') . '</option>'
            . '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</option>'
            . '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</option>'
            . '<option value="' . self::ARG_STATUS . '">' . _('Status') . '</option>'
            . '<option value="' . self::ARG_SOURCE . '">' . _('Source') . '</option>'
            . '<option value="' . self::ARG_PROFILE_LINK . '">' . _('Profile link') . '</option>'
            . '<option value="' . self::ARG_ALTERNATE_CONTACT_LINK . '">' . _('Alternate contact link') . '</option>'
            . '<option value="' . self::ARG_SERVICE . '">' . _('Service') . '</option>'
            . '<option value="' . self::ARG_IVANTI_CUSTOM_FIELD . '">' . _('Ivanti Custom Field') . '</option>'
            . '</select>';

        // we asociate the label with the html code but for the arguments that we've been working on lately
        $array_form['mappingTicket'] = [['label' => _('Argument'), 'html' => $mappingTicketArg_html], ['label' => _('Value'), 'html' => $mappingTicketValue_html]];

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
    }

    protected function getConfigContainer2Extra()
    {
    }

    // Saves the rule form in the database
    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['api_path'] = $this->submitted_config['api_path'];
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['apiKey'] = $this->submitted_config['apiKey'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

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
        return '<option value="' . self::IVANTI_CATEGORY_TYPE . '">Category</option>'
            . '<option value="' . self::IVANTI_EMPLOYEE_TYPE . '">Employee</option>'
            . '<option value="' . self::IVANTI_TEAM_TYPE . '">Team</option>';
    }

    /*
    * configure variables with the data provided by the ivanti api
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from ivanti
    *
    * @return void
    */
    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::IVANTI_CATEGORY_TYPE) {
            $this->assignIvantiCategories($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::IVANTI_EMPLOYEE_TYPE) {
            $this->assignIvantiUsers($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::IVANTI_TEAM_TYPE) {
            $this->assignIvantiTeams($entry, $groups_order, $groups);
        }
    }

    /*
    * handle gathered users
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from ivanti
    *
    * @return void
    *
    * throw \Exception if we can't get users from ivanti
    */
    protected function assignIvantiUsers($entry, &$groups_order, &$groups)
    {
        // Ajoute un label à l'entrée et active le tri si nécessaire
        $groups[$entry['Id']] = [
            'label' => _($entry['Label']) . (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0),
        ];
        // Ajoute l'entrée dans le tableau d'ordre des groupes
        $groups_order[] = $entry['Id'];

        // Récupère les utilisateurs depuis le cache ou l'API
        try {
            $listUsers = $this->getCache($entry['Id']);
            if (is_null($listUsers)) {
                $listUsers = $this->getUsers();
                $this->setCache($entry['Id'], $listUsers, 8 * 3600); // Cache pour 8 heures
            }
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();

            return;
        }

        $result = [];
        foreach ($listUsers['value'] ?? [] as $user) {
            // Si aucun filtre n'est configuré, ajoute tous les utilisateurs
            if (! isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$user['RecId']] = $this->to_utf8($user['FirstName'] . ' ' . $user['LastName']);
                continue;
            }
            // Applique le filtre si configuré
            if (preg_match('/' . $entry['Filter'] . '/', $user['FirstName'] . ' ' . $user['LastName'])) {
                $result[$user['RecId']] = $this->to_utf8($user['FirstName'] . ' ' . $user['LastName']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /**
     * Assigne les équipes récupérées depuis l'API Ivanti à un groupe de configuration.
     *
     * @param array $entry configuration de l'entrée pour les équipes
     * @param array &$groups_order Ordre des groupes
     * @param array &$groups Tableau des groupes
     * @return void
     */
    protected function assignIvantiTeams($entry, &$groups_order, &$groups)
    {
        // Ajoute un label à l'entrée et active le tri si nécessaire
        $groups[$entry['Id']] = [
            'label' => _($entry['Label']) . (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0),
        ];

        // Ajoute l'entrée dans le tableau d'ordre des groupes
        $groups_order[] = $entry['Id'];

        // Récupère les équipes depuis le cache ou l'API
        try {
            $listTeams = $this->getCache($entry['Id']);
            if (is_null($listTeams)) {
                $listTeams = $this->getTeams();
                $this->setCache($entry['Id'], $listTeams, 8 * 3600); // Cache pour 8 heures
            }
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();

            return;
        }

        $result = [];
        foreach ($listTeams['value'] ?? [] as $team) {
            // Si aucun filtre n'est configuré, ajoute toutes les équipes
            if (! isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$team['RecId']] = $this->to_utf8($team['Team']);
                continue;
            }
            // Applique le filtre si configuré
            if (preg_match('/' . $entry['Filter'] . '/', $team['Team'])) {
                $result[$team['RecId']] = $this->to_utf8($team['Team']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered categories
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from ivanti
    *
    * @return void
    *
    * throw \Exception if we can't get suppliers from ivanti
    */
    protected function assignIvantiCategories($entry, &$groups_order, &$groups)
    {
        // Ajoute un label à l'entrée et active le tri si nécessaire
        $groups[$entry['Id']] = [
            'label' => _($entry['Label']) . (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0),
        ];
        // Ajoute l'entrée dans le tableau d'ordre des groupes
        $groups_order[] = $entry['Id'];

        // Récupère les catégories depuis le cache ou l'API
        try {
            $listCategories = $this->getCache($entry['Id']);
            if (is_null($listCategories)) {
                $listCategories = $this->getCategories();
                $this->setCache($entry['Id'], $listCategories, 8 * 3600); // Cache pour 8 heures
            }
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();

            return;
        }

        $result = [];
        foreach ($listCategories['value'] ?? [] as $category) {
            // Si aucun filtre n'est configuré, ajoute toutes les catégories
            if (! isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$category['RecId']] = $this->to_utf8($category['Category']);
                continue;
            }
            // Applique le filtre si configuré
            if (preg_match('/' . $entry['Filter'] . '/', $category['Category'])) {
                $result[$category['RecId']] = $this->to_utf8($category['Category']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
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
        $result = ['ticket_id' => null, 'ticket_error_message' => null, 'ticket_is_ok' => 0, 'ticket_time' => time()];

        // Smarty template initialization
        $tpl = SmartyBC::createSmartyTemplate($this->centreon_open_tickets_path, 'providers/Abstract/templates');

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

                // specific condition to handle custom field "dynamically"
                if ($this->internal_arg_name[$value['Arg']] == $this->internal_arg_name[self::ARG_IVANTI_CUSTOM_FIELD]) {
                    $ticketArguments[$value['Value']] = $resultString;
                } else {
                    $ticketArguments[$this->internal_arg_name[$value['Arg']]] = $resultString;
                }
            }
        }

        // we try to open the ticket
        try {
            $ticketId = $this->createTicket($ticketArguments);
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();

            return $result;
        }

        // we save ticket data in our database
        $this->saveHistory($db_storage, $result, ['contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems, 'ticket_value' => $ticketId, 'subject' => $ticketArguments[$this->internal_arg_name[self::ARG_TITLE]], 'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode($ticketArguments)]);

        return $result;
    }

    /*
    * handle every query that we need to do
    *
    * @param {array} $info required information to reach the ivanti api
    *
    * @return {array} $curlResult the json decoded data gathered from ivanti
    *
    * throw \Exception 10 if php-curl is not installed
    * throw \Exception 11 if ivanti api fails
     */
    protected function curlQuery($info)
    {
        if (! extension_loaded('curl')) {
            throw new Exception('PHP curl extension is missing.', 10);
        }

        $curl = curl_init();

        // Construction de l'URL complète avec le api_path
        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address')
                      . rtrim($this->getFormValue('api_path'), '/') . '/' . ltrim($info['query_endpoint'], '/');

        // Headers par défaut
        $headers = [
            'Authorization: rest_api_key=' . $this->getFormValue('apiKey'),
            'Content-Type: application/json',
        ];

        // Fusionne les headers supplémentaires si $info['headers'] est défini et est un tableau
        if (isset($info['headers']) && is_array($info['headers'])) {
            $headers = array_merge($headers, $info['headers']);
        }

        $peerVerify = ($this->rule_data['peer_verify'] ?? 'yes') === 'yes';
        $verifyHost = $peerVerify ? 2 : 0;
        $caCertPath = $this->rule_data['ca_cert_path'] ?? '';
        $timeout = max(1, (int) $this->getFormValue('timeout', false));

        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $peerVerify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifyHost);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        self::setProxy($curl, [
            'proxy_address' => $this->getFormValue('proxy_address', false),
            'proxy_port' => $this->getFormValue('proxy_port', false),
            'proxy_username' => $this->getFormValue('proxy_username', false),
            'proxy_password' => $this->getFormValue('proxy_password', false),
        ]);

        $optionsToLog = [
            'apiAddress' => $apiAddress,
            'method' => $info['method'],
            'peerVerify' => $peerVerify,
            'verifyHost' => $verifyHost,
            'caCertPath' => '',
        ];

        // Use custom CA only when verification is enabled
        if ($peerVerify && is_string($caCertPath) && $caCertPath !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
            $optionsToLog['caCertPath'] = $caCertPath;
        }

        $this->debug('[open ticket][Ivanti]: request options', [
            'options' => $optionsToLog,
        ]);

        if ($info['method']) {
            curl_setopt($curl, CURLOPT_POST, true);
        }

        if (isset($info['postFields'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $info['postFields']);
        }

        if (isset($info['custom_request'])) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $info['custom_request']);
        }

        $curlResult = curl_exec($curl);

        if ($curlResult === false) {
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            curl_close($curl);
            $this->error('[open ticket][Ivanti]: communication error', ['result' => $curlResult]);

            throw new Exception("Ivanti transport error ({$curlErrNo}): {$curlError}", 11);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            $errorMessage = "Ivanti API error (HTTP {$httpCode}) : {$curlResult}";
            $this->error('[open ticket][Ivanti]: curl query result', ['result' => $curlResult]);

            throw new Exception($errorMessage, 11);
        }

        return json_decode($curlResult, true);
    }

    /*
    * get categories from ivanti
    *
    * @return {array} $this->ivantiCallResult['response'] list of categories
    *
    * throw \Exception if we can't get categories data
    */
    protected function getCategories()
    {
        $info['query_endpoint'] = '/Categorys';
        $info['method'] = 0;
        // try to get itil categories from Ivanti
        try {
            // the variable is going to be used outside of this method.
            $this->ivantiCallResult['response'] = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $this->ivantiCallResult['response'];
    }

    /*
    * get users from ivanti
    *
    * @return {array} $this->ivantiCallResult['response'] list of users
    *
    * throw \Exception if we can't get users data
    */
    protected function getUsers()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Employees';
        $info['method'] = 0;

        // try to get users from Ivanti
        try {
            // the variable is going to be used outside of this method.
            $this->ivantiCallResult['response'] = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $this->ivantiCallResult['response'];
    }

    /**
     * Récupère la liste des équipes depuis l'API Ivanti.
     *
     * @throws Exception si la récupération échoue
     * @return array retourne la liste des équipes
     */
    protected function getTeams()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/standarduserteams';
        $info['method'] = 0;

        // try to get teams from Ivanti
        try {
            // the variable is going to be used outside of this method.
            $this->ivantiCallResult['response'] = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $this->ivantiCallResult['response'];
    }

    /*
    * handle ticket creation in ivanti
    *
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {string} $ticketId ticket id
    *
    * throw \Exception if we can't open a ticket
    */
    protected function createTicket($ticketArguments)
    {
        $endpoint = '/incidents';
        $data = [
            'Symptom' => $ticketArguments[$this->internal_arg_name[self::ARG_SYMPTOM]],
            'Subject' => $ticketArguments[$this->internal_arg_name[self::ARG_TITLE]],
            'Status' => $ticketArguments[$this->internal_arg_name[self::ARG_STATUS]],
        ];

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_SOURCE]]) && $ticketArguments[$this->internal_arg_name[self::ARG_SOURCE]] !== -1) {
            $data['Source'] = $ticketArguments[$this->internal_arg_name[self::ARG_SOURCE]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]]) && $ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]] !== -1) {
            $data['Service'] = $ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]]) && $ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]] !== -1) {
            $data['Category'] = $ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_IMPACT]]) && $ticketArguments[$this->internal_arg_name[self::ARG_IMPACT]] !== -1) {
            $data['Impact'] = $ticketArguments[$this->internal_arg_name[self::ARG_IMPACT]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_URGENCY]]) && $ticketArguments[$this->internal_arg_name[self::ARG_URGENCY]] !== -1) {
            $data['Urgency'] = $ticketArguments[$this->internal_arg_name[self::ARG_URGENCY]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_TEAM]]) && $ticketArguments[$this->internal_arg_name[self::ARG_TEAM]] !== -1) {
            $data['OwnerTeam'] = $ticketArguments[$this->internal_arg_name[self::ARG_TEAM]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_PROFILE_LINK]]) && $ticketArguments[$this->internal_arg_name[self::ARG_PROFILE_LINK]] !== -1) {
            $data['ProfileLink'] = $ticketArguments[$this->internal_arg_name[self::ARG_PROFILE_LINK]];
        }

        if (isset($ticketArguments[$this->internal_arg_name[self::ARG_ALTERNATE_CONTACT_LINK]]) && $ticketArguments[$this->internal_arg_name[self::ARG_ALTERNATE_CONTACT_LINK]] !== -1) {
            $data['AlternateContactLink'] = $ticketArguments[$this->internal_arg_name[self::ARG_ALTERNATE_CONTACT_LINK]];
        }

        foreach ($ticketArguments as $id => $value) {
            // $id is structure is "{$select._cf_customFieldName.value}" we keep "customFieldName"
            if (preg_match('/.*\._cf_(.*)\.(id|value|placeholder).*/', $id, $match)) {
                $data[$match[1]] = $value;
            }
        }

        $info = [
            'query_endpoint' => $endpoint,
            'method' => 1, // POST
            'postFields' => json_encode($data),
        ];

        try {
            $response = $this->curlQuery($info);
            if (isset($response['RecId'])) {
                return $response['RecId'];
            }

            throw new Exception('Unknown Ivanti error: ' . json_encode($response));
        } catch (Exception $e) {
            throw new Exception('Error during ticket creation: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /*
    * close a ticket in Ivanti
    *
    * @params {string} $ticketId the ticket id
    *
    * @return {bool}
    *
    * throw \Exception if it can't close the ticket
    */
    protected function closeTicketIvanti($ticketId)
    {
        $endpoint = '/incidents(' . urlencode($ticketId) . ')';

        $data = [
            'Status' => 'Closed',
        ];

        $info = [
            'query_endpoint' => $endpoint,
            'method' => 0,
            'custom_request' => 'PATCH',
            'postFields' => json_encode($data),
        ];

        try {
            $response = $this->curlQuery($info);

            return true;
        } catch (Exception $e) {
            throw new Exception('Close ticket error: ' . $e->getMessage(), $e->getCode());
        }
    }
}
