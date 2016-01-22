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
    protected $_db;
    protected $_rule_id;
    protected $_centreon_path;
    protected $_centreon_open_tickets_path;
    protected $_config = array("container1_html" => '', "container2_html" => '', "clones" => array());
    protected $_required_field = '&nbsp;<font color="red" size="1">*</font>';
    protected $_submitted_config = null;
    
    /**
     * constructor
     *
     * @return void
     */
    public function __construct($db, $centreon_path, $centreon_open_tickets_path, $rule_id, $submitted_config = null) {
        $this->_db = $db;
        $this->_centreon_path = $centreon_path;
        $this->_centreon_open_tickets_path = $centreon_open_tickets_path;
        $this->_rule_id = $rule_id;
        $this->_submitted_config = $submitted_config;
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
        $url_html = '<input size="50" name="url" type="text" value="" />';
        $message_confirm_html = '<textarea rows="5" cols="40" name="service_comment"></textarea>';
        $ack_html = '<input type="checkbox" name="ack" value="yes" />';

        $array_form = array(
            'url' => array('label' => _("Url"), 'html' => $url_html),
            'message_confirm' => array('label' => _("Confirm message popup"), 'html' => $message_confirm_html),
            'ack' => array('label' => _("Acknowledge"), 'html' => $ack_html)
        );
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1main.ihtml');
    }
    
    /**
     * Build the specifc config: from, subject, body, headers
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
        $from_html = '<input size="50" name="from" type="text" value="" />';
        $subject_html = '<input size="50" name="subject" type="text" value="" />';
        $body_html = '<textarea rows="8" cols="40" name="body"></textarea>';

        $array_form = array(
            'from' => array('label' => _("From"), 'html' => $from_html),
            'subject' => array('label' => _("Subject"), 'html' => $subject_html),
            'header' => array('label' => _("Headers")),
            'body' => array('label' => _("Body"), 'html' => $body_html)
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
        
        // Test build clones
        $headerMail_values = array(
            array('headerMailName_#index#' => 'test', 'headerMailValue_#index#' => 'test'),
            array('headerMailName_#index#' => 'test2', 'headerMailValue_#index#' => 'test2')
        );
        $this->_config['clones']['headerMail'] = array(
            'clone_values' => json_encode($headerMail_values),
            'clone_count' => count($headerMail_values)
        );
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
        $macro_name_html = '<input size="50" name="macro_name" type="text" value="" />';
        $format_popup_html = '<textarea rows="5" cols="40" name="format_popup"></textarea>';

        $array_form = array(
            'macro_name' => array('label' => _("Macro name") . $this->_required_field, 'html' => $macro_name_html),
            'format_popup' => array('label' => _("Formatting popup") . $this->_required_field, 'html' => $format_popup_html)
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
    
    public function saveConfig() {
        $result = $this->_getCloneSubmitted('headerMail', array('Name', 'Value'));
        $fp = fopen('/tmp/debug.txt', 'a+');
        fwrite($fp, "=====\n");
        fwrite($fp, print_r($result, true));
        fwrite($fp, "=====\n");
    }
}
