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

class MailProvider extends AbstractProvider {    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['from'] = 'admin@domain.com';
        $this->default_data['subject'] = 'Open a ticket';
        $this->default_data['body'] = '
<html>
<body>

<div>
{$user} open ticket at {$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}
</div>

<div>
{$custom_message}
</div>

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
</body>
</html>
        ';
        
        $this->default_data['body'] = $this->change_html_tags($this->default_data['body']);
        
        $this->default_data['clones']['headerMail'] = array(
            array('Name' => 'MIME-Version', 'Value' => '1.0'),
            array('Name' => 'Content-Type', 'Value' => 'text/html; charset=utf8')
        );
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
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
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
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['clones']['headerMail'] = $this->_getCloneSubmitted('headerMail', array('Name', 'Value'));
        $this->_save_config['simple']['from'] = $this->_submitted_config['from'];
        $this->_save_config['simple']['to'] = $this->_submitted_config['to'];
        $this->_save_config['simple']['subject'] = $this->_submitted_config['subject'];
        $this->_save_config['simple']['body'] = $this->change_html_tags($this->_submitted_config['body']);
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        return $result;
    }
    
    protected function doSubmit($db_storage, $user, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());
        
        try {
            $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`) VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($user) . "')";            
            $db_storage->query($query);
            $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
            $result['ticket_is_ok'] = 1;
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign('user', $user);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        foreach ($this->_submitted_config as $label => $value) {
            $tpl->assign($label, $value);
        }
        $tpl->assign('string', $this->change_html_tags($this->rule_data['body'], 0));
        $body = $tpl->fetch('eval.ihtml');
        
        // We send the mail
        $headers = "From: " . $this->rule_data['from'];
        if (isset($this->rule_data['clones']['headerMail'])) {
            foreach ($this->rule_data['clones']['headerMail'] as $values) {
                $headers .= "\r\n" . $values['Name'] . ':' . $values['Value'];
            }
        }

        mail($this->rule_data['to'], $this->rule_data['subject'], $body, $headers);
        
        return $result;
    }
}
