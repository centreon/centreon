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

class OtrsProvider extends AbstractProvider
{
    protected $otrs_connected = 0;
    protected $otrs_session = null;
    protected $attach_files = 1;
    protected $close_advanced = 1;

    public const OTRS_QUEUE_TYPE = 10;
    public const OTRS_PRIORITY_TYPE = 11;
    public const OTRS_STATE_TYPE = 12;
    public const OTRS_TYPE_TYPE = 13;
    public const OTRS_CUSTOMERUSER_TYPE = 14;
    public const OTRS_OWNER_TYPE = 15;
    public const OTRS_RESPONSIBLE_TYPE = 16;

    public const ARG_QUEUE = 1;
    public const ARG_PRIORITY = 2;
    public const ARG_STATE = 3;
    public const ARG_TYPE = 4;
    public const ARG_CUSTOMERUSER = 5;
    public const ARG_SUBJECT = 6;
    public const ARG_BODY = 7;
    public const ARG_FROM = 8;
    public const ARG_CONTENTTYPE = 9;
    public const ARG_OWNER = 17;
    public const ARG_RESPONSIBLE = 18;

    protected $internal_arg_name = array(
        self::ARG_QUEUE => 'Queue',
        self::ARG_PRIORITY => 'Priority',
        self::ARG_STATE => 'State',
        self::ARG_TYPE => 'Type',
        self::ARG_CUSTOMERUSER => 'CustomerUser',
        self::ARG_SUBJECT => 'Subject',
        self::ARG_BODY => 'Body',
        self::ARG_FROM => 'From',
        self::ARG_CONTENTTYPE => 'ContentType',
        self::ARG_OWNER => 'Owner',
        self::ARG_RESPONSIBLE => 'Responsible',
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
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/otrs';
        $this->default_data['rest_link'] = 'nph-genericinterface.pl/Webservice';
        $this->default_data['webservice_name'] = 'centreon';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_SUBJECT,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/' .
                    'Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_BODY, 'Value' => '{$body}'),
            array('Arg' => self::ARG_FROM, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_QUEUE, 'Value' => '{$select.otrs_queue.value}'),
            array('Arg' => self::ARG_PRIORITY, 'Value' => '{$select.otrs_priority.value}'),
            array('Arg' => self::ARG_STATE, 'Value' => '{$select.otrs_state.value}'),
            array('Arg' => self::ARG_TYPE, 'Value' => '{$select.otrs_type.value}'),
            array('Arg' => self::ARG_CUSTOMERUSER, 'Value' => '{$select.otrs_customeruser.value}'),
            array('Arg' => self::ARG_CONTENTTYPE, 'Value' => 'text/html; charset=utf8'),
        );
    }

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain(1);
        $this->default_data['url'] = 'http://{$address}/index.pl?Action=AgentTicketZoom;TicketNumber={$ticket_id}';
        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'otrs_queue',
                'Label' => _('Otrs queue'),
                'Type' => self::OTRS_QUEUE_TYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
            array(
                'Id' => 'otrs_priority',
                'Label' => _('Otrs priority'),
                'Type' => self::OTRS_PRIORITY_TYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
            array(
                'Id' => 'otrs_state',
                'Label' => _('Otrs state'),
                'Type' => self::OTRS_STATE_TYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
            array(
                'Id' => 'otrs_type',
                'Label' => _('Otrs type'),
                'Type' => self::OTRS_TYPE_TYPE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'otrs_customeruser',
                'Label' => _('Otrs customer user'),
                'Type' => self::OTRS_CUSTOMERUSER_TYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
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
        $this->checkFormValue('rest_link', "Please set 'Rest Link' value");
        $this->checkFormValue('webservice_name', "Please set 'Webservice Name' value");
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
        $tpl = $this->initSmartyTemplate('providers/Otrs/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("otrs" => _("OTRS")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' .
            $this->getFormValue('path') . '" />';
        $rest_link_html = '<input size="50" name="rest_link" type="text" value="' .
            $this->getFormValue('rest_link') . '" />';
        $webservice_name_html = '<input size="50" name="webservice_name" type="text" value="' .
            $this->getFormValue('webservice_name') . '" />';
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
            'rest_link' => array('label' => _("Rest link") . $this->required_field, 'html' => $rest_link_html),
            'webservice_name' => array(
                'label' => _("Webservice name") . $this->required_field,
                'html' => $webservice_name_html
            ),
            'username' => array('label' => _("Username") . $this->required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
            'mappingticketdynamicfield' => array('label' => _("Mapping ticket dynamic field")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">' .
        '<option value="' . self::ARG_QUEUE . '">' . _('Queue') . '</options>' .
        '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</options>' .
        '<option value="' . self::ARG_STATE . '">' . _('State') . '</options>' .
        '<option value="' . self::ARG_TYPE . '">' . _('Type') . '</options>' .
        '<option value="' . self::ARG_CUSTOMERUSER . '">' . _('Customer user') . '</options>' .
        '<option value="' . self::ARG_OWNER . '">' . _('Owner') . '</options>' .
        '<option value="' . self::ARG_RESPONSIBLE . '">' . _('Responsible') . '</options>' .
        '<option value="' . self::ARG_FROM . '">' . _('From') . '</options>' .
        '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</options>' .
        '<option value="' . self::ARG_BODY . '">' . _('Body') . '</options>' .
        '<option value="' . self::ARG_CONTENTTYPE . '">' . _('Content Type') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        // mapping Ticket DynamicField
        $mappingTicketDynamicFieldName_html = '<input id="mappingTicketDynamicFieldName_#index#" ' .
            'name="mappingTicketDynamicFieldName[#index#]" size="20"  type="text" />';
        $mappingTicketDynamicFieldValue_html = '<input id="mappingTicketDynamicFieldValue_#index#" ' .
            'name="mappingTicketDynamicFieldValue[#index#]" size="20"  type="text" />';
        $array_form['mappingTicketDynamicField'] = array(
            array('label' => _("Name"), 'html' => $mappingTicketDynamicFieldName_html),
            array('label' => _("Value"), 'html' => $mappingTicketDynamicFieldValue_html),
        );

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
        $this->config['clones']['mappingTicketDynamicField'] = $this->getCloneValue('mappingTicketDynamicField');
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
        $this->save_config['simple']['rest_link'] = $this->submitted_config['rest_link'];
        $this->save_config['simple']['webservice_name'] = $this->submitted_config['webservice_name'];
        $this->save_config['simple']['username'] = $this->submitted_config['username'];
        $this->save_config['simple']['password'] = $this->submitted_config['password'];
        $this->save_config['simple']['https'] = (isset($this->submitted_config['https'])
            && $this->submitted_config['https'] == 'yes')
            ? $this->submitted_config['https'] : '';
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];

        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
        $this->save_config['clones']['mappingTicketDynamicField'] = $this->getCloneSubmitted(
            'mappingTicketDynamicField',
            array('Name', 'Value')
        );
    }

    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::OTRS_QUEUE_TYPE . '">Otrs queue</options>' .
        '<option value="' . self::OTRS_PRIORITY_TYPE . '">Otrs priority</options>' .
        '<option value="' . self::OTRS_STATE_TYPE . '">Otrs state</options>' .
        '<option value="' . self::OTRS_CUSTOMERUSER_TYPE . '">Otrs customer user</options>' .
        '<option value="' . self::OTRS_TYPE_TYPE . '">Otrs type</options>' .
        '<option value="' . self::OTRS_OWNER_TYPE . '">Otrs owner</options>' .
        '<option value="' . self::OTRS_RESPONSIBLE_TYPE . '">Otrs responsible</options>';
        return $str;
    }

    protected function assignOtrsQueue($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listQueueOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('otrs_queue', $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOtrsPriority($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listPriorityOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('otrs_priority', $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOtrsState($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listStateOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('otrs_state', $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOtrsType($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listTypeOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('otrs_type', $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOtrsCustomerUser($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listCustomerUserOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('otrs_customeruser', $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOtrsUser($entry, &$groups_order, &$groups, $label_session)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listUserOtrs();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession($label_session, $this->otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::OTRS_QUEUE_TYPE) {
            $this->assignOtrsQueue($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_PRIORITY_TYPE) {
            $this->assignOtrsPriority($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_STATE_TYPE) {
            $this->assignOtrsState($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_TYPE_TYPE) {
            $this->assignOtrsType($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_CUSTOMERUSER_TYPE) {
            $this->assignOtrsCustomerUser($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_OWNER_TYPE) {
            $this->assignOtrsUser($entry, $groups_order, $groups, 'otrs_owner');
        } elseif ($entry['Type'] == self::OTRS_RESPONSIBLE_TYPE) {
            $this->assignOtrsUser($entry, $groups_order, $groups, 'otrs_responsible');
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
                if ($value['Type'] == self::OTRS_QUEUE_TYPE) {
                    $session_name = 'otrs_queue';
                } elseif ($value['Type'] == self::OTRS_PRIORITY_TYPE) {
                    $session_name = 'otrs_priority';
                } elseif ($value['Type'] == self::OTRS_STATE_TYPE) {
                    $session_name = 'otrs_state';
                } elseif ($value['Type'] == self::OTRS_TYPE_TYPE) {
                    $session_name = 'otrs_type';
                } elseif ($value['Type'] == self::OTRS_CUSTOMERUSER_TYPE) {
                    $session_name = 'otrs_customeruser';
                } elseif ($value['Type'] == self::OTRS_OWNER_TYPE) {
                    $session_name = 'otrs_owner';
                } elseif ($value['Type'] == self::OTRS_RESPONSIBLE_TYPE) {
                    $session_name = 'otrs_responsible';
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
        $ticket_dynamic_fields = array();
        if (isset($this->rule_data['clones']['mappingTicketDynamicField'])) {
            foreach ($this->rule_data['clones']['mappingTicketDynamicField'] as $value) {
                if ($value['Name'] == '' ||  $value['Value'] == '') {
                    continue;
                }
                $array_tmp = array();
                $tpl->assign('string', $value['Name']);
                $array_tmp = array('Name' => $tpl->fetch('eval.ihtml'));

                $tpl->assign('string', $value['Value']);
                $array_tmp['Value'] = $tpl->fetch('eval.ihtml');

                $ticket_dynamic_fields[] = $array_tmp;
            }
        }

        $code = $this->createTicketOtrs($ticket_arguments, $ticket_dynamic_fields);
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
                'ticket_value' => $this->otrs_call_response['TicketNumber'],
                'subject' => $ticket_arguments['Subject'],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array(
                        'arguments' => $ticket_arguments,
                        'dynamic_fields' => $ticket_dynamic_fields
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

    protected function listQueueOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('QueueGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function listPriorityOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('PriorityGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function listStateOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('StateGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function listTypeOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('TypeGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function listCustomerUserOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('CustomerUserGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function listUserOtrs()
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array('SessionID' => $this->otrs_session);
        if ($this->callRest('UserGet', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function closeTicketOtrs($ticket_number)
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array(
            'SessionID' => $this->otrs_session,
            'TicketNumber' => $ticket_number,
            'Ticket' => array(
                'State' => 'closed successful',
            ),
        );

        if ($this->callRest('TicketUpdate', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function createTicketOtrs($ticket_arguments, $ticket_dynamic_fields)
    {
        if ($this->otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }

        $argument = array(
            'SessionID' => $this->otrs_session,
            'Ticket' => array(
                'Title'             => $ticket_arguments['Subject'],
                //'QueueID'         => xxx,
                'Queue'             => $ticket_arguments['Queue'],
                //'StateID'         => xxx,
                'State'             => $ticket_arguments['State'],
                //'PriorityID'      => xxx,
                'Priority'          => $ticket_arguments['Priority'],
                //'TypeID'          => 123,
                'Type'              => $ticket_arguments['Type'],
                //'OwnerID'         => 123,
                'Owner'             => $ticket_arguments['Owner'],
                //'ResponsibleID'   => 123,
                'Responsible'       => $ticket_arguments['Responsible'],
                'CustomerUser'      => $ticket_arguments['CustomerUser'],
            ),
            'Article' => array(
                'From' => $ticket_arguments['From'], // Must be an email
                'Subject' => $ticket_arguments['Subject'],
                'Body' => $ticket_arguments['Body'],
                'ContentType' => $ticket_arguments['ContentType'],
            ),
        );

        $files = array();
        $attach_files = $this->getUploadFiles();
        foreach ($attach_files as $file) {
            $base64_content = base64_encode(file_get_contents($file['filepath']));
            $files[] = array(
                'Content' => $base64_content,
                'Filename' => $file['filename'],
                'ContentType' => mime_content_type($file['filepath'])
            );
        }
        if (count($files) > 0) {
            $argument['Attachment'] = $files;
        }

        if (count($ticket_dynamic_fields) > 0) {
            $argument['DynamicField'] = $ticket_dynamic_fields;
        }

        if ($this->callRest('TicketCreate', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function loginOtrs()
    {
        if ($this->otrs_connected == 1) {
            return 0;
        }

        if (!extension_loaded("curl")) {
            $this->setWsError("cannot load curl extension");
            return -1;
        }

        $argument = array('UserLogin' => $this->rule_data['username'], 'Password' => $this->rule_data['password']);
        if ($this->callRest('SessionCreate', $argument) == 1) {
            return -1;
        }

        $this->otrs_session = $this->otrs_call_response['SessionID'];
        $this->otrs_connected = 1;
        return 0;
    }

    protected function callRest($function, $argument)
    {
        $this->otrs_call_response = null;

        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }

        $argument_json = json_encode($argument);
        $base_url = $proto . '://' . $this->rule_data['address'] . $this->rule_data['path'] . '/' .
            $this->rule_data['rest_link'] . '/' . $this->rule_data['webservice_name'] . '/' . $function . '/';
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($argument_json)
            )
        );
        $result = curl_exec($ch);
        if ($result == false) {
            $this->setWsError(curl_error($ch));
            curl_close($ch);
            return 1;
        }

        $decoded_result = json_decode($result, true);
        if (is_null($decoded_result) || $decoded_result == false) {
            $this->setWsError($result);
            return 1;
        }

        curl_close($ch);

        if (isset($decoded_result['Error'])) {
            $this->setWsError($decoded_result['Error']['ErrorMessage']);
            return 1;
        }

        $this->otrs_call_response = $decoded_result;
        return 0;
    }

    public function closeTicket(&$tickets)
    {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                if ($this->closeTicketOtrs($k) == 0) {
                    $tickets[$k]['status'] = 2;
                } else {
                    $tickets[$k]['status'] = -1;
                    $tickets[$k]['msg_error'] = $this->ws_error;
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }
}
