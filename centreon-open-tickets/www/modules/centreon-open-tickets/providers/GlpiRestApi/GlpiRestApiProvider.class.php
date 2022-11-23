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

class GlpiRestApiProvider extends AbstractProvider
{
    protected $close_advanced = 1;
    protected $proxy_enabled = 1;

    public const GLPI_ENTITY_TYPE = 14;
    public const GLPI_GROUP_TYPE = 15;
    public const GLPI_ITIL_CATEGORY_TYPE = 16;
    public const GLPI_USER_TYPE = 17;
    public const GLPI_SUPPLIER_TYPE = 18;
    public const GLPI_REQUESTER_TYPE = 19;

    public const ARG_CONTENT = 1;
    public const ARG_ENTITY = 2;
    public const ARG_URGENCY = 3;
    public const ARG_IMPACT = 4;
    public const ARG_ITIL_CATEGORY = 5;
    public const ARG_USER = 6;
    public const ARG_GROUP = 7;
    public const ARG_TITLE = 8;
    public const ARG_PRIORITY = 9;
    public const ARG_SUPPLIER = 10;
    public const ARG_GROUP_ROLE = 11;
    public const ARG_USER_ROLE = 12;
    public const ARG_REQUESTER = 13;

    private const PAGE_SIZE = 20;

    protected $internal_arg_name = [
        self::ARG_CONTENT => 'content',
        self::ARG_ENTITY => 'entity',
        self::ARG_URGENCY => 'urgency',
        self::ARG_IMPACT => 'impact',
        self::ARG_ITIL_CATEGORY => 'category',
        self::ARG_USER => 'user',
        self::ARG_GROUP => 'group',
        self::ARG_TITLE => 'title',
        self::ARG_PRIORITY => 'priority',
        self::ARG_SUPPLIER => 'supplier',
        self::ARG_GROUP_ROLE => 'group_role',
        self::ARG_USER_ROLE => 'user_role',
        self::ARG_REQUESTER => 'requester'
    ];

    /*
    * Set default values for our rule form options
    *
    * @return {void}
    */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['api_path'] = '/glpi/apirest.php';
        $this->default_data['protocol'] = 'http';
        $this->default_data['user_token'] = '';
        $this->default_data['app_token'] = '';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = [
            [
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ],
            [
                'Arg' => self::ARG_CONTENT,
                'Value' => '{$body}'
            ],
            [
                'Arg' => self::ARG_ENTITY,
                'Value' => '{$select.glpi_entity.id}'
            ],
            [
                'Arg' => self::ARG_ITIL_CATEGORY,
                'Value' => '{$select.glpi_itil_category.id}'
            ],
            [
                'Arg' => self::ARG_REQUESTER,
                'Value' => '{$select.glpi_requester.id}'
            ],
            [
                'Arg' => self::ARG_USER,
                'Value' => '{$select.glpi_users.id}'
            ],
            [
                'Arg' => self::ARG_USER_ROLE,
                'Value' => '{$select.user_role.value}'
            ],
            [
                'Arg' => self::ARG_GROUP,
                'Value' => '{$select.glpi_group.id}'
            ],
            [
                'Arg' => self::ARG_GROUP_ROLE,
                'Value' => '{$select.group_role.value}'
            ],
            [
                'Arg' => self::ARG_URGENCY,
                'Value' => '{$select.urgency.value}'
            ],
            [
                'Arg' => self::ARG_IMPACT,
                'Value' => '{$select.impact.value}'
            ],
            [
                'Arg' => self::ARG_PRIORITY,
                'Value' => '{$select.priority.value}'
            ],
            [
                'Arg' => self::ARG_SUPPLIER,
                'Value' => '{$select.glpi_supplier.id}'
            ]
        ];
    }

    /*
    * Set default values for the widget popup when opening a ticket
    *
    * @return {void}
    */
    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = '{$protocol}://{$address}/glpi/front/ticket.form.php?id={$ticket_id}';

        $this->default_data['clones']['groupList'] = [
            [
                'Id' => 'glpi_entity',
                'Label' => _('Entity'),
                'Type' => self::GLPI_ENTITY_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'glpi_itil_category',
                'Label' => _('Itil category'),
                'Type' => self::GLPI_ITIL_CATEGORY_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'glpi_requester',
                'Label' => _('Requester'),
                'Type' => self::GLPI_REQUESTER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'glpi_users',
                'Label' => _('Glpi users'),
                'Type' => self::GLPI_USER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'user_role',
                'Label' => _('user_role'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'glpi_group',
                'Label' => _('Glpi group'),
                'Type' => self::GLPI_GROUP_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'group_role',
                'Label' => _('group_role'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'urgency',
                'Label' => _('Urgency'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'impact',
                'Label' => _('Impact'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'priority',
                'Label' => _('Priority'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ],
            [
                'Id' => 'glpi_supplier',
                'Label' => _('Glpi supplier'),
                'Type' => self::GLPI_SUPPLIER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ]
        ];

        $this->default_data['clones']['customList'] = [
            [
                'Id' => 'urgency',
                'Value' => '1',
                'Label' => 'Very Low',
                'Default' => ''
            ],
            [
                'Id' => 'urgency',
                'Value' => '2',
                'Label' => 'Low',
                'Default' => ''
            ],
            [
                'Id' => 'urgency',
                'Value' => '3',
                'Label' => 'Medium',
                'Default' => ''
            ],
            [
                'Id' => 'urgency',
                'Value' => '4',
                'Label' => 'High',
                'Default' => ''
            ],
            [
                'Id' => 'urgency',
                'Value' => '5',
                'Label' => 'Very High',
                'Default' => ''
            ],
            [
                'Id' => 'impact',
                'Value' => '1',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'impact',
                'Value' => '2',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'impact',
                'Value' => '3',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'impact',
                'Value' => '4',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'impact',
                'Value' => '5',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '1',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '2',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '3',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '4',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '5',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'priority',
                'Value' => '6',
                'Label' => '',
                'Default' => ''
            ],
            [
                'Id' => 'group_role',
                'Value' => '3',
                'Label' => 'Watcher',
                'Default' => ''
            ],
            [
                'Id' => 'group_role',
                'Value' => '2',
                'Label' => 'Assigned',
                'Default' => ''
            ],
            [
                'Id' => 'user_role',
                'Value' => '3',
                'Label' => 'Watcher',
                'Default' => ''
            ],
            [
                'Id' => 'user_role',
                'Value' => '2',
                'Label' => 'Assigned',
                'Default' => ''
            ]
        ];
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

        $this->checkFormValue('address', 'Please set "Address" value');
        $this->checkFormValue('api_path', 'Please set "API path" value');
        $this->checkFormValue('protocol', 'Please set "Protocol" value');
        $this->checkFormValue('user_token', 'Please set "User token" value');
        $this->checkFormValue('app_token', 'Please set "APP token" value');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    /*
    * Initiate your html configuration and let Smarty display it in the rule form
    *
    * @return {void}
    */
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/GlpiRestApi/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', array('GlpiRestApi' => _("Glpi Rest Api")));
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
        $user_token_html = '<input size="50" name="user_token" type="text" value="' .
            $this->getFormValue('user_token') . '" autocomplete="off" />';
        $app_token_html = '<input size="50" name="app_token" type="text" value="' .
            $this->getFormValue('app_token') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="' .
            $this->getFormValue('timeout') . '" :>';

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
            'user_token' => array(
                'label' => _('User token') . $this->required_field,
                'html' => $user_token_html
            ),
            'app_token' => array(
                'label' => _('APP token') . $this->required_field,
                'html' => $app_token_html
            ),
            'timeout' => array(
                'label' => _('Timeout'),
                'html' => $timeout_html
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
            '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</option>' .
            '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</option>' .
            '<option value="' . self::ARG_ENTITY . '">' . _('Entity') . '</option>' .
            '<option value="' . self::ARG_ITIL_CATEGORY . '">' . _('Category') . '</option>' .
            '<option value="' . self::ARG_REQUESTER . '">' ._('Requester') . '</option>' .
            '<option value="' . self::ARG_USER . '">' . _('User') . '</option>' .
            '<option value="' . self::ARG_USER_ROLE . '">' ._('user_role') . '</option>' .
            '<option value="' . self::ARG_GROUP . '">' . _('Group') . '</option>' .
            '<option value="' . self::ARG_GROUP_ROLE . '">' ._('group_role') . '</option>' .
            '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</option>' .
            '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</option>' .
            '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</option>' .
            '<option value="' . self::ARG_SUPPLIER . '">' . _('Supplier') . '</option>' .
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
    *
    * @return {void}
    */
    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['api_path'] = $this->submitted_config['api_path'];
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['user_token'] = $this->submitted_config['user_token'];
        $this->save_config['simple']['app_token'] = $this->submitted_config['app_token'];
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
        $str = '<option value="' . self::GLPI_ENTITY_TYPE . '">Entity</option>' .
            '<option value="' . self::GLPI_REQUESTER_TYPE . '">Requester</option>' .
            '<option value="' . self::GLPI_GROUP_TYPE . '">Group</option>' .
            '<option value="' . self::GLPI_ITIL_CATEGORY_TYPE . '">ITIL category</option>' .
            '<option value="' . self::GLPI_USER_TYPE . '">User</option>' .
            '<option value="' . self::GLPI_SUPPLIER_TYPE . '">Supplier</option>';

        return $str;
    }

    /*
    * configure variables with the data provided by the glpi api
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    */
    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::GLPI_ENTITY_TYPE) {
            $this->assignGlpiEntities($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_GROUP_TYPE) {
            $this->assignGlpiGroups($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_ITIL_CATEGORY_TYPE) {
            $this->assignItilCategories($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_USER_TYPE) {
            $this->assignGlpiUsers($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_SUPPLIER_TYPE) {
            $this->assignGlpiSuppliers($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_REQUESTER_TYPE) {
            $this->assignGlpiRequesters($entry, $groups_order, $groups);
        }
    }

    /*
    * handle gathered entities
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get entities from glpi
    */
    protected function assignGlpiEntities($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get entities
        try {
            $listEntities = $this->getCache($entry['Id']);
            if (is_null($listEntities)) {
                // if no entity found in cache, get them from glpi and put them in cache for 8 hours
                $listEntities = $this->getEntities();
                $this->setCache($entry['Id'], $listEntities, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }
        $result = array();

        foreach ($listEntities['myentities'] as $entity) {
            // foreach entity found, if we don't have any filter configured,
            // we just put the id and the name of the entity inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$entity['id']] = $this->to_utf8($entity['name']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the entity inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $entity['name'])) {
                $result[$entity['id']] = $this->to_utf8($entity['name']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered requesters
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get requesters from glpi
    */
    protected function assignGlpiRequesters($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get requesters
        try {
            $listRequesters = $this->getCache($entry['Id']);
            if (is_null($listRequesters)) {
                $listRequesters = $this->getUsers();
                $this->setCache($entry['Id'], $listRequesters, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $result = array();

        foreach ($listRequesters as $requester) {
            // foreach requester found, if we don't have any filter configured,
            // we just put the id and the name of the requester inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$requester['id']] = $this->to_utf8($requester['name']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the requester inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $requester['name'])) {
                $result[$requester['id']] = $this->to_utf8($requester['name']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered users
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get users from glpi
    */
    protected function assignGlpiUsers($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get users
        try {
            $listUsers = $this->getCache($entry['Id']);
            if (is_null($listUsers)) {
                $listUsers = $this->getUsers();
                $this->setCache($entry['Id'], $listUsers, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $result = array();

        foreach ($listUsers as $user) {
            // foreach user found, if we don't have any filter configured,
            // we just put the id and the name of the user inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$user['id']] = $this->to_utf8($user['name']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the user inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $user['name'])) {
                $result[$user['id']] = $this->to_utf8($user['name']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered groups
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get groups from glpi
    */
    protected function assignGlpiGroups($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get groups
        try {
            $listGroups = $this->getCache($entry['Id']);
            if (is_null($listGroups)) {
                $listGroups = $this->getGroups();
                $this->setCache($entry['Id'], $listGroups, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $result = array();

        // using $glpiGroup to avoid confusion with $groups and $groups_order
        foreach ($listGroups as $glpiGroup) {
            // foreach group found, if we don't have any filter configured,
            // we just put the id and the name of the group inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$glpiGroup['id']] = $this->to_utf8($glpiGroup['completename']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the group inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $glpiGroup['completename'])) {
                $result[$glpiGroup['id']] = $this->to_utf8($glpiGroup['completename']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered suppliers
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get suppliers from glpi
    */
    protected function assignGlpiSuppliers($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get suppliers
        try {
            $listSuppliers = $this->getCache($entry['Id']);
            if (is_null($listSuppliers)) {
                $listSuppliers = $this->getSuppliers();
                $this->setCache($entry['Id'], $listSuppliers, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $result = array();

        foreach ($listSuppliers as $supplier) {
            // foreach supplier found, if we don't have any filter configured,
            // we just put the id and the name of the supplier inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$supplier['id']] = $this->to_utf8($supplier['name']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the supplier inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $supplier['name'])) {
                $result[$supplier['id']] = $this->to_utf8($supplier['name']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered itil categories
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from glpi
    *
    * @return {void}
    *
    * throw \Exception if we can't get suppliers from glpi
    */
    protected function assignItilCategories($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get suppliers
        try {
            $listCategories = $this->getCache($entry['Id']);
            if (is_null($listCategories)) {
                $listCategories = $this->getItilCategories();
                $this->setCache($entry['Id'], $listCategories, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $result = array();

        foreach ($listCategories as $category) {
            // foreach category found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$category['id']] = $this->to_utf8($category['completename']);
                continue;
            }

            // if we do have have a filter, we make sure that the match the filter, if so, we put the name and the id
            // of the category inside the result array
            if (preg_match('/' . $entry['Filter'] . '/', $category['completename'])) {
                $result[$category['id']] = $this->to_utf8($category['completename']);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
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
    * test if we can reach Glpi webservice with the given Configuration
    *
    * @param {array} $info required information to reach the glpi api
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
            || !isset($info['api_path'])
            || !isset($info['user_token'])
            || !isset($info['app_token'])
            || !isset($info['protocol'])
        ) {
                throw new \Exception('missing arguments', 13);
        }

        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        $curl = curl_init();

        $apiAddress = $info['protocol'] . '://' . $info['address'] . $info['api_path'] . '/initSession';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $info['app_token'],
            'Authorization: user_token ' . $info['user_token'],
            'Content-Type: application/json'
        );

        // initiate our curl options
        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $info['headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, $info['method']);
        curl_setopt($curl, CURLOPT_TIMEOUT, $info['timeout']);
        // execute curl and get status information
        $curlResult = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode > 301) {
            throw new Exception('curl result: ' . $curlResult . '|| HTTP return code: ' . $httpCode, 11);
        }

        return true;
    }

    /*
    * Get a session token from Glpi
    *
    * @return {string} the session token
    *
    * throw \Exception if no api information has been found
    * throw \Exception if the connection failed
    */
    protected function initSession()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/initSession';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Authorization: user_token ' . $this->getFormValue('user_token'),
            'Content-Type: application/json'
        );
        // try to call the rest api
        try {
            $curlResult = $this->curlQuery($info);
            $this->setCache('session_token', $curlResult['session_token'], 8 * 3600);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $curlResult['session_token'];
    }

    /*
    * handle every query that we need to do
    *
    * @param {array} $info required information to reach the glpi api
    * @param int|null $offset pagination offset
    *
    * @return {array} $curlResult the json decoded data gathered from glpi
    *
    * throw \Exception 10 if php-curl is not installed
    * throw \Exception if we can't get a session token
    * throw \Exception 11 if glpi api fails
    */
    protected function curlQuery($info, int $offset = null)
    {
        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        if ($offset !== null && $offset < 0) {
            throw new \InvalidArgumentException('offset must be positive');
        }

        // if we aren't trying to initiate the session, we try to get the session token from the cache
        if ($info['query_endpoint'] != '/initSession') {
            $sessionToken = $this->getCache('session_token');
            // if the token wasn't found in cache we initiate the session to get one and put it in cache
            if (is_null($sessionToken)) {
                try {
                    $sessionToken = $this->initSession();
                    $this->setCache('session_token', $sessionToken, 8 * 3600);
                    array_push($info['headers'], 'Session-Token: ' . $sessionToken);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), $e->getCode());
                }
            } elseif (!preg_grep('/^Session-Token\:/', $info['headers'])) {
                array_push($info['headers'], 'Session-Token: ' . $sessionToken);
            }
        }

        $curl = curl_init();

        $apiAddress = $this->getFormValue('protocol') . '://' . $this->getFormValue('address') .
            $this->getFormValue('api_path') . $info['query_endpoint'];

        if ($offset !== null) {
            $apiAddress .= preg_match('/.+\?/', $apiAddress) ? '&' : '?';
            $apiAddress .= 'range=' . $offset . '-' . ($offset + self::PAGE_SIZE);
        }

        // initiate our curl options
        curl_setopt($curl, CURLOPT_URL, $apiAddress);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $info['headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, $info['method']);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->getFormValue('timeout'));
        // add postData if needed
        if ($info['method']) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $info['postFields']);
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

        // parse headers to manage pagination
        if ($offset !== null) {
            $curlHeaders = [];
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curlResource, $curlHeader) use (&$curlHeaders) {
                $length = strlen($curlHeader);
                $curlHeader = explode(':', $curlHeader, 2);

                if (count($curlHeader) < 2) {
                    return $length;
                }

                $curlHeaders[strtolower(trim($curlHeader[0]))][] = trim($curlHeader[1]);

                return $length;
            });
        }

        // execute curl and get status information
        $curlResult = json_decode(curl_exec($curl), true);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 206 && $offset !== null) {
            // If partial content, get next page
            if (preg_match('/\/(\d+)/', $curlHeaders['content-range'][0], $matches)) {
                $total = $matches[1];

                $offset = $offset + self::PAGE_SIZE;

                if ($offset <= $total) {
                    $curlResult = array_merge_recursive(
                        $curlResult,
                        $this->curlQuery($info, $offset)
                    );
                }
            }
        } elseif ($httpCode == 401 && $curlResult[0] == 'ERROR_SESSION_TOKEN_INVALID') {
            // if http is 401 and message is about token, perhaps the token has expired, so we get a new one
            try {
                $this->initSession();
                $this->curlQuery($info, $offset);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        } elseif ($httpCode >= 400) {
            // for any other issue, we throw an exception
            throw new Exception('ENDPOINT: ' . $apiAddress . ' || GLPI ERROR : ' . $curlResult[0] .
            ' || GLPI MESSAGE: ' . $curlResult[1] . ' || HTTP ERROR: ' . $httpCode, 11);
        }

        return $curlResult;
    }

    /*
    * get entities from glpi
    *
    * @return {array} $this->glpiCallResult['response'] list of entities
    *
    * throw \Exception if we can't get entities data
    */
    protected function getEntities()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/getMyEntities?is_recursive=1';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );
        // try to get entities from Glpi
        try {
            // the variable is going to be used outside of this method.
            $this->glpiCallResult['response'] = $this->curlQuery($info, 0);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->glpiCallResult['response'];
    }

    /*
    * get groups from user ID
    *
    * @return {array} $this->glpiCallResult['response'] list of groups
    *
    * throw \Exception if we can't get groups data
    */
    protected function getUserId()
    {
        // try to get userID
        try {
            $userId = $this->getCache('userId');

            // is there's no userId, we are going to get it from glpi
            if (is_null($userId)) {
                // add the api endpoint and method to our info array
                $info['query_endpoint'] = '/getFullSession';
                $info['method'] = 0;
                // set headers
                $info['headers'] = array(
                    'App-Token: ' . $this->getFormValue('app_token'),
                    'Content-Type: application/json'
                );

                // get user Id from glpi
                $result = $this->curlQuery($info);
                $userId = $result['session']['glpiID'];
                // put user id in cache
                $this->setCache('userId', $userId, 8 * 3600);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $userId;
    }

    /*
    * get groups from glpi
    *
    * @return {array} $this->glpiCallResult['response'] list of groups
    *
    * throw \Exception if we can't get groups data
    */
    protected function getGroups()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/User/' . $this->getUserId() . '/group';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );
        // try to get groups from Glpi
        try {
            // the variable is going to be used outside of this method.
            $this->glpiCallResult['response'] = $this->curlQuery($info, 0);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->glpiCallResult['response'];
    }

    /*
    * get suppliers from glpi
    *
    * @return {array} $this->glpiCallResult['response'] list of suppliers
    *
    * throw \Exception if we can't get suppliers data
    */
    protected function getSuppliers()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Supplier';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );
        // try to get suppliers from Glpi
        try {
            // the variable is going to be used outside of this method.
            $this->glpiCallResult['response'] = $this->curlQuery($info, 0);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->glpiCallResult['response'];
    }

    /*
    * get itil categories from glpi
    *
    * @return {array} $this->glpiCallResult['response'] list of itil categories
    *
    * throw \Exception if we can't get itil categories data
    */
    protected function getItilCategories()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/itilCategory';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );
        // try to get itil categories from Glpi
        try {
            // the variable is going to be used outside of this method.
            $this->glpiCallResult['response'] = $this->curlQuery($info, 0);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->glpiCallResult['response'];
    }

    /*
    * get users from glpi
    *
    * @return {array} $this->glpiCallResult['response'] list of users
    *
    * throw \Exception if we can't get users data
    */
    protected function getUsers()
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/User';
        $info['method'] = 0;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );
        // try to get users from Glpi
        try {
            // the variable is going to be used outside of this method.
            $this->glpiCallResult['response'] = $this->curlQuery($info, 0);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->glpiCallResult['response'];
    }

    /*
    * handle ticket creation in glpi
    *
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {string} $ticketId ticket id
    *
    * throw \Exception if we can't open a ticket
    * throw \Exception if we can't assign a user to the ticket
    * throw \Exception if we can't assign a group to the ticket
    * throw \Exception if we can't assign a supplier to the ticket
    * throw \Exception if we can't assign a requester to the ticket
    */
    protected function createTicket($ticketArguments)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket';
        $info['method'] = 1;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        $fields['input'] = array(
            'name' => $ticketArguments['title'],
            'content' => $ticketArguments['content'],
            'entities_id' => $ticketArguments['entity'],
            'urgency' => $ticketArguments['urgency'],
            'itilcategories_id' => $ticketArguments['category'],
            'impact' => $ticketArguments['impact'],
            'priority' => $ticketArguments['priority']
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
            $ticketId = $this->glpiCallResult['response']['id'];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        // assign ticket to a user
        if (isset($ticketArguments['user']) && $ticketArguments['user'] != -1) {
            try {
                $this->assignUserTicketGlpi($ticketId, $ticketArguments);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        // assign ticket to a group
        if (isset($ticketArguments['group']) && $ticketArguments['group'] != -1) {
            try {
                $this->assignGroupTicketGlpi($ticketId, $ticketArguments);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        // link the ticket to a supplier
        if (isset($ticketArguments['supplier']) && $ticketArguments['supplier'] != -1) {
            try {
                $this->assignSupplierTicketGlpi($ticketId, $ticketArguments);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        // assign ticket to a requester
        if (isset($ticketArguments['requester']) && $ticketArguments['requester'] != -1) {
            try {
                $this->assignRequesterTicketGlpi($ticketId, $ticketArguments);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        return $ticketId;
    }

    /*
    * assign a user to the ticket
    *
    * @params {string} $ticketId id of the tickets
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {void}
    *
    * throw \Exception if we can't assign the ticket to a user
    */
    protected function assignUserTicketGlpi($ticketId, $ticketArguments)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket/' . $ticketId . '/Ticket_User';
        $info['method'] = 1;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        $fields['input'] = array(
            'type' => $ticketArguments['user_role'],
            'users_id' => $ticketArguments['user'],
            'tickets_id' => $ticketId
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
    * assign a group to the ticket
    *
    * @params {string} $ticketId id of the tickets
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {void}
    *
    * throw \Exception if we can't assign the ticket to a group
    */
    protected function assignGroupTicketGlpi($ticketId, $ticketArguments)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket/' . $ticketId . '/group_ticket';
        $info['method'] = 1;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        $fields['input'] = array(
            'type' => $ticketArguments['group_role'],
            'groups_id' => $ticketArguments['group'],
            'tickets_id' => $ticketId
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
    * assign a supplier to the ticket
    *
    * @params {string} $ticketId id of the tickets
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {void}
    *
    * throw \Exception if we can't assign the ticket to a supplier
    */
    protected function assignSupplierTicketGlpi($ticketId, $ticketArguments)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket/' . $ticketId . '/supplier_ticket';
        $info['method'] = 1;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        $fields['input'] = array(
            'type' => 2,
            'suppliers_id' => $ticketArguments['supplier'],
            'tickets_id' => $ticketId
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
    * assign a requester to the ticket
    *
    * @params {string} $ticketId id of the tickets
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {void}
    *
    * throw \Exception if we can't assign the ticket to a requester
    */
    protected function assignRequesterTicketGlpi($ticketId, $ticketArguments)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket/' . $ticketId . '/Ticket_User';
        $info['method'] = 1;
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        $fields['input'] = array(
            'type' => 1,
            'users_id' => $ticketArguments['requester'],
            'tickets_id' => $ticketId
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /*
    * close a ticket in Glpi
    *
    * @params {string} $ticketId the ticket id
    *
    * @return {bool}
    *
    * throw \Exception if it can't close the ticket
    */
    protected function closeTicketGlpi($ticketId)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/Ticket/' . $ticketId;
        $info['method'] = 1;
        $info['custom_request'] = 'PUT';
        // set headers
        $info['headers'] = array(
            'App-Token: ' . $this->getFormValue('app_token'),
            'Content-Type: application/json'
        );

        // status 6 = closed ticket
        $fields['input'] = array(
            'status' => 6
        );

        $info['postFields'] = json_encode($fields);

        try {
            $this->glpiCallResult['response'] = $this->curlQuery($info);
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
                    $this->closeTicketGlpi($k);
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
