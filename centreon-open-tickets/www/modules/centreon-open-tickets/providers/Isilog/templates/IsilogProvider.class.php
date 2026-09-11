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

class IsilogProvider extends AbstractProvider
{
    protected $close_advanced = 1;
    protected $proxy_enabled = 1;

    // we are going to use the same result set from one webservice of isilog,
    // if it's true, it means that we already got the result set, so we don't need to reach the webservice again
    protected $get_others = false;

    protected const ISILOG_CATEGORY_TYPE = 20;
    protected const ISILOG_SERVICE_TYPE = 21;
    protected const ISILOG_IMPACT_TYPE = 22;
    protected const ISILOG_URGENCY_TYPE = 23;
    protected const ISILOG_QUALIFIER_TYPE = 24;
    protected const ISILOG_ORIGIN_TYPE = 25;
    protected const ISILOG_TEAM_TYPE = 26;
    protected const ISILOG_USER_TYPE = 27;
    protected const ISILOG_OU_TYPE = 28;
    protected const ISILOG_SITE_TYPE = 29;
    protected const ISILOG_CUSTOMER_TYPE = 30;

    protected const ARG_CONTENT = 1;
    protected const ARG_TITLE = 2;
    protected const ARG_CATEGORY = 3;
    protected const ARG_SERVICE = 4;
    protected const ARG_IMPACT = 5;
    protected const ARG_URGENCY = 6;
    protected const ARG_QUALIFIER = 7;
    protected const ARG_ORIGIN = 8;
    protected const ARG_TEAM = 9;
    protected const ARG_USER = 10;
    protected const ARG_OU = 11;
    protected const ARG_SITE = 12;
    protected const ARG_CUSTOMER = 13;

    protected $internal_arg_name = array(
        self::ARG_CONTENT => 'content',
        self::ARG_TITLE => 'title',
        self::ARG_CATEGORY => 'category',
        self::ARG_SERVICE => 'service',
        self::ARG_IMPACT => 'impact',
        self::ARG_URGENCY => 'urgency',
        self::ARG_QUALIFIER => 'qualifier',
        self::ARG_ORIGIN => 'origin',
        self::ARG_TEAM => 'team',
        self::ARG_USER => 'user',
        self::ARG_OU => 'OU',
        self::ARG_SITE => 'site',
        self::ARG_CUSTOMER => 'customer'
    );

    /*
    * Set default values for our rule form options
    *
    * @return {void}
    */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['protocol'] = 'http';
        $this->default_data['check_certificate'] = 'yes';
        $this->default_data['user'] = '';
        $this->default_data['password'] = '';
        $this->default_data['database'] = '';
        $this->default_data['timeout'] = 60;
        $this->default_data['centreoncat'] = 'CENTREONCAT';
        $this->default_data['centreonservice'] = 'CENTREONSERVICE';
        $this->default_data['centreonteam'] = 'CENTREONTEAM';
        $this->default_data['centreonsite'] = 'CENTREONSITE';
        $this->default_data['centreonou'] = 'CENTREONOU';
        $this->default_data['centreonuser'] = 'CENTREONUSER';
        $this->default_data['centreonimpact'] = 'CENTREONIMPACT';
        $this->default_data['centreonurgency'] = 'CENTREONURGENCY';
        $this->default_data['centreonothers'] = 'CENTREONOTHERS';

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array(
                'Arg' => self::ARG_CONTENT,
                'Value' => '{$body}'
            ),
            array(
                'Arg' => self::ARG_CATEGORY,
                'Value' => '{$select.isilog_category.id}'
            ),
            array(
                'Arg' => self::ARG_SERVICE,
                'Value' => '{$select.isilog_service.id}'
            ),
            array(
                'Arg' => self::ARG_IMPACT,
                'Value' => '{$select.isilog_impact.id}'
            ),
            array(
                'Arg' => self::ARG_URGENCY,
                'Value' => '{$select.isilog_urgency.id}'
            ),
            array(
                'Arg' => self::ARG_QUALIFIER,
                'Value' => '{$select.isilog_qualifier.id}'
            ),
            array(
                'Arg' => self::ARG_ORIGIN,
                'Value' => '{$select.isilog_origin.id}'
            ),
            array(
                'Arg' => self::ARG_TEAM,
                'Value' => '{$select.isilog_team.id}'
            ),
            array(
                'Arg' => self::ARG_USER,
                'Value' => '{$select.isilog_user.id}'
            ),
            array(
                'Arg' => self::ARG_OU,
                'Value' => '{$select.isilog_ou.id}'
            ),
            array(
                'Arg' => self::ARG_SITE,
                'Value' => '{$select.isilog_site.id}'
            ),
            array(
                'Arg' => self::ARG_CUSTOMER,
                'Value' => '{$select.isilog_customer.id}'
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
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = '{$protocol}://{$address}/';

        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'isilog_category',
                'Label' => _('Category'),
                'Type' => self::ISILOG_CATEGORY_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_service',
                'Label' => _('Service'),
                'Type' => self::ISILOG_SERVICE_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_impact',
                'Label' => _('Impact'),
                'Type' => self::ISILOG_IMPACT_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_urgency',
                'Label' => _('Urgency'),
                'Type' => self::ISILOG_URGENCY_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_qualifier',
                'Label' => _('Qualifier'),
                'Type' => self::ISILOG_QUALIFIER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_origin',
                'Label' => _('Origin'),
                'Type' => self::ISILOG_ORIGIN_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_team',
                'Label' => _('Team'),
                'Type' => self::ISILOG_TEAM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_user',
                'Label' => _('User'),
                'Type' => self::ISILOG_USER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_ou',
                'Label' => _('OU'),
                'Type' => self::ISILOG_OU_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_site',
                'Label' => _('Site'),
                'Type' => self::ISILOG_SITE_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'isilog_customer',
                'Label' => _('Customer'),
                'Type' => self::ISILOG_CUSTOMER_TYPE,
                'Filter' => '',
                'Mandatory' => ''
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

        $this->checkFormValue('address', 'Please set "Address" value');
        $this->checkFormValue('database', 'Please set "Database name" value');
        $this->checkFormValue('protocol', 'Please set "Protocol" value');
        $this->checkFormValue('username', 'Please set "Username" value');
        $this->checkFormValue('password', 'Please set "Password" value');
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
        // initiate smarty and a few variables.
        $tpl = $this->initSmartyTemplate('providers/Isilog/templates');
        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("isilog" => _("Isilog")));
        $tpl->assign('webServiceUrl', './api/internal.php');

        /*
        * we create the html that is going to be displayed
        */
        $address_html = '<input size="50" name="address" type="text" value="'
            . $this->getFormValue('address') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="'
            . $this->getFormValue('username') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="'
            . $this->getFormValue('protocol') . '" />';
        $checkCertificateHtml = '<input size="50" id="check_certificate" '
            . 'name="check_certificate" type="checkbox" value="yes" '
            . ($this->getFormValue('check_certificate') === 'yes' ? 'checked' : '') . '/>'
            . '<label class="empty-label" for="check_certificate"></label>';
        $password_html = '<input size="50" name="password" type="password" value="'
            . $this->getFormValue('password') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="'
            . $this->getFormValue('timeout') . '" :>';
        $database_html = '<input size="50" name="database" type="text" value="'
            . $this->getFormValue('database') . '" />';
        $centreoncat_html = '<input size="50" class="centreoncat" name="centreoncat" type="text" value="'
            . $this->getFormValue('centreoncat') . '" />';
        $centreonservice_html = '<input size="50" class="centreonservice" name="centreonservice" type="text" value="'
            . $this->getFormValue('centreonservice') . '" />';
        $centreonteam_html = '<input size="50" class="centreonteam" name="centreonteam" type="text" value="'
            . $this->getFormValue('centreonteam') . '" />';
        $centreonsite_html = '<input size="50" class="centreonsite" name="centreonsite" type="text" value="'
            . $this->getFormValue('centreonsite') . '" />';
        $centreonou_html = '<input size="50" class="centreonou" name="centreonou" type="text" value="'
            . $this->getFormValue('centreonou') . '" />';
        $centreonuser_html = '<input size="50" class="centreonuser" name="centreonuser" type="text" value="'
            . $this->getFormValue('centreonuser') . '" />';
        $centreonimpact_html = '<input size="50" class="centreonimpact" name="centreonimpact" type="text" value="'
            . $this->getFormValue('centreonimpact') . '" />';
        $centreonurgency_html = '<input size="50" class="centreonurgency" name="centreonurgency" type="text" value="'
            . $this->getFormValue('centreonurgency') . '" />';
        $centreonothers_html = '<input size="50" class="centreonothers" name="centreonothers" type="text" value="'
            . $this->getFormValue('centreonothers') . '" />';

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
            'protocol' => array(
                'label' => _('Protocol') . $this->required_field,
                'html' => $protocol_html
            ),
            'check_certificate' => array(
                'label' => _('Check SSL certificates'),
                'html' => $checkCertificateHtml
            ),
            'password' => array(
                'label' => _('Password') . $this->required_field,
                'html' => $password_html
            ),
            'database' => array(
                'label' => _('Database name') . $this->required_field,
                'html' => $database_html
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

        $arrayWebservices = array (
            'centreoncat' => array(
                'label' => _('webservice name for categories'),
                'html' => $centreoncat_html,
                'value' => $this->rule_data['centreoncat']
            ),
            'centreonservice' => array(
                'label' => _('webservice name for services'),
                'html' => $centreonservice_html,
                'value' => $this->rule_data['centreonservice']
            ),
            'centreonteam' => array(
                'label' => _('webservice name for teams'),
                'html' => $centreonteam_html,
                'value' => $this->rule_data['centreonteam']
            ),
            'centreonsite' => array(
                'label' => _('webservice name for sites'),
                'html' => $centreonsite_html,
                'value' => $this->rule_data['centreonsite']
            ),
            'centreonou' => array(
                'label' => _('webservice name for organizational units'),
                'html' => $centreonou_html,
                'value' => $this->rule_data['centreonou']
            ),
            'centreonuser' => array(
                'label' => _('webservice name for users'),
                'html' => $centreonuser_html,
                'value' => $this->rule_data['centreonuser']
            ),
            'centreonimpact' => array(
                'label' => _('webservice name for impact'),
                'html' => $centreonimpact_html,
                'value' => $this->rule_data['centreonimpact']
            ),
            'centreonurgency' => array(
                'label' => _('webservice name for urgencies'),
                'html' => $centreonurgency_html,
                'value' => $this->rule_data['centreonurgency']
            ),
            'centreonothers' => array(
                'label' => _('webservice name for miscellanous ticket options'),
                'html' => $centreonothers_html,
                'value' => $this->rule_data['centreonothers']
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
            '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</option>' .
            '<option value="' . self::ARG_SERVICE . '">' . _('Service') . '</option>' .
            '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</option>' .
            '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</option>' .
            '<option value="' . self::ARG_QUALIFIER . '">' . _('Qualifier') . '</option>' .
            '<option value="' . self::ARG_ORIGIN . '">' . _('Origin') . '</option>' .
            '<option value="' . self::ARG_TEAM . '">' . _('Team') . '</option>' .
            '<option value="' . self::ARG_USER . '">' . _('User') . '</option>' .
            '<option value="' . self::ARG_OU . '">' . _('OU') . '</option>' .
            '<option value="' . self::ARG_SITE . '">' . _('Site') . '</option>' .
            '<option value="' . self::ARG_CUSTOMER . '">' . _('Customer') . '</option>' .
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
        $tpl->assign('arrayWebservices', $arrayWebservices);
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
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['check_certificate'] = (
            isset($this->submitted_config['check_certificate']) && $this->submitted_config['check_certificate'] == 'yes'
        ) ? 'yes' : '';
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['database'] = $this->submitted_config['database'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['simple']['centreoncat'] = $this->submitted_config['centreoncat'];
        $this->save_config['simple']['centreonservice'] = $this->submitted_config['centreonservice'];
        $this->save_config['simple']['centreonteam'] = $this->submitted_config['centreonteam'];
        $this->save_config['simple']['centreonsite'] = $this->submitted_config['centreonsite'];
        $this->save_config['simple']['centreonou'] = $this->submitted_config['centreonou'];
        $this->save_config['simple']['centreonuser'] = $this->submitted_config['centreonuser'];
        $this->save_config['simple']['centreonimpact'] = $this->submitted_config['centreonimpact'];
        $this->save_config['simple']['centreonurgency'] = $this->submitted_config['centreonurgency'];
        $this->save_config['simple']['centreonothers'] = $this->submitted_config['centreonothers'];

        // saves the ticket arguments
        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
    }

    /*
    * Adds new types to the list of types
    *
    * @return {string} $str html code that add an option to a select
    */
    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::ISILOG_CATEGORY_TYPE . '">Category</option>' .
            '<option value="' . self::ISILOG_SERVICE_TYPE . '">Service</option>' .
            '<option value="' . self::ISILOG_IMPACT_TYPE . '">Impact</option>' .
            '<option value="' . self::ISILOG_URGENCY_TYPE . '">Urgency</option>' .
            '<option value="' . self::ISILOG_QUALIFIER_TYPE . '">Qualifier</option>' .
            '<option value="' . self::ISILOG_ORIGIN_TYPE . '">Origin</option>' .
            '<option value="' . self::ISILOG_TEAM_TYPE . '">Team</option>' .
            '<option value="' . self::ISILOG_USER_TYPE . '">User</option>' .
            '<option value="' . self::ISILOG_OU_TYPE . '">OU</option>' .
            '<option value="' . self::ISILOG_SITE_TYPE . '">Site</option>' .
            '<option value="' . self::ISILOG_CUSTOMER_TYPE . '">Site</option>';

        return $str;
    }

    /*
    * configure variables with the data provided by the isilog api
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    */
    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::ISILOG_CATEGORY_TYPE) {
            $this->assignIsilogCategory($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_SERVICE_TYPE) {
            $this->assignIsilogService($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_IMPACT_TYPE) {
            $this->assignIsilogImpact($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_URGENCY_TYPE) {
            $this->assignIsilogUrgency($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_QUALIFIER_TYPE) {
            $this->assignIsilogQualifier($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_ORIGIN_TYPE) {
            $this->assignIsilogOrigin($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_TEAM_TYPE) {
            $this->assignIsilogTeam($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_USER_TYPE) {
            $this->assignIsilogUser($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_OU_TYPE) {
            $this->assignIsilogOU($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_SITE_TYPE) {
            $this->assignIsilogSite($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::ISILOG_CUSTOMER_TYPE) {
            $this->assignIsilogUser($entry, $groups_order, $groups);
        }
    }

    /*
    * handle gathered categories
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get categories from isilog
    */
    protected function assignIsilogCategory($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get categories
        try {
            $listCategories = $this->getCache($entry['Id']);
            if (is_null($listCategories)) {
                $listCategories = $this->getCategories();
                $this->setCache($entry['Id'], $listCategories, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listCategories = simplexml_load_string($listCategories);
        $result = array();
        $xmlResults = $listCategories->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_FULLNAMETYPEPB') {
                    $categoryName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_TYPEPB') {
                    $categoryId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach category found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$categoryId] = $this->to_utf8($categoryName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $categoryName)) {
                $result[$categoryId] = $this->to_utf8($categoryName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered services
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get services from isilog
    */
    protected function assignIsilogService($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get categories
        try {
            $listServices = $this->getCache($entry['Id']);
            if (is_null($listServices)) {
                $listServices = $this->getServices();
                $this->setCache($entry['Id'], $listServices, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listServices = simplexml_load_string($listServices);
        $result = array();

        $xmlResults = $listServices->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_REFERENCECOMPLET') {
                    $serviceName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_OBJET') {
                    $serviceId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach category found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$serviceId] = $this->to_utf8($serviceName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $serviceName)) {
                $result[$serviceId] = $this->to_utf8($serviceName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered teams
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get teams from isilog
    */
    protected function assignIsilogTeam($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get teams
        try {
            $listTeams = $this->getCache($entry['Id']);
            if (is_null($listTeams)) {
                $listTeams = $this->getTeam();
                $this->setCache($entry['Id'], $listTeams, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listTeams = simplexml_load_string($listTeams);
        $result = array();

        $xmlResults = $listTeams->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_EQUIPE') {
                    $teamName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_EQUIPE') {
                    $teamId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach category found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$teamId] = $this->to_utf8($teamName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $teamName)) {
                $result[$teamId] = $this->to_utf8($teamName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered OU
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get OU from isilog
    */
    protected function assignIsilogOU($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get OU
        try {
            $listOU = $this->getCache($entry['Id']);
            if (is_null($listOU)) {
                $listOU = $this->getOU();
                $this->setCache($entry['Id'], $listOU, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listOU = simplexml_load_string($listOU);
        $result = array();

        $xmlResults = $listOU->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_FULLNAMESERVICE') {
                    $ouName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_SERVICE') {
                    $ouId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach category found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$ouId] = $this->to_utf8($ouName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $ouName)) {
                $result[$ouId] = $this->to_utf8($ouName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered sites
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get sites from isilog
    */
    protected function assignIsilogSite($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get sites
        try {
            $listSites = $this->getCache($entry['Id']);
            if (is_null($listSites)) {
                $listSites = $this->getSite();
                $this->setCache($entry['Id'], $listSites, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listSites = simplexml_load_string($listSites);
        $result = array();

        $xmlResults = $listSites->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_FULLNAMESITE') {
                    $siteName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_SITE') {
                    $siteId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach site found, if we don't have any filter configured,
            // we just put the id and the name of the site inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$siteId] = $this->to_utf8($siteName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $siteName)) {
                $result[$siteId] = $this->to_utf8($siteName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered users
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get users from isilog
    */
    protected function assignIsilogUser($entry, &$groups_order, &$groups)
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
                $listUsers = $this->getUser();
                $this->setCache($entry['Id'], $listUsers, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listUsers = simplexml_load_string($listUsers);
        $result = array();

        $xmlResults = $listUsers->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'N_UTIL') {
                    $lastName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_UTIL') {
                    $userId = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'PRE_UTIL') {
                    $firstName = $field->IsiValue[0]->__toString();
                }
            }

            // foreach user found, if we don't have any filter configured,
            // we just put the id and the name of the category inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$userId] = $this->to_utf8($lastName . '.' . $firstName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $lastName . '.' . $firstName)) {
                $result[$userId] = $this->to_utf8($lastName . '.' . $firstName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered impact
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get impact from isilog
    */
    protected function assignIsilogImpact($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get impacts
        try {
            $listImpacts = $this->getCache($entry['Id']);
            if (is_null($listImpacts)) {
                $listImpacts = $this->getImpact();
                $this->setCache($entry['Id'], $listImpacts, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listImpacts = simplexml_load_string($listImpacts);
        $result = array();

        $xmlResults = $listImpacts->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_SEVERITE') {
                    $impactName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_SEVERITE') {
                    $impactId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach impact found, if we don't have any filter configured,
            // we just put the id and the name of the impact inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$impactId] = $this->to_utf8($impactName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $impactName)) {
                $result[$impactId] = $this->to_utf8($impactName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered urgency
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get urgencies from isilog
    */
    protected function assignIsilogUrgency($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get categories
        try {
            $listUrgencies = $this->getCache($entry['Id']);
            if (is_null($listUrgencies)) {
                $listUrgencies = $this->getUrgency();
                $this->setCache($entry['Id'], $listUrgencies, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listUrgencies = simplexml_load_string($listUrgencies);
        $result = array();

        $xmlResults = $listUrgencies->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;
        foreach ($xmlResults as $xmlResult) {
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_BLOCAGE') {
                    $urgencyName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_BLOCAGE') {
                    $urgencyId = $field->IsiValue[0]->__toString();
                }
            }

            // foreach urgency found, if we don't have any filter configured,
            // we just put the id and the name of the urgency inside the result array
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$urgencyId] = $this->to_utf8($urgencyName);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $urgencyName)) {
                $result[$urgencyId] = $this->to_utf8($urgencyName);
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered qualifiers
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get qualifier from isilog
    */
    protected function assignIsilogQualifier($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get qualifiers
        try {
            $listQualifiers = $this->getCache($entry['Id']);
            if (is_null($listQualifiers)) {
                if (!$this->get_others) {
                    $listQualifiers = $this->getOthers();
                } else {
                    $listQualifiers = $this->getOthersResult;
                }
                $this->setCache($entry['Id'], $listQualifiers, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listQualifiers = simplexml_load_string($listQualifiers);
        $result = array();

        $xmlResults = $listQualifiers->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            $isQualifier = false;
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_LOV_VALUE') {
                    $qualifierName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_LOV_VALUE') {
                    $qualifierId = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_LOV' && $field->IsiValue[0] == 'QUALIF_INC') {
                    $isQualifier = true;
                }
            }

            if ($isQualifier) {
                // foreach qualifeir found, if we don't have any filter configured,
                // we just put the id and the name of the qualifier inside the result array
                if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                    $result[$qualifierId] = $this->to_utf8($qualifierName);
                    continue;
                }

                // if we do have have a filter, we make sure that the qualifier match the filter, if so,
                // we put the name and the id of the qualifier inside the result array
                if (preg_match('/' . $entry['Filter'] . '/', $qualifierName)) {
                    $result[$qualifierId] = $this->to_utf8($qualifierName);
                }
            }
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    /*
    * handle gathered origins
    *
    * @param {array} $entry ticket argument configuration information
    * @param {array} $groups_order order of the ticket arguments
    * @param {array} $groups store the data gathered from isilog
    *
    * @return {void}
    *
    * throw \Exception if we can't get origins from isilog
    */
    protected function assignIsilogOrigin($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : '' ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get origins
        try {
            $listOrigins = $this->getCache($entry['Id']);
            if (is_null($listOrigins)) {
                if (!$this->get_others) {
                    $listOrigins = $this->getOthers();
                } else {
                    $listOrigins = $this->getOthersResult;
                }
                $this->setCache($entry['Id'], $listOrigins, 8 * 3600);
            }
        } catch (\Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }

        $listOrigins = simplexml_load_string($listOrigins);
        $result = array();

        $xmlResults = $listOrigins->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult
            ->Objects->children('b', true)->anyType->children()->IsiWsEntity;

        foreach ($xmlResults as $xmlResult) {
            $isOrigin = false;
            foreach ($xmlResult->IsiFields->IsiWsDataField as $field) {
                if ($field->IsiField[0] == 'L_LOV_VALUE') {
                    $originName = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_LOV_VALUE') {
                    $originId = $field->IsiValue[0]->__toString();
                } elseif ($field->IsiField[0] == 'C_LOV' && $field->IsiValue[0] == 'ORIGINE_INC') {
                    $isOrigin = true;
                }
            }

            if ($isOrigin) {
                // foreach origin found, if we don't have any filter configured,
                // we just put the id and the name of the origin inside the result array
                if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                    $result[$originId] = $this->to_utf8($originName);
                    continue;
                }

                if (preg_match('/' . $entry['Filter'] . '/', $originName)) {
                    $result[$originId] = $this->to_utf8($originName);
                }
            }
        }

        $groups[$entry['Id']]['values'] = $result;
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
    * test if we can reach Isilog webservices with the given Configuration
    *
    * @param {array} $info required information to reach the isilog api
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
            || !isset($info['username'])
            || !isset($info['password'])
            || !isset($info['database'])
            || !isset($info['webservice'])
            || !isset($info['protocol'])
        ) {
            throw new \Exception('missing parameters', 13);
        }

        // check if php curl is installed
        if (!extension_loaded("curl")) {
            throw new \Exception("couldn't find php curl", 10);
        }

        $checkCertificate = (isset($info['check_certificate']) && $info['check_certificate'] == 1) ? true : false;

        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapHeader = '<isil:IsiWsAuthHeader>
            <isil:IsiCallProgram>' . $info['webservice'] . '</isil:IsiCallProgram>
            <isil:IsiDataBaseID>' . $info['database'] . '</isil:IsiDataBaseID>
            <isil:IsiLogin>' . $info['username'] . '</isil:IsiLogin>
            <isil:IsiPassword>' . $info['password'] . '</isil:IsiPassword>
            </isil:IsiWsAuthHeader>';

        $soapInfo['envelope'] = '<soap:Envelope xmlns:s="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:soap="http://www.w3.org/2003/05/soap-envelope" '
            . 'xmlns:wsa="http://wwww.w3.org/2005/08/addressing" '
            . 'xmlns:isil="http://isilog.fr/" >'
            . '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">' . $soapHeader
            . '<wsa:Action>http://isilog.fr/IIsiQueryService/IsiGetQueryResult</wsa:Action>'
            . '<wsa:To>' . $info['protocol'] . '//' . $info['address'] . '/IsilogWebSystem/WebServices/IsiQueryService.svc</wsa:To>'
            . '</soap:Header>'
            . '<soap:Body>'
            . '<isil:IsiGetQueryResult>'
            . '<isil:queryId>' . $info['webservice'] . '</isil:queryId>'
            . '<isil:limit>1</isil:limit>'
            . '<isil:totalRecordCount>1</isil:totalRecordCount>'
            . '<isil:modifiedCriteria>'
            . '</isil:modifiedCriteria>'
            . '</isil:IsiGetQueryResult>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $curlEndpoint = $info['protocol'] . '://' . $info['address'] . '/' . $soapInfo['webservice'];

        $curlHeader = array(
            'Content-Type: ' . $soapInfo['content-type'],
            'SOAPAction: ' . $soapInfo['action'],
            'Content-Length: ' . strlen($soapInfo['envelope'])
        );

        // initiate our curl options
        $curl = curl_init($curlEndpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeader);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $checkCertificate);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_TIMEOUT, $info['timeout']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soapInfo['envelope']);

        // if proxy is set, we add it to curl
        if ($info['proxy_address'] != '' && $info['proxy_port'] != '') {
            curl_setopt($curl, CURLOPT_PROXY, $info['proxy_address'] . ':' . $info['proxy_port']);
            // if proxy authentication configuration is set, we add it to curl
            if ($info['proxy_username'] != '' && $info['proxy_password'] != '') {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $info['proxy_username'] . ':' . $info['proxy_password']);
            }
        }

        $curlResult = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($httpCode > 301) {
            throw new Exception('curl result: ' . $curlResult . '|| HTTP return code: ' . $httpCode, 11);
        }

        try {
            $isilogCallResult = simplexml_load_string($curlResult);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 14);
        }

        $statut = $isilogCallResult->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult->Statut;

        if ($statut != 'responseOk') {
            throw new \Exception($statut, 13);
        }
        return true;
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
    protected function doSubmit(
        $db_storage,
        $contact,
        $host_problems,
        $service_problems,
        $extraTicketArguments = array()
    ) {
        // initiate a result array
        $result = array(
            'ticket_id' => null,
            'ticket_error_message' => null,
            'ticket_is_ok' => 0,
            'ticket_time' => time()
        );

        // initiate smarty variables
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
                    $resultString = null;
                }

                $ticketArguments[$this->_internal_arg_name[$value['Arg']]] = $resultString;
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
    * get categories from isilog
    *
    * @return {array} $this->isilogCallResult list of categories
    *
    * throw \Exception if we can't get categories data
    */
    protected function getCategories()
    {
        $webserviceName = $this->rule_data['centreoncat'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get services from isilog
    *
    * @return {array} $this->isilogCallResult list of services
    *
    * throw \Exception if we can't get services data
    */
    protected function getServices()
    {
        $webserviceName = $this->rule_data['centreonservice'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get teams from isilog
    *
    * @return {array} $this->isilogCallResult list of teams
    *
    * throw \Exception if we can't get teams data
    */
    protected function getTeam()
    {
        $webserviceName = $this->rule_data['centreonteam'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get sites from isilog
    *
    * @return {array} $this->isilogCallResult list of sites
    *
    * throw \Exception if we can't get sites data
    */
    protected function getSite()
    {
        $webserviceName = $this->rule_data['centreonsite'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get OU from isilog
    *
    * @return {array} $this->isilogCallResult list of OU
    *
    * throw \Exception if we can't get OU data
    */
    protected function getOU()
    {
        $webserviceName = $this->rule_data['centreonou'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get users from isilog
    *
    * @return {array} $this->isilogCallResult['response'] list of users
    *
    * throw \Exception if we can't get users data
    */
    protected function getUser()
    {
        $webserviceName = $this->rule_data['centreonuser'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get impacts from isilog
    *
    * @return {array} $this->isilogCallResult['response'] list of impacts
    *
    * throw \Exception if we can't get impacts data
    */
    protected function getImpact()
    {
        $webserviceName = $this->rule_data['centreonimpact'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get urgencies from isilog
    *
    * @return {array} $this->isilogCallResult['response'] list of urgencies
    *
    * throw \Exception if we can't get urgencies data
    */
    protected function getUrgency()
    {
        $webserviceName = $this->rule_data['centreonurgency'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->isilogCallResult;
    }

    /*
    * get various informations from isilog
    *
    * @return {array} $this->isilogCallResult['response'] list of informations
    *
    * throw \Exception if we can't get informations data
    */
    protected function getOthers()
    {
        $webserviceName = $this->rule_data['centreonothers'];
        $soapInfo = array(
            'action' => 'http://isilog.fr/IIsiQueryService/IsiGetQueryResult',
            'content-type' => 'application/soap+xml;charset=UTF-8',
            'webservice' => 'WebServices/IsiQueryService.svc',
            'isicallprogram' => ''
        );

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, 1);

        // get total number of categories and put result in a SimpleXMLElement Object
        try {
            $numberOfResult = $this->getSoapData($soapInfo, true);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $soapInfo['envelope'] = $this->getSoapEnvelope($webserviceName, $numberOfResult);

        try {
            $this->get_others = true;
            $this->getOthersResult = $this->getSoapData($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return $this->getOthersResult;
    }

    /*
    * handle ticket creation in isilog
    *
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {string} $ticketId ticket id
    *
    * throw \Exception if we can't open a ticket
    * throw \Exception if the soap webservice return an error
    */
    protected function createTicket($ticketArguments)
    {
        // L_TITRENEWS , title
        // DE_SYMPAPPEL , body
        // IDT_APPEL , ticket id
        $ticketFields = '';

        if (isset($ticketArguments['team']) && $ticketArguments['team'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_EQUIPE</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['team'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['origin']) && $ticketArguments['origin'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_ORIGINE</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['origin'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['qualifier']) && $ticketArguments['qualifier'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_QUALIFICATIF</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['qualifier'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['impact']) && $ticketArguments['impact'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_SEVERITE</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['impact'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['category']) && $ticketArguments['category'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_TYPEPB</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['category'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['urgency']) && $ticketArguments['urgency'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_BLOCAGE</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['urgency'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['service']) && $ticketArguments['service'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_OBJETSERVICE</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['service'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['site']) && $ticketArguments['site'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_SITE_DEM</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['site'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['OU']) && $ticketArguments['OU'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_SERVICE_DEM</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['OU'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['user']) && $ticketArguments['user'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_UTIL_INT</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['user'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        if (isset($ticketArguments['customer']) && $ticketArguments['customer'] != '-1') {
            $ticketFields .= '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_UTIL_DEM</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['customer'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>';
        }

        $soapInfo = array(
            'action' => 'http://isilog.fr/IsiAddAndGetCall',
            'content-type' => 'text/xml;charset=UTF-8',
            'webservice' => 'webservices/isihelpdeskservice.asmx',
            'isicallprogram' => 'IsiAddAndGetCall',
            'envelope' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
                . 'xmlns:isil="http://isilog.fr">'
                . '<soapenv:Header>' . $this->buildHeader('IsiAddAndGetCall') . '</soapenv:Header>'
                . '<soapenv:Body>'
                . '<isil:IsiAddAndGetCall>'
                . '<isil:pIsiCallEntity>'
                . '<isil:IsiFields>'
                . '<isil:IsiWsDataField>'
                . '<isil:IsiField>L_TITRENEWS</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['title'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>'
                . '<isil:IsiWsDataField>'
                . '<isil:IsiField>DE_SYMPAPPEL</isil:IsiField>'
                . '<isil:IsiValue>' . $ticketArguments['content'] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>' . $ticketFields . '</isil:IsiFields>'
                . '</isil:pIsiCallEntity>'
                . '</isil:IsiAddAndGetCall>'
                . '</soapenv:Body>'
                . '</soapenv:Envelope>'
        );
        // open ticket in isilog and put result in a SimpleXMLElement Object
        try {
            $this->isilogCallResult = simplexml_load_string($this->callWebservice($soapInfo));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        // extract the xml with the data from the soap envelope
        $xmlResult = $this->isilogCallResult->children('soap', true)->Body->children()
            ->IsiAddAndGetCallResponse->IsiAddAndGetCallResult;
        $fixEncoding = preg_replace('/encoding="utf-16"/i', 'encoding="utf-8"', $xmlResult[0]);
        $ticketData = simplexml_load_string($fixEncoding);

        // check if soap webservice returned an error
        if ($ticketData->children()->Statut[0] != 'responseOk') {
            throw new \Exception($ticketData->children()->Trace[0], 12);
        }

        foreach ($ticketData->children()->Objects->anyType->IsiFields->IsiWsDataField as $isiField) {
            if ($isiField->IsiField == 'IDT_APPEL') {
                $humanTicketId = $isiField->IsiValue;
            }

            if ($isiField->IsiField == 'NO_APPEL') {
                $isilogTicketId = $isiField->IsiValue;
            }
        }

        $ticketId = $humanTicketId   . '_' . $isilogTicketId;
        // $ticketId = $humanTicketId;

        return $ticketId;
    }

    /*
    * create soap header
    *
    * @param {string} the name of the isiCallProgram
    *
    * @return {string} soap header
    */
    protected function buildHeader($isiCallProgram = null)
    {
        $soapHeader = '<isil:IsiWsAuthHeader>'
            . '<isil:IsiCallProgram>' . $isiCallProgram . '</isil:IsiCallProgram>'
            . '<isil:IsiDataBaseID>' . $this->rule_data['database'] . '</isil:IsiDataBaseID>'
            . '<isil:IsiLogin>' . $this->rule_data['username'] . '</isil:IsiLogin>'
            . '<isil:IsiPassword>' . $this->rule_data['password'] . '</isil:IsiPassword>'
            . '</isil:IsiWsAuthHeader>';

        return $soapHeader;
    }

    /*
    * create soap envelope
    *
    * @param {string} $webserviceNamethe name of the webservice
    * @param {integer} $numberOfResult he number of result we want
    *
    * @return {string} $envelope the soap envelope
    */
    protected function getSoapEnvelope($webserviceName, $numberOfResult)
    {
        $envelope = '<soap:Envelope xmlns:s="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:soap="http://www.w3.org/2003/05/soap-envelope" '
            . 'xmlns:wsa="http://wwww.w3.org/2005/08/addressing" '
            . 'xmlns:isil="http://isilog.fr/" >'
            . '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">' . $this->buildHeader()
            . '<wsa:Action>http://isilog.fr/IIsiQueryService/IsiGetQueryResult</wsa:Action>'
            . '<wsa:To>' . $this->rule_data['protocol'] . '//' . $this->rule_data['address'] . '/IsilogWebSystem/WebServices/IsiQueryService.svc</wsa:To></soap:Header>'
            . '<soap:Body>'
            . '<isil:IsiGetQueryResult>'
            . '<isil:queryId>' . $webserviceName . '</isil:queryId>'
            . '<isil:limit>' . $numberOfResult . '</isil:limit>'
            . '<isil:totalRecordCount>1</isil:totalRecordCount>'
            . '<isil:modifiedCriteria>'
            . '</isil:modifiedCriteria>'
            . '</isil:IsiGetQueryResult>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        return $envelope;
    }

    /*
    * retrieve soap data from isilog
    *
    * @param {array} $soapInfo soap query related data
    * @param {boolean} $getEntriesNumber get webservice entries or get webservice number of entries
    *
    * @return {integer} $numberOfResultor number of entries
    * @return {string} $this->isilogCallResult entries data
    *
    * throw \Exception if we can't get data or the data is not valid
    */
    protected function getSoapData($soapInfo, $getEntriesNumber = false)
    {
        try {
            $this->isilogCallResult = $this->callWebservice($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $xmlResult = simplexml_load_string($this->isilogCallResult);
        $statut = $xmlResult->children('s', true)->Body->children()
            ->IsiGetQueryResultResponse->IsiGetQueryResultResult->Statut;

        if ($statut != 'responseOk') {
            throw new \Exception($statut, 13);
        }

        if ($getEntriesNumber == true) {
            $numberOfResult = $xmlResult->children('s', true)->Body->children()
                ->IsiGetQueryResultResponse->IsiGetQueryResultResult->TotalRecordCount;
            return $numberOfResult;
        } else {
            return $this->isilogCallResult;
        }
    }

    /*
    * Handle the soap connection
    *
    * @param {array} $soapInfo soap query related data
    *
    * @return {string} $curlResult result of the query
    *
    * throw \Exception if we can't get data
    */
    protected function callWebservice($soapInfo)
    {
        $curlEndpoint = $this->rule_data['protocol'] . '://'
            . $this->rule_data['address'] . '/' . $soapInfo['webservice'];

        $curlHeader = array(
            'Content-Type: ' . $soapInfo['content-type'],
            'SOAPAction: ' . $soapInfo['action'],
            'Content-Length: ' . strlen($soapInfo['envelope'])
        );

        $checkCertificate = (
            isset($this->rule_data['check_certificate'])
            && $this->rule_data['check_certificate'] == 'yes'
        ) ? true : false;

        // initiate our curl options
        $curl = curl_init($curlEndpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeader);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $checkCertificate);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soapInfo['envelope']);

        // if proxy is set, we add it to curl
        if ($this->getFormValue('proxy_address') != '' && $this->getFormValue('proxy_port') != '') {
            curl_setopt($curl, CURLOPT_PROXY, $this->getFormValue('proxy_address') . ':'
                . $this->getFormValue('proxy_port'));
            // if proxy authentication configuration is set, we add it to curl
            if ($this->getFormValue('proxy_username') != '' && $this->getFormValue('proxy_password') != '') {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->getFormValue('proxy_username') . ':'
                    . $this->getFormValue('proxy_password'));
            }
        }

        $curlResult = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode > 301) {
            throw new Exception('curl result: ' . $curlResult . '|| HTTP return code: ' . $httpCode, 11);
        }

        return $curlResult;
    }

    /*
    * close a ticket in isilog
    *
    * @params {string} $ticketId the ticket id
    *
    * @return {bool}
    *
    * throw \Exception if it can't close the ticket
    */
    protected function closeTicketIsilog($ticketId)
    {
        preg_match('/(\w+)_(\w+)/', $ticketId, $matches);

        $soapInfo = array(
            'action' => 'http://isilog.fr/IsiUpdateAndGetCall',
            'webservice' => 'webservices/isihelpdeskservice.asmx',
            'isicallprogram' => 'IsiUpdateAndGetCall',
            'content-type' => 'application/soap+xml;charset=UTF-8;action="http://isilog.fr/IsiUpdateAndGetCall"',
            'envelope' => '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" '
                . 'xmlns:isil="http://isilog.fr">'
                . '<soap:Header>' . $this->buildHeader('IsiUpdateAndGetCall') . '</soap:Header>'
                . '<soap:Body>'
                . '<isil:IsiUpdateAndGetCall>'
                . '<isil:pIsiCallEntity>'
                . '<isil:IsiFields>'
                . '<isil:IsiWsDataField>'
                . '<isil:IsiField>NO_APPEL</isil:IsiField>'
                . '<isil:IsiValue>' . $matches[2] . '</isil:IsiValue>'
                . '</isil:IsiWsDataField>'
                . '<isil:IsiWsDataField>'
                . '<isil:IsiField>C_STAPPEL</isil:IsiField>'
                . '<isil:IsiValue>AC</isil:IsiValue>'
                . '</isil:IsiWsDataField>'
                . '</isil:IsiFields>'
                . '</isil:pIsiCallEntity>'
                . '</isil:IsiUpdateAndGetCall>'
                . '</soap:Body>'
                . '</soap:Envelope>'
        );

        try {
            $this->isilogCallResult = $this->callWebservice($soapInfo);
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
                    $this->closeTicketIsilog($k);
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