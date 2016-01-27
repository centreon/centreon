<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/

class MailProvider {
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
<table id="ListTable" style="width: 100%;">
<tr>
    <th colspan="2">{$title}</th>
</tr>
<tr>
    <td>{$custom_message.label}</td>
    <td><textarea id="custom_message" name="custom_message" cols="30" rows="3"></textarea></td>
</tr>
</table>
';
        $this->default_data['message_confirm'] = '
<table id="ListTable" style="width: 100%;">
<tr>
    <th>{$title}</th>
</tr>
{if $ticket_is_ok == 1}
    <tr><td>New ticket opened: {$ticket_id}.</td></tr>
{else}
    <tr><td>Error to open the ticket: {$ticket_error_message}.</td></tr>
{/if}
</table>
';
        $this->default_data['format_popup'] = $this->change_html_tags($this->default_data['format_popup']);
        $this->default_data['message_confirm'] = $this->change_html_tags($this->default_data['message_confirm']);
                
        //$this->default_data['clones'] = array();
        //$this->default_data['clones']['headerMail'] = array(
        //    array('Name' => 'test', 'Value' => 'test'),
        //    array('Name' => 'test2', 'Value' => 'test2')
        //);
    }
    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['from'] = 'admin@domain.com';
        $this->default_data['subject'] = 'Open a ticket';
        $this->default_data['body'] = '
{$user} open ticket at {$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}

{$custom_message}

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
            <td style="{$cell_style}">{$host.state}</td>
            <td style="{$cell_style}">{$host.duration}</td>
            <td style="{$cell_style}">{$host.short_output}</td>
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
            <td style="{$cell_style}">{$service.state}</td>
            <td style="{$cell_style}">{$service.duration}</td>
            <td style="{$cell_style}">{$service.short_output}</td>
        </tr>
        {/foreach}
    </table>
{/if}

        ';
        
        $this->default_data['body'] = $this->change_html_tags($this->default_data['body']);
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
    
    protected function _checkFormValue($uniq_id, $error_msg) {
        if (!isset($this->_submitted_config[$uniq_id]) || $this->_submitted_config[$uniq_id] == '') {
            $this->_check_error_message .= $this->_check_error_message_append . $error_msg;
            $this->_check_error_message_append = '<br/>';
        }
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('from', "Please set 'From' value");
        $this->_checkFormValue('to', "Please set 'To' value");
        $this->_checkFormValue('subject', "Please set 'Subject' value");
        $this->_checkFormValue('body', "Please set 'Body' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormValue('macro_ticket_time', "Please set 'Macro Ticket Time' value");
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
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
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Mail/templates', $this->_centreon_path);
        
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
            'ack' => array('label' => _("Acknowledge"), 'html' => $ack_html)
        );
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1main.ihtml');
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Mail/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("mail" => _("Mail")));
        
        // Form
        $from_html = '<input size="50" name="from" type="text" value="' . $this->_getFormValue('from') . '" />';
        $to_html = '<input size="50" name="to" type="text" value="' . $this->_getFormValue('to') . '" />';
        $subject_html = '<input size="50" name="subject" type="text" value="' . $this->_getFormValue('subject') . '" />';
        $body_html = '<textarea rows="8" cols="70" name="body">' . $this->_getFormValue('body') . '</textarea>';

        $array_form = array(
            'from' => array('label' => _("From") . $this->_required_field, 'html' => $from_html),
            'to' => array('label' => _("To") . $this->_required_field, 'html' => $to_html),
            'subject' => array('label' => _("Subject") . $this->_required_field, 'html' => $subject_html),
            'header' => array('label' => _("Headers")),
            'body' => array('label' => _("Body") . $this->_required_field, 'html' => $body_html)
        );
        
        // Clone part
        $headerMailName_html = '<input id="headerMailName_#index#" size="20" name="headerMailName[#index#]" type="text" />';
        $headerMailValue_html = '<input id="headerMailValue_#index#" size="20" name="headerMailValue[#index#]" type="text" />';
        $array_form['headerMail'] = array(
            array('label' => _("Name"), 'html' => $headerMailName_html),
            array('label' => _("Value"), 'html' => $headerMailValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['headerMail'] = $this->_getCloneValue('headerMail');
    }
    
    /**
     * Build the advanced config: Popup format, Macro name
     *
     * @return void
     */
    protected function _getConfigContainer2Main() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Mail/templates', $this->_centreon_path);
        
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
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
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
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['clones']['headerMail'] = $this->_getCloneSubmitted('headerMail', array('Name', 'Value'));
        $this->_save_config['simple']['from'] = $this->_submitted_config['from'];
        $this->_save_config['simple']['to'] = $this->_submitted_config['to'];
        $this->_save_config['simple']['subject'] = $this->_submitted_config['subject'];
        $this->_save_config['simple']['body'] = $this->change_html_tags($this->_submitted_config['body']);
    }
    
    public function saveConfig() {
        $this->_checkConfigForm();
        $this->_save_config = array('clones' => array(), 'simple' => array());
        
        $this->saveConfigMain();
        $this->saveConfigExtra();
        
        $this->_rule->save($this->_rule_id, $this->_save_config);
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        return $result;
    }
    
    protected function assignFormatPopupTemplate($tpl, $args) {
        foreach ($args as $label => $value) {
            $tpl->assign($label, $value);
        }
        
        $tpl->assign('custom_message', array('label' => _('Custom message')));
    }
    
    public function getFormatPopup($args) {        
        if (!isset($this->rule_data['format_popup']) || is_null($this->rule_data['format_popup']) || $this->rule_data['format_popup']  == '') {
            return null;
        }
        
        $result = array('format_popup' => null);
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Mail/templates', $this->_centreon_path);
        
        $this->assignFormatPopupTemplate($tpl, $args);
        $tpl->assign('string', $this->change_html_tags($this->rule_data['format_popup'], 0));
        $result['format_popup'] = $tpl->fetch('eval.ihtml');
        return $result;
    }
    
    public function submitTicket($args) {
        
    }
}
