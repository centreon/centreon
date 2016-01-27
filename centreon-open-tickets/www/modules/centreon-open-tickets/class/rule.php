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

class Centreon_OpenTickets_Rule
{
    protected $_db;
    protected $_provider = null;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($db) {
        $this->_db = $db;
    }

    /**
     * Sets the activate field
     *
     * @param array $select
     * @param int $val
     * @return void
     */
    protected function _setActivate($select, $val) {
        $query = "UPDATE mod_open_tickets_rule SET `activate` = '$val' WHERE rule_id IN (";
        $ruleList = "";
        $ruleListAppend = "";
        foreach ($select as $key => $value) {
            $ruleList .= $ruleListAppend . "'" . $key . "'";
            $ruleListAppend = ', ';
        }
        if (isset($_REQUEST['rule_id'])) {
            $ruleList .= $ruleListAppend . "'" . $_REQUEST['rule_id'] . "'";
        }
        $query .= $ruleList;
        $query .= ")";
        if (!$ruleList) {
            return null;
        }
        $this->_db->query($query);
    }

    public function getAliasAndProviderId($rule_id) {
        $result = array();
        if (is_null($rule_id)) {
            return $result;
        }
        
        $DBRESULT = $this->_db->query("SELECT alias, provider_id FROM mod_open_tickets_rule WHERE rule_id = '" . $rule_id . "' LIMIT 1");
        if (($row = $DBRESULT->fetchRow())) {
            $result['alias'] = $row['alias'];
            $result['provider_id'] = $row['provider_id'];
        }
        
        return $result;
    }
    
    protected function loadProvider($rule_id, $provider_id) {
        global $centreon_path, $register_providers;
        
        if (!is_null($this->_provider)) {
            return ;
        }
                
        $centreon_open_tickets_path = $centreon_path . 'www/modules/centreon-open-tickets/';
        require_once $centreon_open_tickets_path . 'providers/register.php';
        
        $provider_name = null;
        foreach ($register_providers as $name => $id) {
            if ($id == $provider_id) {
                $provider_name = $name;
                break;
            }
        }
        
        if (is_null($provider_name) || !file_exists($centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php')) {
            throw new Exception(sprintf('Cannot find provider'));
        }
        
        require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';
        $classname = $provider_name . 'Provider';
        $this->_provider = new $classname($this, $centreon_path, $centreon_open_tickets_path, $rule_id);
    }
    
    public function getMacroNames($rule_id) {
        $result = array('ticket_id' => null, 'ticket_time' => null);
        
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id']);
        $result['ticket_id'] = $this->_provider->getMacroTicketId();
        $result['ticket_time'] = $this->_provider->getMacroTicketTime();
        
        return $result;
    }
    
    public function getFormatPopupProvider($rule_id, $args) {        
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id']);
        
        return $this->_provider->getFormatPopup($args);
    }
    
    public function submitTicket($rule_id, $args) {
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id']);
        
        return $this->_provider->submitTicket($args);
    }
    
    public function save($rule_id, $datas) {
        $this->_db->autocommit(0);
        
        $nrule_id = $rule_id;
        $DBRESULT = $this->_db->query("SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" . $this->_db->escape($rule_id) . "' LIMIT 1");
        if (!($row = $DBRESULT->fetchRow())) {
            $query = "INSERT INTO mod_open_tickets_rule
  (`alias`, `provider_id`, `activate`) VALUES ('" . $this->_db->escape($datas['rule_alias']) . "', '" . $this->_db->escape($datas['provider_id']) . "', '1')";            
            $this->_db->query($query);
            $nrule_id = $this->_db->lastinsertId('mod_open_tickets_rule');;
        } else {
            $query = "UPDATE mod_open_tickets_rule SET `alias` = '" . $this->_db->escape($datas['rule_alias']) . 
            "', `provider_id` = '" . $datas['provider_id'] . "' WHERE rule_id = '" . $this->_db->escape($rule_id) . "'";
            $this->_db->query($query);
            $this->_db->query("DELETE FROM mod_open_tickets_form_clone WHERE rule_id = '" . $this->_db->escape($rule_id) . "'");
            $this->_db->query("DELETE FROM mod_open_tickets_form_value WHERE rule_id = '" . $this->_db->escape($rule_id) . "'");
        }
        
        foreach ($datas['simple'] as $uniq_id => $value) {
            $query = "INSERT INTO mod_open_tickets_form_value
  (`uniq_id`, `value`, `rule_id`) VALUES ('" . $this->_db->escape($uniq_id) . "', '" . $this->_db->escape($value) . "', '" . $this->_db->escape($nrule_id) . "')";
            $this->_db->query($query);
        }
        
        foreach ($datas['clones'] as $uniq_id => $orders) {
            foreach ($orders as $order => $values) {                
                foreach ($values as $key => $value) {
                    $query = "INSERT INTO mod_open_tickets_form_clone
  (`uniq_id`, `label`, `value`, `rule_id`, `order`) VALUES ('" . $this->_db->escape($uniq_id) . "', '" . 
    $this->_db->escape($key) . "', '" . $this->_db->escape($value) . "', '" . $this->_db->escape($nrule_id) . "', '" . $this->_db->escape($order) . "')";
                    $this->_db->query($query);
                }
            }
        }
        
        $this->_db->commit();
    }
    
    public function get($rule_id) {
        $result = array();
        if (is_null($rule_id)) {
            return $result;
        }
        
        $DBRESULT = $this->_db->query("SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" . $this->_db->escape($rule_id) . "' LIMIT 1");
        if (!($row = $DBRESULT->fetchRow())) {
            return $result;
        }
        $result['rule_alias'] = $row['alias'];

        $result['clones'] = array();
        $DBRESULT = $this->_db->query("SELECT * FROM mod_open_tickets_form_clone WHERE rule_id = '" . $this->_db->escape($rule_id) . "' ORDER BY uniq_id, `order` ASC");
        while (($row = $DBRESULT->fetchRow())) {
            if (!isset($result['clones'][$row['uniq_id']])) {
                $result['clones'][$row['uniq_id']] = array();
            }
            if (!isset($result['clones'][$row['uniq_id']][$row['order']])) {
                $result['clones'][$row['uniq_id']][$row['order']] = array();
            }
            $result['clones'][$row['uniq_id']][$row['order']][$row['label']] = $row['value'];
        }
        
        $DBRESULT = $this->_db->query("SELECT * FROM mod_open_tickets_form_value WHERE rule_id = '" . $this->_db->escape($rule_id) . "'");
        while (($row = $DBRESULT->fetchRow())) {
            $result[$row['uniq_id']] = $row['value'];
        }
        
        return $result;
    }
    
    /**
     * Enable rules
     *
     * @param array $select
     * @return void
     */
    public function enable($select) {
        $this->_setActivate($select, 1);
    }

    /**
     * Disable rules
     *
     * @param array $select
     * @return void
     */
    public function disable($select) {
        $this->_setActivate($select, 0);
    }

    /**
     * Duplicate rules
     *
     * @param array $select
     * @param array $duplicateNb
     * @return void
     */
    public function duplicate($select = array(), $duplicateNb = array()) {
        $this->_db->autocommit(0);
        foreach ($select as $ruleId => $val) {
            $query = "SELECT * FROM mod_open_tickets_rule WHERE rule_id = '" . $ruleId . "' LIMIT 1";
            $res = $this->_db->query($query);
            if (!$res->numRows()) {
                throw new Exception(sprintf('Rule ID: % not found', $ruleId));
            }
            $row = $res->fetchRow();
                        
            $i = 1;
            if (isset($duplicateNb[$ruleId]) && $duplicateNb[$ruleId] > 0) {
                for ($j = 1; $j <= $duplicateNb[$ruleId]; $j++) {
                    $name = $row['alias'] . "_" . $j;
                    $res2 = $this->_db->query("SELECT `rule_id` FROM `mod_open_tickets_rule` WHERE `alias` = '" . $this->_db->escape($name) . "'");
                    while ($res2->numRows()) {
                        $res2->free();
                        $i++;
                        $name = $row['alias'] . "_" . $i;
                        $res2 = $this->_db->query("SELECT `rule_id` FROM `mod_open_tickets_rule` WHERE `alias` = '" . $this->_db->escape($name) . "'");
                    }
                    $query = "INSERT INTO mod_open_tickets_rule
  (`alias`, `provider_id`, `activate`) VALUES " . 
  "('" . $this->_db->escape($name) . "', " . $row['provider_id'] . ", " . $row['activate'] . ")";
                    $this->_db->query($query);

                    $nrule_id = $this->_db->lastinsertId('mod_open_tickets_rule');
                    
                    // Duplicate form clone
                    $res2 = $this->_db->query("SELECT * FROM mod_open_tickets_form_clone WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $query = "INSERT INTO mod_open_tickets_form_clone
  (`uniq_id`, `label`, `value`, `rule_id`) VALUES " . 
  "('" . $this->_db->escape($row['uniq_id']) . "', '" . $this->_db->escape($row['label']) . "', '" . $this->_db->escape($row['value']) . "', " . $nrule_id . ")";
                        $this->_db->query($query);
                    }

                    // Duplicate macros
                    $res2 = $this->_db->query("SELECT * FROM mod_open_tickets_form_value WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $query = "INSERT INTO mod_open_tickets_form_value
  (`uniq_id`, `value`, `rule_id`) VALUES " . 
  "('" . $row['uniq_id'] . "', '" . $this->_db->escape($row['value']) . "', " . $nrule_id . ")";
                        $this->_db->query($query);
                    }
                }
            }
        }
        
        $this->_db->commit();
    }

    /**
     * Delete rules
     *
     * @param array select
     * @return void
     */
    public function delete($select) {
        $query = "DELETE FROM mod_open_tickets_rule WHERE rule_id IN (";
        $ruleList = "";
        foreach ($select as $key => $value) {
            if ($ruleList) {
                $ruleList .= ",";
            }
            $ruleList .= "'" . $key . "'";
        }
        $query .= $ruleList;
        $query .= ")";
        if (!$ruleList) {
            return null;
        }
        $this->_db->query($query);
    }

}

?>
