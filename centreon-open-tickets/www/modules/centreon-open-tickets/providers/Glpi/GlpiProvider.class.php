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

class GlpiProvider extends AbstractProvider
{
    protected $glpi_connected = 0;
    protected $glpi_session = null;

    public const GPLI_ENTITIES_TYPE = 10;
    public const GPLI_GROUPS_TYPE = 11;
    public const GLPI_ITIL_CATEGORIES_TYPE = 12;

    public const ARG_CONTENT = 1;
    public const ARG_ENTITY = 2;
    public const ARG_URGENCY = 3;
    public const ARG_IMPACT = 4;
    public const ARG_CATEGORY = 5;
    public const ARG_USER = 6;
    public const ARG_USER_EMAIL = 7;
    public const ARG_GROUP = 8;
    public const ARG_GROUP_ASSIGN = 9;
    public const ARG_TITLE = 10;

    protected $internal_arg_name = array(
        self::ARG_CONTENT => 'content',
        self::ARG_ENTITY => 'entity',
        self::ARG_URGENCY => 'urgency',
        self::ARG_IMPACT => 'impact',
        self::ARG_CATEGORY => 'category',
        self::ARG_USER => 'user',
        self::ARG_USER_EMAIL => 'user_email',
        self::ARG_GROUP => 'group',
        self::ARG_GROUP_ASSIGN => 'groupassign',
        self::ARG_TITLE => 'title',
    );

    function __destruct()
    {
        $this->logoutGlpi();
    }

    /**
     * Set default extra value
     *
     * @return void
     */
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/glpi/plugins/webservices/xmlrpc.php';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_ENTITY, 'Value' => '{$select.gpli_entity.id}'),
            array('Arg' => self::ARG_CATEGORY, 'Value' => '{$select.glpi_itil_category.id}'),
            array('Arg' => self::ARG_GROUP_ASSIGN, 'Value' => '{$select.glpi_group.id}'),
            array('Arg' => self::ARG_USER_EMAIL, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_URGENCY, 'Value' => '{$select.urgency.value}'),
            array('Arg' => self::ARG_IMPACT, 'Value' => '{$select.impact.value}'),
        );
    }

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = 'http://{$address}/glpi/front/ticket.form.php?id={$ticket_id}';

        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'gpli_entity',
                'Label' => _('Entity'),
                'Type' => self::GPLI_ENTITIES_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'glpi_group',
                'Label' => _('Glpi group'),
                'Type' => self::GPLI_GROUPS_TYPE,
                'Filter' => '', 'Mandatory' => ''
            ),
            array(
                'Id' => 'glpi_itil_category',
                'Label' => _('Itil category'),
                'Type' => self::GLPI_ITIL_CATEGORIES_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'urgency',
                'Label' => _('Urgency'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'impact',
                'Label' => _('Impact'),
                'Type' => self::CUSTOM_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
        );
        $this->default_data['clones']['customList'] = array(
            array('Id' => 'urgency', 'Value' => '1', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '2', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '3', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '4', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '5', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '1', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '2', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '3', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '4', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '5', 'Default' => ''),
        );
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
        $tpl = $this->initSmartyTemplate('providers/Glpi/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("glpi" => _("Glpi")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' .
            $this->getFormValue('path') . '" />';
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
            'path' => array('label' => _("Path"), 'html' => $path_html),
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
        '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
        '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
        '<option value="' . self::ARG_ENTITY . '">' . _('Entity') . '</options>' .
        '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</options>' .
        '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
        '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</options>' .
        '<option value="' . self::ARG_USER . '">' . _('User') . '</options>' .
        '<option value="' . self::ARG_USER_EMAIL . '">' . _('User email') . '</options>' .
        '<option value="' . self::ARG_GROUP . '">' . _('Group') . '</options>' .
        '<option value="' . self::ARG_GROUP_ASSIGN . '">' . _('Group assign') . '</options>' .
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
    }

    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['path'] = $this->submitted_config['path'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['https'] = (
            isset($this->submitted_config['https'])
            && $this->submitted_config['https'] == 'yes'
        ) ? $this->submitted_config['https'] : '';
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
    }

    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::GPLI_ENTITIES_TYPE . '">Glpi entities</options>' .
        '<option value="' . self::GPLI_GROUPS_TYPE . '">Glpi groups</options>' .
        '<option value="' . self::GLPI_ITIL_CATEGORIES_TYPE . '">Glpi itil categories</options>';
        return $str;
    }

    protected function assignGlpiEntities($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listEntitiesGlpi();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->rpc_error;
            return 0;
        }

        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['completename']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['completename'])) {
                $result[$row['id']] = $this->to_utf8($row['completename']);
            }
        }

        $this->saveSession('glpi_entities', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignGlpiGroups($entry, &$groups_order, &$groups)
    {
        $filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
        $code = $this->listGroupsGlpi($filter);

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->rpc_error;
            return 0;
        }

        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            $result[$row['id']] = $this->to_utf8($row['completename']);
        }

        $this->saveSession('glpi_groups', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignItilCategories($entry, &$groups_order, &$groups)
    {
        $filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
        $code = $this->listItilCategoriesGlpi($filter);

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->rpc_error;
            return 0;
        }

        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            $result[$row['id']] = $this->to_utf8($row['name']);
        }

        $this->saveSession('glpi_itil_categories', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::GPLI_ENTITIES_TYPE) {
            $this->assignGlpiEntities($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GPLI_GROUPS_TYPE) {
            $this->assignGlpiGroups($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
            $this->assignItilCategories($entry, $groups_order, $groups);
        }
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);
        return $result;
    }

    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id)
    {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {
                if ($value['Type'] == self::GPLI_ENTITIES_TYPE) {
                    $session_name = 'glpi_entities';
                } elseif ($value['Type'] == self::GPLI_GROUPS_TYPE) {
                    $session_name = 'glpi_groups';
                } elseif ($value['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
                    $session_name = 'glpi_itil_categories';
                }
            }
        }

        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }

        $result = $this->getSession($session_name);

        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value) {
            if ($value['id'] == $selected_id) {
                return $value;
            }
        }

        return array();
    }

    protected function doSubmit(
        $db_storage,
        $contact,
        $host_problems,
        $service_problems,
        $extra_ticket_arguments = array()
    ) {
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

        $ticket_arguments = $extra_ticket_arguments;
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $result_str = $tpl->fetch('eval.ihtml');

                if ($result_str == '') {
                    $result_str = null;
                }

                $ticket_arguments[$this->internal_arg_name[$value['Arg']]] = $result_str;
                // Old version of GLPI use 'recipient' depiste groupassign
                if ($value['Arg'] == self::ARG_GROUP_ASSIGN) {
                    $ticket_arguments['recipient'] = $result_str;
                }
            }
        }

        $code = $this->createTicketGlpi($ticket_arguments);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->rpc_error;
            return $result;
        }

        $this->saveHistory(
            $db_storage,
            $result,
            array(
                'contact' => $contact,
                'host_problems' => $host_problems,
                'service_problems' => $service_problems,
                'ticket_value' => $this->glpi_call_response['response']['id'],
                'subject' => $ticket_arguments[self::ARG_TITLE],
                'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode($ticket_arguments)
            )
        );
        return $result;
    }

    /*
     *
     * XML-RPC Calls
     *
     */
    protected function setRpcError($error)
    {
        $this->rpc_error = $error;
    }

    protected function requestRpc($method, $args = null)
    {
        $array_result = array('code' => -1);
        if (is_null($args)) {
            $args = array();
        }
        foreach ($args as $key => $value) {
            if (is_null($value)) {
                unset($args[$key]);
            }
        }

        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        $host = $this->rule_data['address'];

        $url = '/';
        if (!is_null($this->rule_data['path']) || $this->rule_data['path'] != '') {
            $url = $this->rule_data['path'];
        }
        if ($this->glpi_connected == 1) {
            $url .= '?session=' . $this->glpi_session;
        }

        $request = xmlrpc_encode_request($method, $args, array('encoding' => 'utf-8', 'escaping' => 'markup'));
        $context = stream_context_create(
            array(
                'http' => array(
                    'method'  => "POST",
                    'header'  => 'Content-Type: text/xml',
                    'timeout' => $this->rule_data['timeout'],
                    'content' => $request
                )
            )
        );
        $file = file_get_contents("$proto://$host/$url", false, $context);
        if (!$file) {
            $this->setRpcError("webservice '$method': no response");
            return $array_result;
        }
        $response = xmlrpc_decode($file);
        if (!is_array($response)) {
            $this->setRpcError("webservice '$method': bad response");
            return $array_result;
        }
        if (xmlrpc_is_fault($response)) {
            $this->setRpcError("webservice '$method' error (" . $response['faultCode'] . "): " .
                $this->to_utf8($response['faultString']));
            return $array_result;
        }

        $array_result['response'] = $response;
        $array_result['code'] = 0;
        return $array_result;
    }

    protected function listEntitiesGlpi()
    {
        if ($this->glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->requestRpc('glpi.listEntities', array('start' => 0, 'limit' => 100));
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function listGroupsGlpi($filter = null)
    {
        if ($this->glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->requestRpc(
            'glpi.listGroups',
            array('start' => 0, 'limit' => 100, 'name' => $filter)
        );
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function listItilCategoriesGlpi($filter = null)
    {
        if ($this->glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->requestRpc(
            'glpi.listObjects',
            array(
                'start' => 0,
                'limit' => 100,
                'name' => $filter,
                'itemtype' => 'itilcategory',
                'show_label' => 1
            )
        );
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function createTicketGlpi($arguments)
    {
        if ($this->glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->requestRpc('glpi.createTicket', $arguments);
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function listObjects($arguments)
    {
        if ($this->glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->requestRpc('glpi.listObjects', $arguments);
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function logoutGlpi()
    {
        if ($this->glpi_connected == 0) {
            return 0;
        }
        $this->glpi_call_response = $this->requestRpc('glpi.doLogout');
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function loginGlpi()
    {
        if ($this->glpi_connected == 1) {
            return 0;
        }
        if (!extension_loaded("xmlrpc")) {
            $this->setRpcError("cannot load xmlrpc extension");
            return -1;
        }

        $this->glpi_call_response = $this->requestRpc(
            'glpi.doLogin',
            array(
                'login_name' => $this->rule_data['username'],
                'login_password' => $this->rule_data['password']
            )
        );
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        $this->glpi_session = $this->glpi_call_response['response']['session'];
        $this->glpi_connected = 1;
        return 0;
    }
}
