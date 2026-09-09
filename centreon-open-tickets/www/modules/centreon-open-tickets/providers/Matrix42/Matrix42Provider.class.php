<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

class Matrix42Provider extends AbstractProvider
{
    use LoggerTrait;

    public const ARG_SUBJECT = 1;
    public const ARG_DESCRIPTION = 2;
    public const ARG_PRIORITY = 3;
    public const ARG_IMPACT = 4;
    public const ARG_URGENCY = 5;
    public const ARG_USER = 6;
    public const ARG_RESPONSIBLE_USER = 7;
    public const ARG_SERVICE = 8;
    public const ARG_ASSET = 9;
    public const ARG_CATEGORY = 10;
    public const ARG_RESPONSIBLE_ROLE = 11;

    public const MATRIX42_USER_TYPE = 20;
    public const MATRIX42_SERVICE_TYPE = 21;
    public const MATRIX42_ASSET_TYPE = 22;
    public const MATRIX42_CATEGORY_TYPE = 23;
    public const MATRIX42_ROLE_TYPE = 24;

    protected $close_advanced = 1;

    protected $proxy_enabled = 1;

    protected $internal_arg_name = [
        self::ARG_SUBJECT => 'subject',
        self::ARG_DESCRIPTION => 'description',
        self::ARG_PRIORITY => 'priority',
        self::ARG_IMPACT => 'impact',
        self::ARG_URGENCY => 'urgency',
        self::ARG_USER => 'user',
        self::ARG_RESPONSIBLE_USER => 'responsible_user',
        self::ARG_SERVICE => 'service',
        self::ARG_ASSET => 'asset',
        self::ARG_CATEGORY => 'category',
        self::ARG_RESPONSIBLE_ROLE => 'responsible_role',
    ];

    // data definition are used to retrieve objects from matrix42
    private $ddNameConfigField = [
        self::MATRIX42_USER_TYPE => 'user_ddname',
        self::MATRIX42_SERVICE_TYPE => 'service_ddname',
        self::MATRIX42_ASSET_TYPE => 'asset_ddname',
        self::MATRIX42_CATEGORY_TYPE => 'category_ddname',
        self::MATRIX42_ROLE_TYPE => 'role_ddname',
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
     * test if we can reach the Matrix42 API with the given configuration
     *
     * @param {array} $info required information to reach the Matrix42 api
     *
     * @return {bool}
     *
     * throw \Exception if there are some missing parameters
     * throw \Exception if the connection failed
     */
    public static function test($info)
    {
        if (
            ! isset($info['address'])
            || ! isset($info['api_path'])
            || ! isset($info['protocol'])
            || ! isset($info['api_token'])
        ) {
            throw new Exception('missing arguments');
        }

        if (! extension_loaded('curl')) {
            throw new Exception("couldn't find php curl", 10);
        }

        $timeout = max(1, (int) ($info['timeout'] ?? 60));
        $peerVerify = ($info['peer_verify'] ?? 'yes') === 'yes';
        $caCertPath = $info['ca_cert_path'] ?? '';

        $curl = curl_init();
        $apiAddress = $info['protocol'] . '://' . $info['address'] . rtrim($info['api_path'], '/')
            . '/ApiToken/GenerateAccessTokenFromApiToken/';

        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $info['api_token'],
            'Content-Type: application/json;charset=UTF-8',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $peerVerify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $peerVerify ? 2 : 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '{}');

        if ($peerVerify && is_string($caCertPath) && $caCertPath !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
        }

        $curlResult = curl_exec($curl);

        if ($curlResult === false) {
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            throw new Exception("Matrix42 transport error ({$curlErrNo}): {$curlError}", 11);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception('curl result: ' . $curlResult . ' || HTTP return code: ' . $httpCode, 11);
        }

        $decoded = json_decode($curlResult, true);
        if (! is_array($decoded) || empty($decoded['RawToken'])) {
            throw new Exception('Matrix42 did not return an access token: ' . $curlResult, 11);
        }

        return true;
    }

    // Set default values for our rule form options
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = 'mycompany.matrix42.cloud';
        $this->default_data['api_path'] = '/m42Services/api';
        $this->default_data['protocol'] = 'https';
        $this->default_data['api_token'] = '';
        $this->default_data['activity_type'] = 0;
        $this->default_data['timeout'] = 60;

        // Hard to find any documentation regarding real data definition name. Putting default values that worked on a platform
        $this->default_data['user_ddname'] = 'SPSUserClassBase';
        $this->default_data['service_ddname'] = 'SPSArticleClassBase';
        $this->default_data['asset_ddname'] = 'SPSComputerClassBase';
        $this->default_data['category_ddname'] = 'SPSScCategoryClassBase';
        $this->default_data['role_ddname'] = 'SPSScRoleClassBase';

        // when enabled, the asset list of the ticket popup is filtered using the host name
        $this->default_data['match_asset_to_host'] = 'no';

        // when enabled, the match_user_by_email.ihtml templates also apply
        // the user they matched to the "Responsible user" list, in addition to the "User" list
        // unlikely to find a match since we match the centreon user email and the user returned by Matrix42 which is probably not the email value
        $this->default_data['sync_user_to_responsible_user'] = 'no';

        $this->default_data['clones']['mappingTicket'] = [
            [
                'Arg' => self::ARG_SUBJECT,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers'
                    . '/Abstract/templates/display_title.ihtml"}',
            ],
            [
                'Arg' => self::ARG_DESCRIPTION,
                'Value' => '{$body}',
            ],
            [
                'Arg' => self::ARG_PRIORITY,
                'Value' => '{$select.priority.value}',
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
                'Arg' => self::ARG_USER,
                'Value' => '{$select.matrix42_user.id}',
            ],
            [
                'Arg' => self::ARG_RESPONSIBLE_USER,
                'Value' => '{$select.matrix42_responsible_user.id}',
            ],
            [
                'Arg' => self::ARG_RESPONSIBLE_ROLE,
                'Value' => '{$select.matrix42_role.id}',
            ],
            [
                'Arg' => self::ARG_SERVICE,
                'Value' => '{$select.matrix42_service.id}',
            ],
            [
                'Arg' => self::ARG_ASSET,
                'Value' => '{$select.matrix42_asset.id}',
            ],
            [
                'Arg' => self::ARG_CATEGORY,
                'Value' => '{$select.matrix42_category.id}',
            ],
        ];
    }

    /*
     * Set default values for the widget popup when opening a ticket
     *
     * @return void
     */
    protected function setDefaultValueMain($body_html = 1)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = '{$protocol}://{$address}/wm/app-ServiceDesk/?view-options={ldelim}%22embedded%22:false,%22objectId%22:%22{$ticket_id}%22,%22viewType%22:%22preview%22{rdelim}';

        // add template to handle the match asset to host name option and 
        $this->default_data['format_popup'] = str_replace(
            '{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}',
            '{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
            {include file="file:$centreon_open_tickets_path/providers/Matrix42/templates/match_asset_to_host.ihtml"}
            {include file="file:$centreon_open_tickets_path/providers/Matrix42/templates/match_user_by_email.ihtml"}',
            $this->default_data['format_popup']
        );

        $this->default_data['clones']['groupList'] = [
            [
                'Id' => 'matrix42_user',
                'Label' => _('User'),
                'Type' => self::MATRIX42_USER_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'matrix42_responsible_user',
                'Label' => _('Responsible user'),
                'Type' => self::MATRIX42_USER_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'matrix42_service',
                'Label' => _('Service'),
                'Type' => self::MATRIX42_SERVICE_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'matrix42_asset',
                'Label' => _('Asset'),
                'Type' => self::MATRIX42_ASSET_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'matrix42_category',
                'Label' => _('Category'),
                'Type' => self::MATRIX42_CATEGORY_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'matrix42_role',
                'Label' => _('Responsible role (group)'),
                'Type' => self::MATRIX42_ROLE_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
            [
                'Id' => 'priority',
                'Label' => _('Priority'),
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
                'Id' => 'urgency',
                'Label' => _('Urgency'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => '',
            ],
        ];

        $this->default_data['clones']['customList'] = [
            ['Id' => 'priority', 'Value' => '1', 'Label' => 'Low', 'Default' => ''],
            ['Id' => 'priority', 'Value' => '2', 'Label' => 'Medium', 'Default' => '1'],
            ['Id' => 'priority', 'Value' => '3', 'Label' => 'High', 'Default' => ''],
            ['Id' => 'priority', 'Value' => '4', 'Label' => 'Critical', 'Default' => ''],
            ['Id' => 'impact', 'Value' => '1', 'Label' => 'Low', 'Default' => ''],
            ['Id' => 'impact', 'Value' => '2', 'Label' => 'Medium', 'Default' => '1'],
            ['Id' => 'impact', 'Value' => '3', 'Label' => 'High', 'Default' => ''],
            ['Id' => 'urgency', 'Value' => '1', 'Label' => 'Low', 'Default' => ''],
            ['Id' => 'urgency', 'Value' => '2', 'Label' => 'Medium', 'Default' => '1'],
            ['Id' => 'urgency', 'Value' => '3', 'Label' => 'High', 'Default' => ''],
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
        $this->checkFormValue('api_token', 'Please set "API token" value');
        $this->checkFormValue('activity_type', 'Please set "SPSActivityTypeIncident ID" value');
        $this->checkFormInteger('activity_type', '"SPSActivityTypeIncident ID" must be an integer');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    // Initiate your html configuration and let Smarty display it in the rule form
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/Matrix42/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', ['Matrix42' => _('Matrix42 configuration')]);
        $tpl->assign('webServiceUrl', './api/internal.php');

        $address_html = '<input size="50" name="address" type="text" value="'
            . $this->getFormValue('address') . '" />';
        $api_path_html = '<input size="50" name="api_path" type="text" value="'
            . $this->getFormValue('api_path') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="'
            . $this->getFormValue('protocol') . '" />';
        $api_token_html = '<input size="50" name="api_token" type="password" value="'
            . $this->getFormValue('api_token') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="'
            . $this->getFormValue('timeout') . '" />';

        $activity_type_html = '<input size="50" name="activity_type" type="text" value="'
            . $this->getFormValue('activity_type') . '" />';

        $user_ddname_html = '<input size="50" name="user_ddname" type="text" value="'
            . $this->getFormValue('user_ddname') . '" />';
        $service_ddname_html = '<input size="50" name="service_ddname" type="text" value="'
            . $this->getFormValue('service_ddname') . '" />';
        $asset_ddname_html = '<input size="50" name="asset_ddname" type="text" value="'
            . $this->getFormValue('asset_ddname') . '" />';
        $category_ddname_html = '<input size="50" name="category_ddname" type="text" value="'
            . $this->getFormValue('category_ddname') . '" />';
        $role_ddname_html = '<input size="50" name="role_ddname" type="text" value="'
            . $this->getFormValue('role_ddname') . '" />';

        $match_asset_to_host_html = '<div class="md-checkbox md-checkbox-inline">'
            . '<input type="checkbox" id="match_asset_to_host" name="match_asset_to_host" value="yes" '
            . ($this->getFormValue('match_asset_to_host') === 'yes' ? 'checked' : '')
            . '/><label class="empty-label" for="match_asset_to_host"></label></div>';

        $sync_user_to_responsible_user_html = '<div class="md-checkbox md-checkbox-inline">'
            . '<input type="checkbox" id="sync_user_to_responsible_user" name="sync_user_to_responsible_user" '
            . 'value="yes" '
            . ($this->getFormValue('sync_user_to_responsible_user') === 'yes' ? 'checked' : '')
            . '/><label class="empty-label" for="sync_user_to_responsible_user"></label></div>';

        $array_form = [
            'address' => ['label' => _('Address') . $this->required_field, 'html' => $address_html],
            'api_path' => ['label' => _('API path') . $this->required_field, 'html' => $api_path_html],
            'protocol' => ['label' => _('Protocol') . $this->required_field, 'html' => $protocol_html],
            'api_token' => ['label' => _('API token') . $this->required_field, 'html' => $api_token_html],
            'activity_type' => [
                'label' => 'SPSActivityTypeIncident ID' . $this->required_field,
                'html' => $activity_type_html,
            ],
            'timeout' => ['label' => _('Timeout'), 'html' => $timeout_html],
            'user_ddname' => [
                'label' => _('User data definition name'),
                'html' => $user_ddname_html,
            ],
            'service_ddname' => [
                'label' => _('Service data definition name'),
                'html' => $service_ddname_html,
            ],
            'asset_ddname' => [
                'label' => _('Asset data definition name'),
                'html' => $asset_ddname_html,
            ],
            'category_ddname' => [
                'label' => _('Category data definition name'),
                'html' => $category_ddname_html,
            ],
            'role_ddname' => [
                'label' => _('Responsible role (group) data definition name'),
                'html' => $role_ddname_html,
            ],
            'match_asset_to_host' => [
                'label' => _('Match asset to host name'),
                'html' => $match_asset_to_host_html,
            ],
            'sync_user_to_responsible_user' => [
                'label' => _('Set responsible user to the matched user'),
                'html' => $sync_user_to_responsible_user_html,
            ],
            'mappingTicketLabel' => ['label' => _('Mapping ticket arguments')],
        ];

        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" '
            . 'name="mappingTicketValue[#index#]" size="20" type="text" />';

        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" '
            . 'name="mappingTicketArg[#index#]" type="select-one">'
            . '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</option>'
            . '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</option>'
            . '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</option>'
            . '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</option>'
            . '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</option>'
            . '<option value="' . self::ARG_USER . '">' . _('User') . '</option>'
            . '<option value="' . self::ARG_RESPONSIBLE_USER . '">' . _('Responsible user') . '</option>'
            . '<option value="' . self::ARG_RESPONSIBLE_ROLE . '">' . _('Responsible role (group)') . '</option>'
            . '<option value="' . self::ARG_SERVICE . '">' . _('Service') . '</option>'
            . '<option value="' . self::ARG_ASSET . '">' . _('Asset') . '</option>'
            . '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</option>'
            . '</select>';

        $array_form['mappingTicket'] = [
            ['label' => _('Argument'), 'html' => $mappingTicketArg_html],
            ['label' => _('Value'), 'html' => $mappingTicketValue_html],
        ];

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
        $this->save_config['simple']['api_token'] = $this->submitted_config['api_token'];
        $this->save_config['simple']['activity_type'] = $this->submitted_config['activity_type'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['simple']['user_ddname'] = $this->submitted_config['user_ddname'];
        $this->save_config['simple']['service_ddname'] = $this->submitted_config['service_ddname'];
        $this->save_config['simple']['asset_ddname'] = $this->submitted_config['asset_ddname'];
        $this->save_config['simple']['category_ddname'] = $this->submitted_config['category_ddname'];
        $this->save_config['simple']['role_ddname'] = $this->submitted_config['role_ddname'];
        $this->save_config['simple']['match_asset_to_host'] = (
            isset($this->submitted_config['match_asset_to_host'])
            && $this->submitted_config['match_asset_to_host'] == 'yes'
        ) ? $this->submitted_config['match_asset_to_host'] : '';
        $this->save_config['simple']['sync_user_to_responsible_user'] = (
            isset($this->submitted_config['sync_user_to_responsible_user'])
            && $this->submitted_config['sync_user_to_responsible_user'] == 'yes'
        ) ? $this->submitted_config['sync_user_to_responsible_user'] : '';

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
        return '<option value="' . self::MATRIX42_USER_TYPE . '">' . _('Matrix42 users') . '</option>'
            . '<option value="' . self::MATRIX42_SERVICE_TYPE . '">' . _('Matrix42 services') . '</option>'
            . '<option value="' . self::MATRIX42_ASSET_TYPE . '">' . _('Matrix42 assets') . '</option>'
            . '<option value="' . self::MATRIX42_CATEGORY_TYPE . '">' . _('Matrix42 categories') . '</option>'
            . '<option value="' . self::MATRIX42_ROLE_TYPE . '">' . _('Matrix42 roles (groups)') . '</option>';
    }

    /*
     * exposes rule options to the popup template (format_popup), which otherwise only has access to
     * $args, $groups, $groups_order and $custom_message
     * (see AbstractProvider::assignFormatPopupTemplate(), templates/match_asset_to_host.ihtml and
     * templates/match_user_by_email.ihtml / templates/match_user_by_alias.ihtml)
     *
     * @param {SmartyBC} $tpl
     * @param {array} $args
     *
     * @return {array} the popup lists, unchanged
     */
    protected function assignFormatPopupTemplate(&$tpl, $args)
    {
        $groups = parent::assignFormatPopupTemplate($tpl, $args);
        $tpl->assign('match_asset_to_host', $this->getFormValue('match_asset_to_host'));
        $tpl->assign('sync_user_to_responsible_user', $this->getFormValue('sync_user_to_responsible_user'));

        return $groups;
    }

    /*
     * configure variables with the data fetched from the Matrix42 API
     *
     * @param {array} $entry ticket argument configuration information
     * @param {array} $groups_order order of the ticket arguments
     * @param {array} $groups store the data gathered from Matrix42
     *
     * @return void
     */
    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if (isset($this->ddNameConfigField[$entry['Type']])) {
            $this->assignMatrix42Fragments($entry, $groups_order, $groups);
        }
    }

    /*
     * generic handler for every dynamic list type: it fetches the list of fragments (id/display name
     * pairs) for the Data Definition configured for this list type and turns it into popup values.
     *
     * @param {array} $entry ticket argument configuration information
     * @param {array} $groups_order order of the ticket arguments
     * @param {array} $groups store the data gathered from Matrix42
     *
     * @return void
     */
    protected function assignMatrix42Fragments($entry, &$groups_order, &$groups)
    {
        $groups[$entry['Id']] = [
            'label' => _($entry['Label'])
                . (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0),
        ];
        $groups_order[] = $entry['Id'];

        $ddname = $this->getFormValue($this->ddNameConfigField[$entry['Type']]);
        $result = [];

        try {
            $fragments = $this->getCache($entry['Id']);
            if (is_null($fragments)) {
                $fragments = $this->getFragments($ddname, $entry['Filter'] ?? '');
                $this->setCache($entry['Id'], $fragments, 8 * 3600);
            }

            foreach ($fragments as $fragment) {
                if (! isset($fragment['ID'])) {
                    continue;
                }
                $result[$fragment['ID']] = $this->to_utf8($fragment['DisplayString'] ?? $fragment['ID']);
            }
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
     * fetch the list of fragments (id / display name) of a Data Definition through the Matrix42
     * "Fragments Data Service" API
     *
     * @param {string} $ddname technical name of the Data Definition to read
     * @param {string} $filter optional A-SQL where expression (see Matrix42 for more info)
     *
     * @return {array} list of ['ID' => ..., 'DisplayString' => ...] entries
     *
     * throw \Exception if the Data Definition name is missing or the Matrix42 API call fails
     */
    protected function getFragments($ddname, $filter = '')
    {
        if (empty($ddname)) {
            throw new Exception('no data definition name configured for this list');
        }

        $endpoint = '/data/fragments/' . rawurlencode($ddname) . '?columns=ID';
        if (! empty($filter)) {
            $endpoint .= '&where=' . rawurlencode($filter);
        }

        $info = [
            'query_endpoint' => $endpoint,
            'method' => 'GET',
        ];

        try {
            $result = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return is_array($result) ? $result : [];
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
        $result = ['ticket_id' => null, 'ticket_error_message' => null, 'ticket_is_ok' => 0, 'ticket_time' => time()];

        $tpl = SmartyBC::createSmartyTemplate($this->centreon_open_tickets_path, 'providers/Abstract/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $this->assignSubmittedValues($tpl);

        $ticketArguments = $extraTicketArguments;
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $resultString = $tpl->fetch('eval.ihtml');
                if ($resultString == '') {
                    $resultString = null;
                }

                $ticketArguments[$this->internal_arg_name[$value['Arg']]] = $resultString;
            }
        }

        try {
            $ticketId = $this->createTicket($ticketArguments);
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();

            return $result;
        }

        $this->saveHistory($db_storage, $result, [
            'contact' => $contact,
            'host_problems' => $host_problems,
            'service_problems' => $service_problems,
            'ticket_value' => $ticketId,
            'subject' => $ticketArguments[$this->internal_arg_name[self::ARG_SUBJECT]],
            'data_type' => self::DATA_TYPE_JSON,
            'data' => json_encode($ticketArguments),
        ]);

        return $result;
    }

    /*
     * handle ticket creation in Matrix42
     *
     * @params {array} $ticketArguments contains all the ticket arguments
     *
     * @return {string} the id of the created ticket
     *
     * throw \Exception if we can't open a ticket, or if Matrix42 does not tell us its id
     */
    protected function createTicket($ticketArguments)
    {
        $data = [
            'Subject' => $ticketArguments[$this->internal_arg_name[self::ARG_SUBJECT]],
        ];

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_DESCRIPTION]])) {
            $data['DescriptionHTML'] = $ticketArguments[$this->internal_arg_name[self::ARG_DESCRIPTION]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_PRIORITY]])) {
            $data['Priority'] = (int) $ticketArguments[$this->internal_arg_name[self::ARG_PRIORITY]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_IMPACT]])) {
            $data['Impact'] = (int) $ticketArguments[$this->internal_arg_name[self::ARG_IMPACT]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_URGENCY]])) {
            $data['Urgency'] = (int) $ticketArguments[$this->internal_arg_name[self::ARG_URGENCY]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_USER]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_USER]] != -1
        ) {
            $data['User'] = $ticketArguments[$this->internal_arg_name[self::ARG_USER]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_USER]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_USER]] != -1
        ) {
            $data['ResponsibleUser'] = $ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_USER]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_ROLE]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_ROLE]] != -1
        ) {
            $data['ResponsibleRole'] = $ticketArguments[$this->internal_arg_name[self::ARG_RESPONSIBLE_ROLE]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]] != -1
        ) {
            $data['Service'] = $ticketArguments[$this->internal_arg_name[self::ARG_SERVICE]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_ASSET]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_ASSET]] != -1
        ) {
            $data['Asset'] = $ticketArguments[$this->internal_arg_name[self::ARG_ASSET]];
        }

        if (
            ! empty($ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]])
            && $ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]] != -1
        ) {
            $data['Category'] = $ticketArguments[$this->internal_arg_name[self::ARG_CATEGORY]];
        }

        $info = [
            'query_endpoint' => '/ticket/Create?activityType=' . rawurlencode($this->getFormValue('activity_type')),
            'method' => 'POST',
            'postFields' => json_encode($data),
            'rawStringResponse' => true,
        ];

        try {
            $ticketId = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception('Error during ticket creation: ' . $e->getMessage(), $e->getCode());
        }

        if (empty($ticketId)) {
            throw new Exception(
                'Ticket was created in Matrix42, but no ticket id could be extracted from the API response'
            );
        }

        return $ticketId;
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
            foreach ($tickets as $ticketId => $v) {
                try {
                    $this->closeTicketMatrix42($ticketId);
                    $tickets[$ticketId]['status'] = 2;
                } catch (Exception $e) {
                    if ($this->doCloseTicketContinueOnError()) {
                        $tickets[$ticketId]['status'] = 2;
                    } else {
                        $tickets[$ticketId]['status'] = -1;
                        $tickets[$ticketId]['msg_error'] = $e->getMessage();
                    }
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }

    /*
     * close a ticket in Matrix42
     *
     * @params {string} $ticketId the ticket id (guid)
     *
     * @return void
     *
     * throw \Exception if it can't close the ticket
     */
    protected function closeTicketMatrix42($ticketId)
    {
        $data = [
            'ObjectIds' => [$ticketId],
            'Comments' => '<p>' . _('Closed from Centreon') . '</p>',
        ];

        $info = [
            'query_endpoint' => '/ticket/close',
            'method' => 'POST',
            'postFields' => json_encode($data),
        ];

        try {
            $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
     * handle every query that we need to do against the Matrix42 API
     *
     * @param {array} $info required information to reach the Matrix42 api.
     *
     * @return {array|string|null} the json decoded body, or the raw (quote-stripped) string body when
     *                              $info['rawStringResponse'] is true
     *
     * throw \Exception 10 if php-curl is not installed
     * throw \Exception 11 if the Matrix42 api call fails
     */
    protected function curlQuery($info)
    {
        if (! extension_loaded('curl')) {
            throw new Exception("couldn't find php curl", 10);
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        $curl = curl_init();
        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address')
            . rtrim($this->getFormValue('api_path'), '/') . '/' . ltrim($info['query_endpoint'], '/');

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json;charset=UTF-8',
        ];

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

        if ($peerVerify && is_string($caCertPath) && $caCertPath !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
        }

        if (($info['method'] ?? 'GET') === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $info['postFields'] ?? '{}');
        }

        $this->debug('[open ticket][Matrix42]: request options', [
            'url' => $apiAddress,
            'method' => $info['method'] ?? 'GET',
        ]);

        $curlResult = curl_exec($curl);

        if ($curlResult === false) {
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            throw new Exception("Matrix42 transport error ({$curlErrNo}): {$curlError}", 11);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        
        // documentation do not specifiy which http code is returned when successful.
        // the ticket creation even indicates a http code 204 with no response which is wrong since ticket id is returned as a string
        if ($httpCode >= 400) {
            throw new Exception('curl result: ' . $curlResult . ' || HTTP return code: ' . $httpCode, 11);
        }

        if (! empty($info['rawStringResponse'])) {
            // ticket id comes back as a quoted JSON string (e.g. '"abcd-1234-..."'): strip the quotes
            return trim($curlResult, '"');
        }

        if ($curlResult === '' || $curlResult === false) {
            return null;
        }

        return json_decode($curlResult, true);
    }

    /*
     * returns a valid access token, generating (and caching) a new one if needed.
     *
     * @return {string} the access token
     *
     * throw \Exception if the access token can't be generated
     */
    protected function getAccessToken()
    {
        $accessToken = $this->getCache('matrix42_access_token');
        if (! is_null($accessToken)) {
            return $accessToken;
        }

        try {
            return $this->generateAccessToken();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
     * exchanges the long-lived Matrix42 API token configured on the rule for a short-lived access
     * token, as required by the Matrix42 "API Token" authentication scheme, and caches it for its
     * lifetime.
     *
     * @return {string} the freshly generated access token
     *
     * throw \Exception if the Matrix42 api call fails, or does not return a usable token
     */
    protected function generateAccessToken()
    {
        $curl = curl_init();
        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address')
            . rtrim($this->getFormValue('api_path'), '/') . '/ApiToken/GenerateAccessTokenFromApiToken/';

        $peerVerify = ($this->rule_data['peer_verify'] ?? 'yes') === 'yes';
        $caCertPath = $this->rule_data['ca_cert_path'] ?? '';
        $timeout = max(1, (int) $this->getFormValue('timeout', false));

        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getFormValue('api_token', false),
            'Content-Type: application/json;charset=UTF-8',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $peerVerify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $peerVerify ? 2 : 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '{}');

        self::setProxy($curl, [
            'proxy_address' => $this->getFormValue('proxy_address', false),
            'proxy_port' => $this->getFormValue('proxy_port', false),
            'proxy_username' => $this->getFormValue('proxy_username', false),
            'proxy_password' => $this->getFormValue('proxy_password', false),
        ]);

        if ($peerVerify && is_string($caCertPath) && $caCertPath !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
        }

        $curlResult = curl_exec($curl);

        if ($curlResult === false) {
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            curl_close($curl);
            $this->error('[open ticket][Matrix42]: communication error while generating an access token');

            throw new Exception("Matrix42 transport error ({$curlErrNo}): {$curlError}", 11);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception(
                'Matrix42 authentication error: HTTP ' . $httpCode . ' - ' . $curlResult,
                11
            );
        }

        $result = json_decode($curlResult, true);
        if (! is_array($result) || empty($result['RawToken'])) {
            throw new Exception('Matrix42 did not return an access token: ' . $curlResult, 11);
        }

        // give ourselves a 60s safety margin before the access token actually expires
        $ttl = max(60, $this->parseLifeTime($result['LifeTime'] ?? '') - 60);
        $this->setCache('matrix42_access_token', $result['RawToken'], $ttl);

        return $result['RawToken'];
    }

    /*
     * Matrix42 returns the access token lifetime as a .Net TimeSpan string (e.g. "01:00:00", or
     * "1.00:00:00" when it spans more than a day). This converts it to a number of seconds.
     *
     * @param {string} $lifeTime the lifetime as returned by the Matrix42 API
     *
     * @return {int} the lifetime in seconds (defaults to 3600 if it could not be parsed)
     */
    private function parseLifeTime($lifeTime)
    {
        if (preg_match('/^(?:(\d+)\.)?(\d{1,2}):(\d{2}):(\d{2})/', (string) $lifeTime, $match)) {
            $days = (int) ($match[1] ?? 0);
            $hours = (int) $match[2];
            $minutes = (int) $match[3];
            $seconds = (int) $match[4];

            return $days * 86400 + $hours * 3600 + $minutes * 60 + $seconds;
        }

        return 3600;
    }
}
