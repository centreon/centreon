<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
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

abstract class AbstractProvider {
    abstract protected function _setDefaultValueExtra(); 
    abstract protected function _checkConfigForm();
    abstract protected function _getConfigContainer1Extra();
    abstract protected function _getConfigContainer2Extra();
    abstract protected function saveConfigExtra();
    abstract public function validateFormatPopup();
    abstract protected function doSubmit($db_storage, $user, $host_problems, $service_problems);
    
    protected $_rule;
    protected $_rule_id;
    protected $_centreon_path;
    protected $_centreon_open_tickets_path;
    protected $_config = array("container1_html" => '', "container2_html" => '', "clones" => array());
    protected $_required_field = '&nbsp;<font color="red" size="1">*</font>';
    protected $_submitted_config = null;
    protected $_check_error_message = '';
    protected $_save_config = array();
    
    /**
     * constructor
     *
     * @return void
     */
    public function __construct($rule, $centreon_path, $centreon_open_tickets_path, $rule_id, $submitted_config = null) {
        $this->_rule = $rule;
        $this->_centreon_path = $centreon_path;
        $this->_centreon_open_tickets_path = $centreon_open_tickets_path;
        $this->_rule_id = $rule_id;
        $this->_submitted_config = $submitted_config;
        $this->rule_data = $rule->get($rule_id);
        
        $this->default_data = array();
        $this->default_data['clones'] = array();
        $this->_setDefaultValueMain();
        $this->_setDefaultValueExtra();
    }
    
    protected function change_html_tags($output, $change=1) {
        if ($change == 1) {
            $output = str_replace('<', '&lt;', $output);
            $output = str_replace('>', '&gt;', $output);
        } else {
            $output = str_replace('&lt;', '<', $output);
            $output = str_replace('&gt;', '>', $output);
        }
        return $output;
    }
    
    protected function _setDefaultValueMain() {
        $this->default_data['macro_ticket_id'] = 'TICKET_ID';
        $this->default_data['macro_ticket_time'] = 'TICKET_TIME';
        $this->default_data['ack'] = 'yes';
        
        $this->default_data['format_popup'] = '
<table class="table">
<tr class="ListHeader">
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
<tr>
    <td class="FormRowField" style="padding-left:15px;">{$custom_message.label}</td>
    <td class="FormRowValue" style="padding-left:15px;"><textarea id="custom_message" name="custom_message" cols="30" rows="3"></textarea></td>
</tr>
{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
</table>
';
        $this->default_data['message_confirm'] = '
<table class="table">
<tr class="ListHeader">
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
{if $ticket_is_ok == 1}
    <tr><td class="FormRowField" style="padding-left:15px;">New ticket opened: {$ticket_id}.</td></tr>
{else}
    <tr><td class="FormRowField" style="padding-left:15px;">Error to open the ticket: {$ticket_error_message}.</td></tr>
{/if}
</table>
';
        $this->default_data['format_popup'] = $this->change_html_tags($this->default_data['format_popup']);
        $this->default_data['message_confirm'] = $this->change_html_tags($this->default_data['message_confirm']);        
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
        
        return $value;
    }
    
    protected function _checkLists() {
        $groupList = $this->_getCloneSubmitted('groupList', array('Id', 'Label', 'Type', 'Filter', 'Mandatory'));
        
        foreach ($groupList as $values) {
            if (preg_match('/[^A-Za-z0-9_]/', $values['Id'])) {
                $this->_check_error_message .= $this->_check_error_message_append . "List id '" . $values['Id'] . "' must contains only alphanumerics or underscore characters";
                $this->_check_error_message_append = '<br/>';
            }
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
    
    public function getMacroTicketId() {
        return $this->rule_data['macro_ticket_id'];
    }
    
    public function getMacroTicketTime() {
        return $this->rule_data['macro_ticket_time'];
    }
    
    /**
     * Build the main config: url, ack, message confirm, lists
     *
     * @return void
     */
    protected function _getConfigContainer1Main() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("common" => _("Common")));
        
        // Form
        $url_html = '<input size="50" name="url" type="text" value="' . $this->_getFormValue('url') . '" />';
        $message_confirm_html = '<textarea rows="8" cols="70" name="message_confirm">' . $this->_getFormValue('message_confirm') . '</textarea>';
        $ack_html = '<input type="checkbox" name="ack" value="yes" ' . ($this->_getFormValue('ack') == 'yes' ? 'checked' : '') . '/>';

        $array_form = array(
            'url' => array('label' => _("Url"), 'html' => $url_html),
            'message_confirm' => array('label' => _("Confirm message popup"), 'html' => $message_confirm_html),
            'ack' => array('label' => _("Acknowledge"), 'html' => $ack_html),
            'grouplist' => array('label' => _("Lists")),
            'customlist' => array('label' => _("Custom list definition")),
        );
        
        // Group list clone
        $groupListId_html = '<input id="groupListId_#index#" name="groupListId[#index#]" size="20"  type="text" />';
        $groupListLabel_html = '<input id="groupListLabel_#index#" name="groupListLabel[#index#]" size="20"  type="text" />';
        $groupListType_html = '<select id="groupListType_#index#" name="groupListType[#index#]" type="select-one">' .
        '<option value="0">Host group</options>' .
        '<option value="1">Host category</options>' .
        '<option value="2">Host severity</options>' .
        '<option value="3">Service group</options>' .
        '<option value="4">Service category</options>' .
        '<option value="5">Service severity</options>' .
        '<option value="6">Contact group</options>' .
        '<option value="7">Custom</options>' .
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
        $customListDefault_html =  '<input id="customListDefault_#index#" name="customListDefault[#index#]" type="checkbox" value="1" />';
        $array_form['customList'] = array(
            array('label' => _("Id"), 'html' => $customListId_html),
            array('label' => _("Value"), 'html' => $customListValue_html),
            array('label' => _("Default"), 'html' => $customListDefault_html),
        );
        
        $tpl->assign('form', $array_form);
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1main.ihtml');
        
        $this->_config['clones']['groupList'] = $this->_getCloneValue('groupList');
        $this->_config['clones']['customList'] = $this->_getCloneValue('customList');
    }
    
    /**
     * Build the advanced config: Popup format, Macro name
     *
     * @return void
     */
    protected function _getConfigContainer2Main() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign("img_wrench", "./modules/centreon-open-tickets/images/wrench.png");
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("title" => _("Rules"), "common" => _("Common")));
        
        // Form
        $macro_ticket_id_html = '<input size="50" name="macro_ticket_id" type="text" value="' . $this->_getFormValue('macro_ticket_id') . '" />';
        $macro_ticket_time_html = '<input size="50" name="macro_ticket_time" type="text" value="' . $this->_getFormValue('macro_ticket_time') . '" />';
        $format_popup_html = '<textarea rows="8" cols="70" name="format_popup">' . $this->_getFormValue('format_popup') . '</textarea>';

        $array_form = array(
            'macro_ticket_id' => array('label' => _("Macro Ticket ID") . $this->_required_field, 'html' => $macro_ticket_id_html),
            'macro_ticket_time' => array('label' => _("Macro Ticket Time") . $this->_required_field, 'html' => $macro_ticket_time_html),
            'format_popup' => array('label' => _("Formatting popup"), 'html' => $format_popup_html)
        );
        $tpl->assign('form', $array_form);
        
        $this->_config['container2_html'] .= $tpl->fetch('conf_container2main.ihtml');
    }
    
    protected function _getCloneSubmitted($clone_key, $values) {
        $result = array();
        
        foreach ($this->_submitted_config as $key => $value) {   
            if (preg_match('/^clone_order_' . $clone_key . '_(\d+)/', $key, $matches)) {
                $index = $matches[1];
                $array_values = array();
                foreach ($values as $other) {
                    $array_values[$other] = $this->_submitted_config[$clone_key . $other][$index];
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
        $this->_save_config['simple']['macro_ticket_time'] = $this->_submitted_config['macro_ticket_time'];
        $this->_save_config['simple']['ack'] = (isset($this->_submitted_config['ack']) && $this->_submitted_config['ack'] == 'yes') ? 
            $this->_submitted_config['ack'] : '';
        $this->_save_config['simple']['url'] = $this->_submitted_config['url'];
        $this->_save_config['simple']['format_popup'] = $this->change_html_tags($this->_submitted_config['format_popup']);
        $this->_save_config['simple']['message_confirm'] = $this->change_html_tags($this->_submitted_config['message_confirm']);
        
        $this->_save_config['clones']['groupList'] = $this->_getCloneSubmitted('groupList', array('Id', 'Label', 'Type', 'Filter', 'Mandatory'));
        $this->_save_config['clones']['customList'] = $this->_getCloneSubmitted('customList', array('Id', 'Value', 'Default'));
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
                    if (isset($values['Default']) && $values['Default']) {
                        $default = $values['Value'];
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
        
        $groups_order = array();
        $groups = array();
        $tpl->assign('custom_message', array('label' => _('Custom message')));
        $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);
        
        if (isset($this->rule_data['clones']['groupList'])) {
            foreach ($this->rule_data['clones']['groupList'] as $values) {
                if ($values['Type'] == 0) {
                    $this->assignHostgroup($values, $groups_order, $groups);
                } else if ($values['Type'] == 1) {
                    $this->assignHostcategory($values, $groups_order, $groups);
                } else if ($values['Type'] == 2) {
                    $this->assignHostseverity($values, $groups_order, $groups);
                } else if ($values['Type'] == 3) {
                    $this->assignServicegroup($values, $groups_order, $groups);
                } else if ($values['Type'] == 4) {
                    $this->assignServicecategory($values, $groups_order, $groups);
                } else if ($values['Type'] == 5) {
                    $this->assignServiceseverity($values, $groups_order, $groups);
                } else if ($values['Type'] == 6) {
                    $this->assignContactgroup($values, $groups_order, $groups);
                } else if ($values['Type'] == 7) {
                    $this->assignCustom($values, $groups_order, $groups);
                }
            }
        }
        
        $tpl->assign('groups_order', $groups_order);
        $tpl->assign('groups', $groups);
    }
    
    protected function validateFormatPopupLists(&$result) {
        //$fp = fopen('/tmp/debug.txt', 'a+');
        //fwrite($fp, print_r($this->rule_data, true));
        //fwrite($fp, "================\n");
        //fwrite($fp, print_r($this->_submitted_config, true));
        
        $result['code'] = 1;
    }
    
    public function getFormatPopup($args) {        
        if (!isset($this->rule_data['format_popup']) || is_null($this->rule_data['format_popup']) || $this->rule_data['format_popup']  == '') {
            return null;
        }
        
        $result = array('format_popup' => null);
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $this->assignFormatPopupTemplate($tpl, $args);
        $tpl->assign('string', $this->change_html_tags($this->rule_data['format_popup'], 0));
        $result['format_popup'] = $tpl->fetch('eval.ihtml');
        return $result;
    }
    
    public function doAck() {
        if (isset($this->rule_data['ack']) && $this->rule_data['ack'] == 'yes') {
            return 1;
        }
        
        return 0;
    }
    
    protected function setConfirmMessage($host_problems, $service_problems, $submit_result) {
        if (!isset($this->rule_data['format_popup']) || is_null($this->rule_data['format_popup']) || $this->rule_data['format_popup']  == '') {
            return null;
        }
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        
        foreach ($submit_result as $label => $value) {
            $tpl->assign($label, $value);
        }
        foreach ($this->_submitted_config as $label => $value) {
            $tpl->assign($label, $value);
        }
        
        $tpl->assign('string', $this->change_html_tags($this->rule_data['message_confirm'], 0));
        return $tpl->fetch('eval.ihtml');
    }
    
    public function submitTicket($db_storage, $user, $host_problems, $service_problems) {
        $result = array('confirm_popup' => null);
        
        $submit_result = $this->doSubmit($db_storage, $user, $host_problems, $service_problems);
        $result['confirm_message'] = $this->setConfirmMessage($host_problems, $service_problems, $submit_result);
        $result['ticket_id'] = $submit_result['ticket_id'];
        $result['ticket_is_ok'] = $submit_result['ticket_is_ok'];
        $result['ticket_time'] = $submit_result['ticket_time'];
        return $result;
    }
}
