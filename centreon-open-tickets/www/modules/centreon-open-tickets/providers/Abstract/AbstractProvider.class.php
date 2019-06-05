<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
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

require_once(dirname(__FILE__) . '/CentreonCommon.php');

abstract class AbstractProvider {
    /**
     * Set the default extra data
     */
    abstract protected function _setDefaultValueExtra();
    /**
     * Check the configuration form
     */
    abstract protected function _checkConfigForm();
    /**
     * Prepare the extra configuration block
     */
    abstract protected function _getConfigContainer1Extra();
    /**
     * Prepare the extra configuration block
     */
    abstract protected function _getConfigContainer2Extra();
    /**
     * Add specific configuration field
     */
    abstract protected function saveConfigExtra();
    /**
     * Validate the popup for submit a ticket
     *
     * @return array The status of validation (
     *  'code' => int,
     *  'message' => string
     * )
     */
    abstract public function validateFormatPopup();
    /**
     * Create a ticket
     *
     * @param CentreonDB $db_storage The centreon_storage database connection
     * @param string $contact The contact who open the ticket
     * @param array $host_problems The list of host issues link to the ticket
     * @param array $service_problems The list of service issues link to the ticket
     * @return array The status of action (
     *  'code' => int,
     *  'message' => string
     * )
     */
    abstract protected function doSubmit($db_storage, $contact, $host_problems, $service_problems);

    protected $_rule;
    protected $_rule_id;
    protected $_centreon_path;
    protected $_centreon_open_tickets_path;
    protected $_config = array("container1_html" => '', "container2_html" => '', "clones" => array());
    protected $_required_field = '&nbsp;<font color="red" size="1">*</font>';
    protected $_submitted_config = null;
    protected $_check_error_message = '';
    protected $_save_config = array();
    protected $_widget_id;
    protected $_uniq_id;
    protected $_attach_files = 0;
    protected $_close_advanced = 0;
    protected $_proxy_enabled = 0;

    const HOSTGROUP_TYPE = 0;
    const HOSTCATEGORY_TYPE = 1;
    const HOSTSEVERITY_TYPE = 2;
    const SERVICEGROUP_TYPE = 3;
    const SERVICECATEGORY_TYPE = 4;
    const SERVICESEVERITY_TYPE = 5;
    const SERVICECONTACTGROUP_TYPE = 6;
    const CUSTOM_TYPE = 7;
    const BODY_TYPE = 8;

    const DATA_TYPE_JSON = 0;
    const DATA_TYPE_XML = 1;

    /**
     * constructor
     *
     * @return void
     */
    public function __construct($rule, $centreon_path, $centreon_open_tickets_path, $rule_id, $submitted_config = null, $provider_id) {
        $this->_rule = $rule;
        $this->_centreon_path = $centreon_path;
        $this->_centreon_open_tickets_path = $centreon_open_tickets_path;
        $this->_rule_id = $rule_id;
        $this->_submitted_config = $submitted_config;
        $this->rule_data = $rule->get($rule_id);
        $this->rule_list = $rule->getRuleList();

        if (is_null($rule_id) || !isset($this->rule_data['provider_id']) || $provider_id != $this->rule_data['provider_id']) {
            $this->default_data = array();
            $this->default_data['clones'] = array();
            $this->_setDefaultValueMain();
            $this->_setDefaultValueExtra();
        }
        // We reset value. We have changed provider on same form
        if (isset($this->rule_data['provider_id']) && $provider_id != $this->rule_data['provider_id']) {
            $this->rule_data = array();
        }

        $this->_widget_id = null;
        $this->_uniq_id = null;
    }

    protected function initSmartyTemplate($path="providers/Abstract/templates") {
        $tpl = new Smarty();
        $tpl->register_function('host_get_hostgroups', 'smarty_function_host_get_hostgroups');
        $tpl->register_function('host_get_severity', 'smarty_function_host_get_severity');
        $tpl->register_function('host_get_hostcategories', 'smarty_function_host_get_hostcategories');
        $tpl->register_function('host_get_macro_value_in_config', 'smarty_function_host_get_macro_value_in_config');
        $tpl->register_function('host_get_macro_values_in_config', 'smarty_function_host_get_macro_values_in_config');
        $tpl->register_function('service_get_servicecategories', 'smarty_function_service_get_servicecategories');
        $tpl->register_function('service_get_servicegroups', 'smarty_function_service_get_servicegroups');
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, $path, $this->_centreon_path);
        return $tpl;
    }

    public function setWidgetId($widget_id) {
        $this->_widget_id = $widget_id;
    }

    public function setUniqId($uniq_id) {
        $this->_uniq_id = $uniq_id;
    }

    protected function clearSession() {
        if (!is_null($this->_uniq_id) && isset($_SESSION['ot_save_' . $this->_uniq_id])) {
            unset($_SESSION['ot_save_' . $this->_uniq_id]);
        }
    }

    protected function saveSession($key, $value) {
        if (!is_null($this->_uniq_id)) {
            if (!isset($_SESSION['ot_save_' . $this->_uniq_id])) {
                $_SESSION['ot_save_' . $this->_uniq_id] = array();
            }
            $_SESSION['ot_save_' . $this->_uniq_id][$key] = $value;
        }
    }

    protected function getUploadFiles() {
        $upload_files = array();
        if (isset($_SESSION['ot_upload_files'][$this->_uniq_id])) {
            foreach (array_keys($_SESSION['ot_upload_files'][$this->_uniq_id]) as $filepath) {
                $filename = basename($filepath);
                if (preg_match('/^.*?__(.*)/', $filename, $matches)) {
                    $upload_files[] = array('filepath' => $filepath, 'filename' => $matches[1]);
                }
            }
        }

        return $upload_files;
    }

    public function clearUploadFiles() {
        $upload_files = $this->getUploadFiles();
        foreach ($upload_files as $file) {
            unlink($file['filepath']);
        }

        unset($_SESSION['ot_upload_files'][$this->_uniq_id]);
    }

    protected function getSession($key) {
        if (!is_null($key) && !is_null($this->_uniq_id) && isset($_SESSION['ot_save_' . $this->_uniq_id][$key])) {
            return $_SESSION['ot_save_' . $this->_uniq_id][$key];
        }
        return null;
    }

    protected function to_utf8($value) {
        $encoding = mb_detect_encoding($value);
        if ($encoding == 'UTF-8') {
            return $value;
        }
        $value = mb_convert_encoding($value, "UTF-8", $encoding);
        return $value;
    }

    protected function _setDefaultValueMain($body_html = 0) {
        $this->default_data['macro_ticket_id'] = 'TICKET_ID';
        $this->default_data['ack'] = 'yes';
        #$this->default_data['close_ticket_enable'] = 'yes';

        $this->default_data['format_popup'] = '
<table class="table">
<tr>
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
<tr>
    <td class="FormRowField" style="padding-left:15px;">{$custom_message.label}</td>
    <td class="FormRowValue" style="padding-left:15px;"><textarea id="custom_message" name="custom_message" cols="50" rows="6"></textarea></td>
</tr>
{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
<!--<tr>
    <td class="FormRowField" style="padding-left:15px;">Add graphs</td>
    <td class="FormRowValue" style="padding-left:15px;"><input type="checkbox" name="add_graph" value="1" /></td>
</tr>-->
</table>
';
        $this->default_data['message_confirm'] = '
<table class="table">
<tr>
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
{if $ticket_is_ok == 1}
    <tr><td class="FormRowField" style="padding-left:15px;">New ticket opened: {$ticket_id}.</td></tr>
{else}
    <tr><td class="FormRowField" style="padding-left:15px;">Error to open the ticket: {$ticket_error_message}.</td></tr>
{/if}
</table>
';
        $this->default_data['format_popup'] = $this->default_data['format_popup'];
        $this->default_data['message_confirm'] = $this->default_data['message_confirm'];

        if ($body_html == 1) {
            $default_body = '
<html>
<body>

<p>{$user.alias} open ticket at {$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}</p>

<p>{$custom_message}</p>

<p>
{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_selected_lists.ihtml" separator="<br/>"}
</p>

{assign var="table_style" value="border-collapse: collapse; border: 1px solid black;"}
{assign var="cell_title_style" value="background-color: #D2F5BB; border: 1px solid black; text-align: center; padding: 10px; text-transform:uppercase; font-weight:bold;"}
{assign var="cell_style" value="border-bottom: 1px solid black; padding: 5px;"}

{if $host_selected|@count gt 0}
    <table cellpading="0" cellspacing="0" style="{$table_style}">
        <tr>
            <td style="{$cell_title_style}">Host</td>
            <td style="{$cell_title_style}">State</td>
            <td style="{$cell_title_style}">Duration</td>
            <td style="{$cell_title_style}">Output</td>
        </tr>
        {foreach from=$host_selected item=host}
        <tr>
            <td style="{$cell_style}">{$host.name}</td>
            <td style="{$cell_style}">{$host.state_str}</td>
            <td style="{$cell_style}">{$host.last_hard_state_change_duration}</td>
            <td style="{$cell_style}">{$host.output|substr:0:255}</td>
        </tr>
        {/foreach}
    </table>
{/if}

{if $service_selected|@count gt 0}
    <table cellpading="0" cellspacing="0" style="{$table_style}">
        <tr>
            <td style="{$cell_title_style}">Host</td>
            <td style="{$cell_title_style}">Service</td>
            <td style="{$cell_title_style}">State</td>
            <td style="{$cell_title_style}">Duration</td>
            <td style="{$cell_title_style}">Output</td>
        </tr>
        {foreach from=$service_selected item=service}
        <tr>
            <td style="{$cell_style}">{$service.host_name}</td>
            <td style="{$cell_style}">{$service.description}</td>
            <td style="{$cell_style}">{$service.state_str}</td>
            <td style="{$cell_style}">{$service.last_hard_state_change_duration}</td>
            <td style="{$cell_style}">{$service.output|substr:0:255}</td>
        </tr>
        {/foreach}
    </table>
{/if}

{assign var="centreon_url" value="localhost"}
{assign var="centreon_username" value="admin"}
{assign var="centreon_token" value="token"}
{assign var="centreon_end" value="`$smarty.now`"}
{assign var="centreon_start" value=$centreon_end-86400}

{if isset($add_graph) && $add_graph == 1}
    {if $service_selected|@count gt 0}
        {foreach from=$service_selected item=service}
            {if $service.num_metrics > 0}
<br /><img src="http://{$centreon_url}/centreon/include/views/graphs/generateGraphs/generateImage.php?username={$centreon_username}&token={$centreon_token}&start={$centreon_start}&end={$centreon_end}&hostname={$service.host_name}&service={$service.description}" />
            {/if}
        {/foreach}
    {/if}
{/if}

</body>
</html>';
        } else {
            $default_body = '
{$user.alias} open ticket at {$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}

{$custom_message}

{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_selected_lists.ihtml" separator=""}

{if $host_selected|@count gt 0}
{foreach from=$host_selected item=host}
Host: {$host.name}
State: {$host.state_str}
Duration: {$host.last_hard_state_change_duration}
Output: {$host.output|substr:0:1024}

{/foreach}
{/if}

{if $service_selected|@count gt 0}
{foreach from=$service_selected item=service}
Host: {$service.host_name}
Service: {$service.description}
State: {$service.state_str}
Duration: {$service.last_hard_state_change_duration}
Output: {$service.output|substr:0:1024}
{/foreach}
{/if}
';
        }

        $this->default_data['clones']['bodyList'] = array(
            array('Name' => 'Default', 'Value' => $default_body, 'Default' => '1'),
        );
    }

    /**
     * Get a form clone value
     *
     * @return a array
     */
    protected function _getCloneValue($uniq_id) {
        $format_values = array();
        if (isset($this->rule_data['clones'][$uniq_id]) && is_array($this->rule_data['clones'][$uniq_id])) {
            foreach ($this->rule_data['clones'][$uniq_id] as $values) {
                $format = array();
                foreach ($values as $label => $value) {
                    $format[$uniq_id . $label . '_#index#'] = $value;
                }
                $format_values[] = $format;
            }
        } else if (isset($this->default_data['clones'][$uniq_id])) {
            foreach ($this->default_data['clones'][$uniq_id] as $values) {
                $format = array();
                foreach ($values as $label => $value) {
                    $format[$uniq_id . $label . '_#index#'] = $value;
                }
                $format_values[] = $format;
            }
        }

        $result = array(
            'clone_values' => json_encode($format_values),
            'clone_count' => count($format_values)
        );

        return $result;
    }

    /**
     * Get a form value
     *
     * @return a string
     */
    protected function _getFormValue($uniq_id) {
        $value = '';
        if (isset($this->rule_data[$uniq_id]) && !is_null($this->rule_data[$uniq_id])) {
            $value = $this->rule_data[$uniq_id];
        } else if (isset($this->default_data[$uniq_id])) {
            $value = $this->default_data[$uniq_id];
        }

        $value = htmlentities($value, ENT_QUOTES);
        return $value;
    }

    protected function _checkLists() {
        $groupList = $this->_getCloneSubmitted('groupList', array('Id', 'Label', 'Type', 'Filter', 'Mandatory'));
        $duplicate_id = array();

        foreach ($groupList as $values) {
            if (preg_match('/[^A-Za-z0-9_]/', $values['Id'])) {
                $this->_check_error_message .= $this->_check_error_message_append . "List id '" . $values['Id'] . "' must contains only alphanumerics or underscore characters";
                $this->_check_error_message_append = '<br/>';
            }
            if (isset($duplicate_id[$values['Id']])) {
                $this->_check_error_message .= $this->_check_error_message_append . "List id '" . $values['Id'] . "' already exits";
                $this->_check_error_message_append = '<br/>';
            }

            $duplicate_id[$values['Id']] = 1;
        }
    }

    protected function _checkFormInteger($uniq_id, $error_msg) {
        if (isset($this->_submitted_config[$uniq_id]) && $this->_submitted_config[$uniq_id] != '' &&
            preg_match('/[^0-9]/', $this->_submitted_config[$uniq_id])) {
            $this->_check_error_message .= $this->_check_error_message_append . $error_msg;
            $this->_check_error_message_append = '<br/>';
        }
    }

    protected function _checkFormValue($uniq_id, $error_msg) {
        if (!isset($this->_submitted_config[$uniq_id]) || $this->_submitted_config[$uniq_id] == '') {
            $this->_check_error_message .= $this->_check_error_message_append . $error_msg;
            $this->_check_error_message_append = '<br/>';
        }
    }

    /**
     * Build the config form
     *
     * @return a array
     */
    public function getConfig() {
        $this->_getConfigContainer1Extra();
        $this->_getConfigContainer1Main();
        $this->_getConfigContainer2Main();
        $this->_getConfigContainer2Extra();

        return $this->_config;
    }

    public function getChainRuleList() {
        $result = array();
        if (isset($this->rule_data['clones']['chainruleList'])) {
            $result = $this->rule_data['clones']['chainruleList'];
        }
        return $result;
    }

    public function getMacroTicketId() {
        return $this->rule_data['macro_ticket_id'];
    }

    /**
     * Build the main config: url, ack, message confirm, lists
     *
     * @return void
     */
    protected function _getConfigContainer1Main() {
        $tpl = $this->initSmartyTemplate();

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("common" => _("Common")));
        $tpl->assign("header", array("close_ticket" => _("Close Ticket")));

        // Form
        $url_html = '<input size="50" name="url" type="text" value="' . $this->_getFormValue('url') . '" />';
        $message_confirm_html = '<textarea rows="8" cols="70" name="message_confirm">' . $this->_getFormValue('message_confirm') . '</textarea>';
        $ack_html = '<input type="checkbox" name="ack" value="yes" ' . ($this->_getFormValue('ack') == 'yes' ? 'checked' : '') . '/>';
        $close_ticket_enable_html = '<input type="checkbox" name="close_ticket_enable" value="yes" ' . ($this->_getFormValue('close_ticket_enable') == 'yes' ? 'checked' : '') . '/>';
        $error_close_centreon_html = '<input type="checkbox" name="error_close_centreon" value="yes" ' . ($this->_getFormValue('error_close_centreon') == 'yes' ? 'checked' : '') . '/>';

        $array_form = array(
            'url' => array('label' => _("Url"), 'html' => $url_html),
            'message_confirm' => array('label' => _("Confirm message popup"), 'html' => $message_confirm_html),
            'ack' => array('label' => _("Acknowledge"), 'html' => $ack_html),
            'close_ticket_enable' => array('label' => _("Enable"), 'enable' => $this->_close_advanced, 'html' => $close_ticket_enable_html),
            'error_close_centreon' => array('label' => _("On error continue close Centreon"), 'html' => $error_close_centreon_html),
            'grouplist' => array('label' => _("Lists")),
            'customlist' => array('label' => _("Custom list definition")),
            'bodylist' => array('label' => _("Body list definition")),
        );

        $extra_group_options = '';

        $method_name = 'getGroupListOptions';
        if (method_exists($this, $method_name)) {
            $extra_group_options = $this->{$method_name}();
        }

        // Group list clone
        $groupListId_html = '<input id="groupListId_#index#" name="groupListId[#index#]" size="20"  type="text" />';
        $groupListLabel_html = '<input id="groupListLabel_#index#" name="groupListLabel[#index#]" size="20"  type="text" />';
        $groupListType_html = '<select id="groupListType_#index#" name="groupListType[#index#]" type="select-one">' .
            $extra_group_options .
        '<option value="' . self::HOSTGROUP_TYPE . '">Host group</options>' .
        '<option value="' . self::HOSTCATEGORY_TYPE . '">Host category</options>' .
        '<option value="' . self::HOSTSEVERITY_TYPE . '">Host severity</options>' .
        '<option value="' . self::SERVICEGROUP_TYPE . '">Service group</options>' .
        '<option value="' . self::SERVICECATEGORY_TYPE . '">Service category</options>' .
        '<option value="' . self::SERVICESEVERITY_TYPE . '">Service severity</options>' .
        '<option value="' . self::SERVICECONTACTGROUP_TYPE . '">Contact group</options>' .
        '<option value="' . self::BODY_TYPE . '">Body</options>' .
        '<option value="' . self::CUSTOM_TYPE . '">Custom</options>' .
        '</select>';
        $groupListFilter_html =  '<input id="groupListFilter_#index#" name="groupListFilter[#index#]" size="20"  type="text" />';
        $groupListMandatory_html =  '<input id="groupListMandatory_#index#" name="groupListMandatory[#index#]" type="checkbox" value="1" />';
        $array_form['groupList'] = array(
            array('label' => _("Id"), 'html' => $groupListId_html),
            array('label' => _("Label"), 'html' => $groupListLabel_html),
            array('label' => _("Type"), 'html' => $groupListType_html),
            array('label' => _("Filter"), 'html' => $groupListFilter_html),
            array('label' => _("Mandatory"), 'html' => $groupListMandatory_html),
        );

        // Custom list clone
        $customListId_html = '<input id="customListId_#index#" name="customListId[#index#]" size="20"  type="text" />';
        $customListValue_html = '<input id="customListValue_#index#" name="customListValue[#index#]" size="20"  type="text" />';
        $customListLabel_html = '<input id="customListLabel_#index#" name="customListLabel[#index#]" size="20"  type="text" />';
        $customListDefault_html =  '<input id="customListDefault_#index#" name="customListDefault[#index#]" type="checkbox" value="1" />';
        $array_form['customList'] = array(
            array('label' => _("Id"), 'html' => $customListId_html),
            array('label' => _("Value"), 'html' => $customListValue_html),
            array('label' => _("Label"), 'html' => $customListLabel_html),
            array('label' => _("Default"), 'html' => $customListDefault_html),
        );

        // Body list clone
        $bodyListName_html = '<input id="bodyListName_#index#" name="bodyListName[#index#]" size="20"  type="text" />';
        $bodyListValue_html = '<textarea type="textarea" id="bodyListValue_#index#" rows="8" cols="70" name="bodyListValue[#index#]"></textarea>';
        $bodyListDefault_html =  '<input id="bodyListDefault_#index#" name="bodyListDefault[#index#]" type="checkbox" value="1" />';
        $array_form['bodyList'] = array(
            array('label' => _("Name"), 'html' => $bodyListName_html),
            array('label' => _("Value"), 'html' => $bodyListValue_html),
            array('label' => _("Default"), 'html' => $bodyListDefault_html),
        );

        $tpl->assign('form', $array_form);
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1main.ihtml');

        $this->_config['clones']['groupList'] = $this->_getCloneValue('groupList');
        $this->_config['clones']['customList'] = $this->_getCloneValue('customList');
        $this->_config['clones']['bodyList'] = $this->_getCloneValue('bodyList');
    }

    /**
     * Build the advanced config: Popup format, Macro name
     *
     * @return void
     */
    protected function _getConfigContainer2Main() {
        $tpl = $this->initSmartyTemplate();

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_wrench", "./modules/centreon-open-tickets/images/wrench.png");
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("proxy_settings" => _("Proxy settings"), "title" => _("Rules"), "common" => _("Common")));
        $tpl->assign("proxy_enabled", $this->_proxy_enabled);

        // Form
        $confirm_autoclose_html = '<input size="5" name="confirm_autoclose" type="text" value="' . $this->_getFormValue('confirm_autoclose') . '" />';
        $macro_ticket_id_html = '<input size="50" name="macro_ticket_id" type="text" value="' . $this->_getFormValue('macro_ticket_id') . '" />';
        $format_popup_html = '<textarea rows="8" cols="70" name="format_popup">' . $this->_getFormValue('format_popup') . '</textarea>';
        $attach_files_html = '<input type="checkbox" name="attach_files" value="yes" ' . ($this->_getFormValue('attach_files') == 'yes' ? 'checked' : '') . '/>';

        //Proxy
        $proxy_address_html = '<input size="50" name="proxy_address" type="text" value="' . $this->_getFormValue('proxy_address') . '" />';
        $proxy_port_html = '<input size="10" name="proxy_port" type="text" value="' . $this->_getFormValue('proxy_port') . '" />';
        $proxy_username_html = '<input size="50" name="proxy_username" type="text" value="' . $this->_getFormValue('proxy_username') . '" />';
        $proxy_password_html = '<input size="50" name="proxy_password" type="password" value="' . $this->_getFormValue('proxy_password') . '" autocomplete="off" />';

        $array_form = array(
            'macro_ticket_id' => array('label' => _("Macro Ticket ID") . $this->_required_field, 'html' => $macro_ticket_id_html),
            'format_popup' => array('label' => _("Formatting popup"), 'html' => $format_popup_html),
            'confirm_autoclose' => array('label' => _("Confirm popup autoclose"), 'html' => $confirm_autoclose_html),
            'chainrule' => array('label' => _("Chain rules")),
            'command' => array('label' => _("Commands")),
            'attach_files' => array('label' => _("Attach Files"), "enable" => $this->_attach_files, 'html' => $attach_files_html),
            'proxy_address' => array('label' => _("Proxy address"), 'html' => $proxy_address_html),
            'proxy_port' => array('label' => _("Proxy port"), 'html' => $proxy_port_html),
            'proxy_username' => array('label' => _("Proxy username"), 'html' => $proxy_username_html),
            'proxy_password' => array('label' => _("Proxy password"), 'html' => $proxy_password_html),
        );

        // Chain rule list clone
        $chainruleListProvider_html = '<select id="chainruleListProvider_#index#" name="chainruleListProvider[#index#]" type="select-one">';
        $chainruleListProvider_html .=  '<option value="-1">-- ' . _('Choose provider') . ' --</options>';
        foreach ($this->rule_list as $id => $name) {
            if ($id != $this->_rule_id) {
                $chainruleListProvider_html .=  '<option value="' . $id . '">' . $name . '</options>';
            }
        }
        $chainruleListProvider_html .= '</select>';
        $array_form['chainruleList'] = array(
            array('label' => _("Provider"), 'html' => $chainruleListProvider_html)
        );

        // Command list clone
        $commandListCmd_html = '<input id="commandListCmd_#index#" name="commandListCmd[#index#]" size="60"  type="text" />';
        $array_form['commandList'] = array(
            array('label' => _("Command"), 'html' => $commandListCmd_html)
        );

        $tpl->assign('form', $array_form);

        $this->_config['container2_html'] .= $tpl->fetch('conf_container2main.ihtml');

        $this->_config['clones']['chainruleList'] = $this->_getCloneValue('chainruleList');
        $this->_config['clones']['commandList'] = $this->_getCloneValue('commandList');
    }

    protected function _getCloneSubmitted($clone_key, $values) {
        $result = array();

        foreach ($this->_submitted_config as $key => $value) {
            if (preg_match('/^clone_order_' . $clone_key . '_(\d+)/', $key, $matches)) {
                $index = $matches[1];
                $array_values = array();
                foreach ($values as $other) {
                    if (isset($this->_submitted_config[$clone_key . $other]) && isset($this->_submitted_config[$clone_key . $other][$index])) {
                        $array_values[$other] = $this->_submitted_config[$clone_key . $other][$index];
                    } else {
                        $array_values[$other] = '';
                    }
                }
                $result[] = $array_values;
            }
        }

        return $result;
    }

    protected function saveConfigMain() {
        $this->_save_config['provider_id'] = $this->_submitted_config['provider_id'];
        $this->_save_config['rule_alias'] = $this->_submitted_config['rule_alias'];
        $this->_save_config['simple']['macro_ticket_id'] = $this->_submitted_config['macro_ticket_id'];
        $this->_save_config['simple']['confirm_autoclose'] = $this->_submitted_config['confirm_autoclose'];
        $this->_save_config['simple']['ack'] = (isset($this->_submitted_config['ack']) && $this->_submitted_config['ack'] == 'yes') ?
            $this->_submitted_config['ack'] : '';
        $this->_save_config['simple']['attach_files'] = (isset($this->_submitted_config['attach_files']) && $this->_submitted_config['attach_files'] == 'yes') ?
            $this->_submitted_config['attach_files'] : '';
        $this->_save_config['simple']['close_ticket_enable'] = (isset($this->_submitted_config['close_ticket_enable']) && $this->_submitted_config['close_ticket_enable'] == 'yes') ?
            $this->_submitted_config['close_ticket_enable'] : '';
        $this->_save_config['simple']['error_close_centreon'] = (isset($this->_submitted_config['error_close_centreon']) && $this->_submitted_config['error_close_centreon'] == 'yes') ?
            $this->_submitted_config['error_close_centreon'] : '';
        $this->_save_config['simple']['url'] = $this->_submitted_config['url'];
        $this->_save_config['simple']['format_popup'] = $this->_submitted_config['format_popup'];
        $this->_save_config['simple']['message_confirm'] = $this->_submitted_config['message_confirm'];

        $this->_save_config['clones']['groupList'] = $this->_getCloneSubmitted('groupList', array('Id', 'Label', 'Type', 'Filter', 'Mandatory'));
        $this->_save_config['clones']['customList'] = $this->_getCloneSubmitted('customList', array('Id', 'Value', 'Label', 'Default'));
        $this->_save_config['clones']['bodyList'] = $this->_getCloneSubmitted('bodyList', array('Name', 'Value', 'Default'));
        $this->_save_config['clones']['chainruleList'] = $this->_getCloneSubmitted('chainruleList', array('Provider'));
        $this->_save_config['clones']['commandList'] = $this->_getCloneSubmitted('commandList', array('Cmd'));

        $this->_save_config['simple']['proxy_address'] = isset($this->_submitted_config['proxy_address']) ? $this->_submitted_config['proxy_address'] : '';
        $this->_save_config['simple']['proxy_port'] = isset($this->_submitted_config['proxy_port']) ? $this->_submitted_config['proxy_port'] : '';
        $this->_save_config['simple']['proxy_username'] = isset($this->_submitted_config['proxy_username']) ? $this->_submitted_config['proxy_username'] : '';
        $this->_save_config['simple']['proxy_password'] = isset($this->_submitted_config['proxy_password']) ? $this->_submitted_config['proxy_password'] : '';
    }

    public function saveConfig() {
        $this->_checkConfigForm();
        $this->_save_config = array('clones' => array(), 'simple' => array());

        $this->saveConfigMain();
        $this->saveConfigExtra();

        $this->_rule->save($this->_rule_id, $this->_save_config);
    }

    protected function assignHostgroup($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getHostgroup($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignHostcategory($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getHostcategory($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignHostseverity($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getHostseverity($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignServicegroup($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getServicegroup($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignServicecategory($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getServicecategory($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignServiceseverity($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getServiceseverity($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignContactgroup($entry, &$groups_order, &$groups) {
        $result = $this->_rule->getContactgroup($entry['Filter']);
        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result);
        $groups_order[] = $entry['Id'];
    }

    protected function assignCustom($entry, &$groups_order, &$groups) {
        $result = array();
        $default = '';
        if (isset($this->rule_data['clones']['customList'])) {
            foreach ($this->rule_data['clones']['customList'] as $values) {
                if (isset($entry['Id']) && $entry['Id'] != '' &&
                    isset($values['Id']) && $values['Id'] != '' && $values['Id'] == $entry['Id']) {
                    $result[] = $values['Value'];
                    $placeholder[] = $values['Label'];
                    if (isset($values['Default']) && $values['Default']) {
                        $default = $values['Value'];
                    }
                }
            }
        }

        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result,
                                      'placeholder' => $placeholder,
                                      'default' => $default);
        $groups_order[] = $entry['Id'];
    }

    protected function assignBody($entry, &$groups_order, &$groups) {
        $result = array();
        $default = '';
        if (isset($this->rule_data['clones']['bodyList'])) {
            foreach ($this->rule_data['clones']['bodyList'] as $values) {
                if (isset($entry['Id']) && $entry['Id'] != '' &&
                    isset($values['Name']) && $values['Name'] != '') {
                    $result[] = $values['Name'];
                    if (isset($values['Default']) && $values['Default']) {
                        $default = $values['Name'];
                    }
                }
            }
        }

        $groups[$entry['Id']] = array('label' => _($entry['Label']) .
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''),
                                      'values' => $result,
                                      'default' => $default);
        $groups_order[] = $entry['Id'];
    }

    protected function assignFormatPopupTemplate(&$tpl, $args) {
        foreach ($args as $label => $value) {
            $tpl->assign($label, $value);
        }

        $this->clearSession();

        $groups_order = array();
        $groups = array();
        $tpl->assign('custom_message', array('label' => _('Custom message')));
        $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);

        if (isset($this->rule_data['clones']['groupList'])) {
            foreach ($this->rule_data['clones']['groupList'] as $values) {
                if ($values['Type'] == self::HOSTGROUP_TYPE) {
                    $this->assignHostgroup($values, $groups_order, $groups);
                } else if ($values['Type'] == self::HOSTCATEGORY_TYPE) {
                    $this->assignHostcategory($values, $groups_order, $groups);
                } else if ($values['Type'] == self::HOSTSEVERITY_TYPE) {
                    $this->assignHostseverity($values, $groups_order, $groups);
                } else if ($values['Type'] == self::SERVICEGROUP_TYPE) {
                    $this->assignServicegroup($values, $groups_order, $groups);
                } else if ($values['Type'] == self::SERVICECATEGORY_TYPE) {
                    $this->assignServicecategory($values, $groups_order, $groups);
                } else if ($values['Type'] == self::SERVICESEVERITY_TYPE) {
                    $this->assignServiceseverity($values, $groups_order, $groups);
                } else if ($values['Type'] == self::SERVICECONTACTGROUP_TYPE) {
                    $this->assignContactgroup($values, $groups_order, $groups);
                } else if ($values['Type'] == self::CUSTOM_TYPE) {
                    $this->assignCustom($values, $groups_order, $groups);
                } else if ($values['Type'] == self::BODY_TYPE) {
                    $this->assignBody($values, $groups_order, $groups);
                } else {
                    $method_name = 'assignOthers';
                    if (method_exists($this, $method_name)) {
                        $this->{$method_name}($values, $groups_order, $groups);
                    }
                }
            }
        }

        $tpl->assign('groups_order', $groups_order);
        $tpl->assign('groups', $groups);
    }

    protected function validateFormatPopupLists(&$result) {
        if (isset($this->rule_data['clones']['groupList'])) {
            foreach ($this->rule_data['clones']['groupList'] as $values) {
                if ($values['Mandatory'] == 1 && isset($this->_submitted_config['select_' . $values['Id']]) &&
                    $this->_submitted_config['select_' . $values['Id']] == '-1') {
                    $result['code'] = 1;
                    $result['message'] = 'Please select ' . $values['Label'];
                }
            }
        }
    }

    public function getFormatPopup($args) {
        if (!isset($this->rule_data['format_popup']) || is_null($this->rule_data['format_popup']) || $this->rule_data['format_popup']  == '') {
            return null;
        }

        $result = array('format_popup' => null);

        $tpl = $this->initSmartyTemplate();

        $this->assignFormatPopupTemplate($tpl, $args);
        $tpl->assign('string', $this->rule_data['format_popup']);
        $result['format_popup'] = $tpl->fetch('eval.ihtml');
        $result['attach_files_enable'] = isset($this->rule_data['attach_files']) ? $this->rule_data['attach_files'] : 0;
        return $result;
    }

    public function doAck() {
        if (isset($this->rule_data['ack']) && $this->rule_data['ack'] == 'yes') {
            return 1;
        }

        return 0;
    }

    public function doCloseTicket() {
        if (isset($this->rule_data['close_ticket_enable']) && $this->rule_data['close_ticket_enable'] == 'yes') {
            return 1;
        }

        return 0;
    }

    public function doCloseTicketContinueOnError() {
        if (isset($this->rule_data['error_close_centreon']) && $this->rule_data['error_close_centreon'] == 'yes') {
            return 1;
        }

        return 0;
    }

    protected function assignSubmittedValues(&$tpl) {
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);

        foreach ($this->_submitted_config as $label => $value) {
            if (!preg_match('/^select_/', $label)) {
                $tpl->assign($label, $value);
            }
        }

        $body = null;
        $method_name = 'assignSubmittedValuesSelectMore';
        $select_lists = array();
        if (isset($this->rule_data['clones']['groupList'])) {
            foreach ($this->rule_data['clones']['groupList'] as $values) {
                // Maybe an error to get list
                if ($values['Type'] == self::BODY_TYPE || !isset($this->_submitted_config['select_' . $values['Id']])) {
                    continue;
                }

                $id = '-1';
                $value = '';
                $matches = array();
                if (preg_match('/^(.*?)___(.*?)___(.*)$/', $this->_submitted_config['select_' . $values['Id']], $matches)) {
                    $id = $matches[1];
                    $value = $matches[2];
                    $placeholder = $matches[3];
                } elseif (preg_match('/^(.*?)_(.*)$/', $this->_submitted_config['select_' . $values['Id']], $matches)) {
                    $id = $matches[1];
                    $value = $matches[2];
                }
                if (!empty($placeholder) && isset($placeholder)) {
                    $select_lists[$values['Id']] = array('label' => _($values['Label']), 'id' => $id, 'value' => $value, 'placeholder' => $placeholder);
                } else {
                    $select_lists[$values['Id']] = array('label' => _($values['Label']), 'id' => $id, 'value' => $value);
                }
                if (method_exists($this, $method_name)) {
                    $more_attributes = $this->{$method_name}($values['Id'], $id);
                    $select_lists[$values['Id']] = array_merge($select_lists[$values['Id']], $more_attributes);
                }
            }
        }

        $tpl->assign('select', $select_lists);

        # Manage body
        $body_lists = array();
        if (isset($this->rule_data['clones']['groupList'])) {
            foreach ($this->rule_data['clones']['groupList'] as $values) {
                if ($values['Type'] != self::BODY_TYPE || !isset($this->_submitted_config['select_' . $values['Id']])) {
                    continue;
                }

                $id = '-1';
                $value = '';
                $matches = array();
                if (preg_match('/^(.*?)_(.*)$/', $this->_submitted_config['select_' . $values['Id']], $matches)) {
                    $id = $matches[1];
                    $value = $matches[2];
                }

                // body list
                $value_body = '';
                foreach ($this->rule_data['clones']['bodyList'] as $body_entry) {
                    if ($body_entry['Name'] == $value) {
                        $value_body = $body_entry['Value'];
                        $body = $body_entry['Value'];
                        break;
                    }
                }

                $tpl->assign('string', $value_body);
                $content = $tpl->fetch('eval.ihtml');
                $body_lists[$values['Id']] = array('label' => _($values['Label']), 'id' => $id, 'name' => $value, 'value' => $content);
            }
        }

        # We reassign
        $tpl->assign('list_body', $body_lists);

        # if no submitted value, we set the default body (compatibility)
        if (is_null($body)) {
            $body = '';
            foreach ($this->rule_data['clones']['bodyList'] as $body_entry) {
                if ($body_entry['Default'] == 1) {
                    $body = $body_entry['Value'];
                    break;
                }
            }
        }

        # We assign the default body
        $tpl->assign('string', $body);
        $content = $tpl->fetch('eval.ihtml');
        $tpl->assign('body', $content);
        $this->body = $content;
    }

    protected function setConfirmMessage($host_problems, $service_problems, $submit_result) {
        if (!isset($this->rule_data['message_confirm']) || is_null($this->rule_data['message_confirm']) || $this->rule_data['message_confirm']  == '') {
            return null;
        }

        $tpl = $this->initSmartyTemplate();

        $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);

        foreach ($submit_result as $label => $value) {
            $tpl->assign($label, $value);
        }
        foreach ($this->_submitted_config as $label => $value) {
            $tpl->assign($label, $value);
        }

        $tpl->assign('string', $this->rule_data['message_confirm']);
        return $tpl->fetch('eval.ihtml');
    }

    private function ExecWaitTimeout($cmd, $timeout=10) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $pipes = array();

        $timeout += time();
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("proc_open failed on: " . $cmd);
        }

        $output = '';
        do {
            $timeleft = $timeout - time();
            $read = array($pipes[1]);
            stream_select($read, $write = NULL, $exeptions = NULL, $timeleft, NULL);

            if (!empty($read)) {
                $output .= fread($pipes[1], 8192);
            }
        } while (!feof($pipes[1]) && $timeleft > 0);

        if ($timeleft <= 0) {
            proc_terminate($process);
            throw new Exception("command timeout on: " . $cmd);
        } else {
            return $output;
        }
    }

    protected function executeCmd($host_problems, $service_problems, &$submit_result) {
        $submit_result['commands'] = array();

        if (!isset($this->rule_data['clones']['commandList'])) {
            return 0;
        }

        $tpl = $this->initSmartyTemplate();
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        foreach ($submit_result as $label => $value) {
            $tpl->assign($label, $value);
        }
        foreach ($this->_submitted_config as $label => $value) {
            $tpl->assign($label, $value);
        }

        foreach ($this->rule_data['clones']['commandList'] as $cmd) {
            $output = '';
            $error = '';
            try {
                $tpl->assign('string', $cmd['Cmd']);
                $cmd_exec = $tpl->fetch('eval.ihtml');
                $output = $this->ExecWaitTimeout($cmd_exec);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }

            $submit_result['commands'][] = array('output' => $output, 'error' => $error);
        }
    }

    public function submitTicket($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('confirm_popup' => null);

        $submit_result = $this->doSubmit($db_storage, $contact, $host_problems, $service_problems);
        if ($submit_result['ticket_is_ok'] == 1) {
            $this->executeCmd($host_problems, $service_problems, $submit_result);
        }
        $result['confirm_message'] = $this->setConfirmMessage($host_problems, $service_problems, $submit_result);
        $result['ticket_id'] = $submit_result['ticket_id'];
        $result['ticket_is_ok'] = $submit_result['ticket_is_ok'];
        $result['ticket_time'] = $submit_result['ticket_time'];
        $result['confirm_autoclose'] = $this->rule_data['confirm_autoclose'];

        return $result;
    }

    public function getUrl($ticket_id, $data) {
        $tpl = $this->initSmartyTemplate();
        foreach ($data as $label => $value) {
            $tpl->assign($label, $value);
        }

        foreach ($this->rule_data as $label => $value) {
            $tpl->assign($label, $value);
        }
        $tpl->assign('ticket_id', $ticket_id);
        $tpl->assign('string', $this->rule_data['url']);

        return $tpl->fetch('eval.ihtml');
    }

    protected function saveHistory($db_storage, &$result,
                                   $extra_args=array()) {
        $default_values = array('contact' => '', 'host_problems' => array(), 'service_problems' => array(),
                                'ticket_value' => null, 'subject' => null, 'data_type' => null, 'data' => null,
                                'no_create_ticket_id' => false);
        foreach ($default_values as $k => $v) {
            if (!isset($extra_args[$k])) {
                $extra_args[$k] = $v;
            }
        }

        try {
            $db_storage->beginTransaction();

            if ($extra_args['no_create_ticket_id'] == false) {
                $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`" . (is_null($extra_args['ticket_value']) ? "" : ", `ticket_value`") . ")
  VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($extra_args['contact']['name']) . "'" . (is_null($extra_args['ticket_value']) ? "" : ", '" . $db_storage->escape($extra_args['ticket_value']) . "'") . ")";
                $db_storage->query($query);
                $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
            }

            if (is_null($extra_args['ticket_value'])) {
                $query = "UPDATE mod_open_tickets SET `ticket_value` = '" . $db_storage->escape($result['ticket_id']) . "'
    WHERE `ticket_id` = '" . $db_storage->escape($result['ticket_id']) . "'";
                $db_storage->query($query);
            }

            foreach ($extra_args['host_problems'] as $row) {
                $db_storage->query("INSERT INTO mod_open_tickets_link (`ticket_id`, `host_id`, `host_state`, `hostname`) VALUES
    ('" . $db_storage->escape($result['ticket_id']) . "', '" . $db_storage->escape($row['host_id']) . "', '" . $db_storage->escape($row['host_state']) . "', '" . $db_storage->escape($row['name']) . "')");
            }
            foreach ($extra_args['service_problems'] as $row) {
                $db_storage->query("INSERT INTO mod_open_tickets_link (`ticket_id`, `host_id`, `host_state`, `hostname`, `service_id`, `service_state`, `service_description`) VALUES
    ('" . $db_storage->escape($result['ticket_id']) . "', '" . $db_storage->escape($row['host_id']) . "', '" . $db_storage->escape($row['host_state']) . "', '" . $db_storage->escape($row['host_name']) . "', '" . $db_storage->escape($row['service_id']) . "', '" . $db_storage->escape($row['service_state']) . "', '" . $db_storage->escape($row['description']) . "')");
            }

            if (!is_null($extra_args['data_type']) && !is_null($extra_args['data'])) {
                $db_storage->query("INSERT INTO mod_open_tickets_data (`ticket_id`, `subject`, `data_type`, `data`) VALUES
    ('" . $db_storage->escape($result['ticket_id']) . "', '" . $db_storage->escape($extra_args['subject']) . "', '" . $db_storage->escape($extra_args['data_type']) . "', '" . $db_storage->escape($extra_args['data']) . "')");
            }

            $result['ticket_id'] = is_null($extra_args['ticket_value']) ? $result['ticket_id'] : $extra_args['ticket_value'];
            $result['ticket_is_ok'] = 1;
            $db_storage->commit();
        } catch (Exception $e) {
            $db_storage->rollback();
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }
    }

    public function closeTicket(&$tickets) {
        // By default, yes tickets are removed (even no). -1 means a error
        foreach ($tickets as $k => $v) {
            $tickets[$k]['status'] = 1;
        }
    }

    /**
    * Add a value to the cache
    *
    * @param string $key The cache key name
    * @param mixed $value The value to cache
    * @param int|null $ttl The ttl of expire this cache, if it's null no expire
    */
    protected function setCache($key, $value, $ttl = null) {
        $_SESSION['ot_cache_' . $this->_rule_id][$key] =  array(
            'value' => $value,
            'ttl' => $ttl,
            'created' => time()
        );
    }

    /**
     * Get a cache value
     *
     * @param string $key The cache key name
     * @return mixed The cache value or null if not found or expired
     */
    protected function getCache($key) {
        if (!isset($_SESSION['ot_cache_' . $this->_rule_id][$key])) {
            return null;
        }
        if (!is_null())
        $cacheJson = file_get_contents($cacheFile);
        $cache = json_decode($cacheJson, true);
        if (!is_null($_SESSION['ot_cache_' . $this->_rule_id][$key]['ttl'])) {
            $timeTtl = $_SESSION['ot_cache_' . $this->_rule_id][$key]['ttl'] + $_SESSION['ot_cache_' . $this->_rule_id][$key]['created'];
            if ($timeTtl < time()) {
                unlink($_SESSION['ot_cache_' . $this->_rule_id][$key]);
                return null;
            }
        }
        return $_SESSION['ot_cache_' . $this->_rule_id][$key]['value'];
    }

    static protected function setProxy(&$ch, $info) {
        if (is_null($info['proxy_address']) || !isset($info['proxy_address']) || $info['proxy_address'] == '') {
            return 1;
        }

        curl_setopt($ch, CURLOPT_PROXY, $info['proxy_address']);
        if (!is_null($info['proxy_port']) && isset($info['proxy_port']) && $info['proxy_port'] != '') {
            curl_setopt($ch, CURLOPT_PROXYPORT, $info['proxy_port']);
        }
        if (!is_null($info['proxy_username']) && isset($info['proxy_username']) && $info['proxy_username'] != '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $info['proxy_username'] . ':' . $info['proxy_password']);
        }

        return 0;
    }
}
