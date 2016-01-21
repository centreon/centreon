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
    protected $_request;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($db) {
        $this->_db = $db;
        $this->_request = new Centreon_OpenTickets_Request();
    }

    /**
     * Sets the activate field
     *
     * @param array $select
     * @param int $val
     * @return void
     */
    protected function _setActivate($select, $val) {
        $query = "UPDATE mod_auto_disco_rule SET rule_activate = '$val' WHERE rule_id IN (";
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
        
        $DBRESULT = $this->_db->query("SELECT alias, provider_id FROM mod_open_tickets_rule WHERE rule_id = '" . $ruleId . "' LIMIT 1");
        if (($row = $DBRESULT->fetchRow())) {
            $result['alias'] = $row[0];
            $result['provider_id'] = $row[1];
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
        foreach ($select as $ruleId => $val) {
            $query = "SELECT * FROM mod_auto_disco_rule WHERE rule_id = '".$ruleId."' LIMIT 1";
            $res = $this->_db->query($query);
            if (!$res->numRows()) {
                throw new Exception(sprintf('Rule ID: % not found', $ruleId));
            }
            $row = $res->fetchRow();
            
            $i = 1;
            if (isset($duplicateNb[$ruleId]) && $duplicateNb[$ruleId] > 0) {
                for ($j = 1; $j <= $duplicateNb[$ruleId]; $j++) {
                    $name = $row['rule_alias']."_".$j;
                    $res2 = $this->_db->query("SELECT `rule_id` FROM `mod_auto_disco_rule` WHERE `rule_alias` = '".$name."'");
                    while ($res2->numRows()) {
                        $res2->free();
                        $i++;
                        $name = $row['rule_alias']."_".$i;
                        $res2 = $this->_db->query("SELECT `rule_id` FROM `mod_auto_disco_rule` WHERE `rule_alias` = '".$name."'");
                    }
                    $query2 = <<<EOQ
INSERT INTO mod_auto_disco_rule
  (rule_alias, service_display_name, rule_activate, rule_disable,
   rule_comment, command_command_id, service_template_model_id, command_command_id2)
VALUES
  ('$name', '{$row['service_display_name']}', '{$row['rule_activate']}',
   '{$row['rule_disable']}', '{$row['rule_comment']}', '{$row['command_command_id']}',
   '{$row['service_template_model_id']}', {$row['command_command_id2']})
EOQ;
                    $this->_db->query($query2);

                    $res2 = $this->_db->query("SELECT `rule_id` FROM mod_auto_disco_rule WHERE rule_alias='$name'");
                    if (!$res2->numRows()) {
                        throw new Exception(sprintf("Failed to retrieve newly duplicated rule '%s'", $name));
                    }
                    $row = $res2->fetchRow();
                    $nrule_id = $row['rule_id'];

                    // Duplicate host template relations
                    $res2 = $this->_db->query("SELECT host_host_id FROM mod_auto_disco_ht_rule_relation WHERE rule_rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $this->_db->query(<<<EOQ
INSERT INTO mod_auto_disco_ht_rule_relation
  (host_host_id, rule_rule_id)
VALUES
  ({$row['host_host_id']}, $nrule_id)
EOQ
                                          );
                    }

                    // Duplicate contact relations
                    $res2 = $this->_db->query("SELECT contact_id, cg_id FROM mod_auto_disco_rule_contact_relation WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        if (!$row['contact_id']) {
                            $row['contact_id'] = 'NULL';
                        }
                        if (!$row['cg_id']) {
                            $row['cg_id'] = 'NULL';
                        }
                        $this->_db->query(<<<EOQ
INSERT INTO mod_auto_disco_rule_contact_relation
  (rule_id, contact_id, cg_id)
VALUES
  ($nrule_id, {$row['contact_id']}, {$row['cg_id']})
EOQ
                                          );
                    }

                    // Duplicate inclusions/exclusions
                    $res2 = $this->_db->query("SELECT * FROM mod_auto_disco_inclusion_exclusion WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $this->_db->query(<<<EOQ
INSERT INTO mod_auto_disco_inclusion_exclusion
  (exinc_type, exinc_str, exinc_regexp, exinc_order, rule_id)
VALUES
  ('{$row['exinc_type']}', '{$row['exinc_str']}', '{$row['exinc_regexp']}', '{$row['exinc_order']}', $nrule_id)
EOQ
                                          );
                    }
                    
                    // Duplicate change
                    $res2 = $this->_db->query("SELECT * FROM mod_auto_disco_change WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $this->_db->query(<<<EOQ
INSERT INTO mod_auto_disco_change
  (change_str, change_regexp, change_replace, change_order, rule_id)
VALUES
  ('{$row['change_str']}', '{$row['change_regexp']}', '{$row['change_replace']}', '{$row['change_order']}', $nrule_id)
EOQ
                                          );
                    }

                    // Duplicate macros
                    $res2 = $this->_db->query("SELECT * FROM mod_auto_disco_macro WHERE rule_id=$ruleId");
                    while (($row = $res2->fetchRow())) {
                        $this->_db->query(<<<EOQ
INSERT INTO mod_auto_disco_macro
  (macro_name, macro_value, is_empty, rule_id)
VALUES
  ('{$row['macro_name']}', '{$row['macro_value']}', '{$row['is_empty']}', $nrule_id)
EOQ
                                          );
                    }
                }
            }
        }
    }

    /**
     * Delete rules
     *
     * @param array select
     * @return void
     */
    public function delete($select) {
        $query = "DELETE FROM mod_auto_disco_rule WHERE rule_id IN (";
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

    /**
     * Delete inclusions and exclusions
     *
     * @param int $ruleId
     * @return void
     */
    protected function _deleteInclusionsAndExclusions($ruleId)
    {
        $this->_db->query("DELETE FROM mod_auto_disco_inclusion_exclusion WHERE rule_id = '". $this->_db->escape($ruleId) ."'");
    }

    /**
     * Insert inclusions and exclusions
     *
     * @param int $ruleId
     * @return void
     */
    protected function _insertInclusionsAndExclusions($ruleId)
    {
        $inExStr = "";
        $inExAppend = "";
        
        if (isset($_REQUEST['exincType'])) {
            $i = 0;
            foreach ($_REQUEST['exincType'] as $key => $val) {
                if ($_REQUEST['exincStr'][$key] != '') {
                    $inExStr .= $inExAppend . "('" . $this->_db->escape($_REQUEST['exincType'][$key]) . "', '" . $this->_db->escape($_REQUEST['exincStr'][$key])  . "', '" . $this->_db->escape($_REQUEST['exincRegexp'][$key]) . "', '$i', '" . $this->_db->escape($ruleId) . "')";
                    $inExAppend = ', ';
                    $i++;
                }
            }
        }
        
	    if ($inExStr != '') {
            $this->_db->query("INSERT INTO mod_auto_disco_inclusion_exclusion (exinc_type, exinc_str, exinc_regexp, exinc_order, rule_id) VALUES $inExStr");
	    }
    }
    
    /**
     * Delete change
     *
     * @param int $ruleId
     * @return void
     */
    protected function _deleteChange($ruleId)
    {
        $this->_db->query("DELETE FROM mod_auto_disco_change WHERE rule_id = '". $this->_db->escape($ruleId) ."'");
    }

    /**
     * Insert change
     *
     * @param int $ruleId
     * @return void
     */
    protected function _insertChange($ruleId)
    {
        $changeStr = "";
        $changeAppend = "";
        
        if (isset($_REQUEST['changeStr'])) {
            $i = 0;
            foreach ($_REQUEST['changeStr'] as $key => $val) {
                if ($_REQUEST['changeStr'][$key] != '') {
                    $changeStr .= $changeAppend . "('" . $this->_db->escape($_REQUEST['changeStr'][$key]) . "', '" . $this->_db->escape($_REQUEST['changeRegexp'][$key])  . "', '" . $this->_db->escape($_REQUEST['changeReplace'][$key]) . "', '$i', '" . $this->_db->escape($ruleId) . "')";
                    $changeAppend = ', ';
                    $i++;
                }
            }
        }
        
	    if ($changeStr != '') {
            $this->_db->query("INSERT INTO mod_auto_disco_change (change_str, change_regexp, change_replace, change_order, rule_id) VALUES $changeStr");
	    }
    }

    /**
     * Delete macros
     *
     * @param int $ruleId
     * @return void
     */
    protected function _deleteMacros($ruleId)
    {
        $this->_db->query("DELETE FROM mod_auto_disco_macro WHERE rule_id = '" . $this->_db->escape($ruleId) . "'");
    }

    /**
     * Insert macros
     *
     * @param int $ruleId
     * @return void
     */
    protected function _insertMacros($ruleId) {
        $macros_request = $this->_getMacrosFromRequest();    
        $macroStr = "";
        $macroAppend = "";
        
        foreach ($macros_request as $key => $val) {
            if ($val['macro_value'] != '' || $val['is_empty'] == 1) {
                $macroStr .= $macroAppend . "('" . $this->_db->escape($ruleId) . "', '" . $this->_db->escape($val['macro_name'])  . "', '" . $this->_db->escape($val['macro_value']) . "', '" . $val['is_empty'] . "')";
                $macroAppend = ', ';
            }
        }
        
        if ($macroStr) {
            $this->_db->query("INSERT INTO mod_auto_disco_macro (rule_id, macro_name, macro_value, is_empty) VALUES $macroStr");
        }
    }

    /**
     * Insert host template relations
     *
     * @param array $hostTemlates
     * @param int $ruleId
     * @return void
     */
    protected function _insertHostTemplates($hostTemplates = array(), $ruleId) {
        $tplStr = "";
        foreach ($hostTemplates as $value) {
            if ($tplStr) {
                $tplStr .= ", ";
            }
            $tplStr .= "('" .$value. "', '".$ruleId."')";
        }
        if ($tplStr) {
            $this->_db->query("INSERT INTO mod_auto_disco_ht_rule_relation (host_host_id, rule_rule_id) VALUES $tplStr");
        }
    }

    /**
     * Delete host templates
     *
     * @param int $ruleId
     * @return void
     */
    protected function _deleteHostTemplates($ruleId) {
        $this->_db->query("DELETE FROM mod_auto_disco_ht_rule_relation WHERE rule_rule_id = '".htmlentities($ruleId, ENT_QUOTES)."'");
    }

    /**
     * Delete contact and contactgroups of a rule
     *
     * @return void
     */
    protected function _deleteContactsAndContactgroups($ruleId) {
        $this->_db->query("DELETE FROM mod_auto_disco_rule_contact_relation WHERE rule_id = '".htmlentities($ruleId, ENT_QUOTES)."'");
    }

    /**
     * Insert contact and contactgroups for a rule
     *
     * @return void
     */
    protected function _insertContactsAndContactgroups($contacts, $contactgroups, $ruleId) {
        $contactStr = "";
        if (isset($contacts)) {
	        foreach ($contacts as $value) {
	            if ($contactStr) {
	                $contactStr .= ", ";
	            }
	            $contactStr .= "('" .$value. "', '".$ruleId."')";
	        }
	        if ($contactStr) {
	            $this->_db->query("INSERT INTO mod_auto_disco_rule_contact_relation (contact_id, rule_id) VALUES $contactStr");
	        }
        }

        $cgtStr = "";
        if (isset($contactgroups)) {
	        foreach ($contactgroups as $value) {
	            if ($cgtStr) {
	                $cgtStr .= ", ";
	            }
	            $cgtStr .= "('" .$value. "', '".$ruleId."')";
	        }
	        if ($cgtStr) {
	            $this->_db->query("INSERT INTO mod_auto_disco_rule_contact_relation (cg_id, rule_id) VALUES $cgtStr");
	        }
        }
    }

    /**
     * Insert new rule
     *
     * @return void
     */
    public function insert($form) {
        $rule_activate = $form->getSubmitValue('rule_activate');
        $rule_disable = $form->getSubmitValue('rule_disable');
        $command_id2 = $form->getSubmitValue('command_command_id2');
        if (!isset($command_id2) || is_null($command_id2) || $command_id2 == '') {
            $command_id2 = 'NULL';
        } else {
            $command_id2 = "'" . $this->_db->escape($command_id2) . "'";
        }
        
        $query  = "INSERT INTO mod_auto_disco_rule (rule_alias, service_display_name, rule_activate, rule_disable, rule_comment, command_command_id, service_template_model_id, command_command_id2) VALUES (";
        $query .= "'" . $this->_db->escape($form->getSubmitValue('rule_alias')). "',";
        $query .= "'" . $this->_db->escape($form->getSubmitValue('service_display_name')). "',";
        $query .= "'" . $this->_db->escape($rule_activate['rule_activate']). "',";
        $query .= "'" . $this->_db->escape($rule_disable['rule_disable']). "',";
        $query .= "'" . $this->_db->escape($form->getSubmitValue('rule_comment')). "',";
        $query .= "'" . $this->_db->escape($form->getSubmitValue('command_command_id')). "',";
        $query .= "'" . $this->_db->escape($form->getSubmitValue('service_template_model_id')). "', ";
        $query .= "" .$command_id2. ")";
        $this->_db->query($query);

        $query = "SELECT MAX(rule_id) FROM mod_auto_disco_rule WHERE rule_alias = '". $this->_db->escape($form->getSubmitValue('rule_alias')) . "' LIMIT 1";
        $res = $this->_db->query($query);
        $row = $res->fetchRow();
        $ruleId = $row['MAX(rule_id)'];
        $hostTemplates = $form->getSubmitValue('host_host_id');
        $this->_deleteHostTemplates($ruleId);
        $this->_insertHostTemplates($hostTemplates, $ruleId);
        $contacts = $form->getSubmitValue('contact_id');
        $contactgroups = $form->getSubmitValue('cg_id');
        $this->_deleteContactsAndContactgroups($ruleId);
        $this->_insertContactsAndContactgroups($contacts, $contactgroups, $ruleId);
        $this->_insertInclusionsAndExclusions($ruleId);
        $this->_insertChange($ruleId);
        $this->_insertMacros($ruleId);
        return $ruleId;
    }

    /**
     * Update a rule
     *
     * @return void
     */
    public function update($form, $ruleId) {
        $rule_activate = $form->getSubmitValue('rule_activate');
        $rule_disable = $form->getSubmitValue('rule_disable');
        $command_id2 = $form->getSubmitValue('command_command_id2');
        if (!isset($command_id2) || is_null($command_id2) || $command_id2 == '') {
            $command_id2 = 'NULL';
        } else {
            $command_id2 = "'" . $this->_db->escape($command_id2) . "'";
        }
                
        $query  = "UPDATE mod_auto_disco_rule SET ";
        $query .= "rule_alias = '" . $this->_db->escape($form->getSubmitValue('rule_alias')) . "', ";
        $query .= "service_display_name = '" . $this->_db->escape($form->getSubmitValue('service_display_name')) . "', ";
        $query .= "rule_activate = '" . $this->_db->escape($rule_activate['rule_activate']) . "', ";
        $query .= "rule_disable = '" . $this->_db->escape($rule_disable['rule_disable']) . "', ";
        $query .= "rule_comment = '" . $this->_db->escape($form->getSubmitValue('rule_comment')) . "', ";
        $query .= "command_command_id = '" . $this->_db->escape($form->getSubmitValue('command_command_id')) . "', ";
        $query .= "service_template_model_id = '" . $this->_db->escape($form->getSubmitValue('service_template_model_id')) . "', ";
        $query .= "command_command_id2 = " . $command_id2 . " ";
        $query .= "WHERE rule_id = '" . $this->_db->escape($ruleId) . "'";

        $this->_db->query($query);
        
        $hostTemplates = $form->getSubmitValue('host_host_id');
        $this->_deleteHostTemplates($ruleId);
        $this->_insertHostTemplates($hostTemplates, $ruleId);

        $contacts = $form->getSubmitValue('contact_id');
        $contactgroups = $form->getSubmitValue('cg_id');

        $this->_deleteContactsAndContactgroups($ruleId);
        $this->_insertContactsAndContactgroups($contacts, $contactgroups, $ruleId);
        $this->_deleteInclusionsAndExclusions($ruleId);
        $this->_insertInclusionsAndExclusions($ruleId);
        $this->_deleteChange($ruleId);
        $this->_insertChange($ruleId);
        $this->_deleteMacros($ruleId);
        $this->_insertMacros($ruleId);
    }
    
    /**
     * Get macros saves for a rule
     *
     * @param int $ruleId
     * @return array
     */
    protected function _getMacrosAutodisco($ruleId) {
        $macros = array();
        
        if (!isset($ruleId)) {
            return $macros;
        }
        $DBRESULT = $this->_db->query("SELECT * FROM mod_auto_disco_macro WHERE rule_id = '$ruleId'");
        while (($row = $DBRESULT->fetchRow())) {
            $macros[$row['macro_name']] = $row;
            $macros[$row['macro_name']]['macroFrom'] = 0;
        }
        
        return $macros;
    }
    
    /**
     * Get macros from _REQUEST
     *
     * @param array $force_request_array
     * @return array
     */
    protected function _getMacrosFromRequest($force_request_array = null) {
        $macros = array();
        
        if (!is_null($force_request_array)) {
            foreach($force_request_array['macroName'] as $key => $val) {
                $macros[$val] = array('macro_name' => $val, 'macro_value' => $force_request_array['macroValue'][$key], 'is_empty' => $force_request_array['macroEmpty'][$key], 'macroFrom' => 1);
            }
        } else if (isset($_REQUEST['macroName'])) {
            foreach($_REQUEST['macroName'] as $key => $val) {
                $macros[$val] = array('macro_name' => $val, 'macro_value' => $_REQUEST['macroValue'][$key], 'is_empty' => $_REQUEST['macroEmpty'][$key], 'macroFrom' => 1);
            }
        }
        
        return $macros;
    }
    
    /**
     * Get macros of a service template
     *
     * @param int $service_template_id
     * @return array
     */
    protected function _getMacrosServiceTemplate($service_template_id) {
        $macros = array();
        $command_id = null;
        
        while (1) {
            $DBRESULT = $this->_db->query("SELECT service_template_model_stm_id, command_command_id FROM service WHERE service_id = '$service_template_id' AND service_register = '0'");
            if (!($row = $DBRESULT->fetchRow())) {
                break;
            }

            if (!is_null($row['command_command_id']) && $row['command_command_id'] != '' && $row['command_command_id'] > 0) {
                $command_id = $row['command_command_id'];
                break;
            }
            
            if (is_null($row['service_template_model_stm_id']) || $row['service_template_model_stm_id'] == '') {
                break;
            }
            
            $service_template_id = $row['service_template_model_stm_id'];
        }

        if (!is_null($command_id)) {
            $DBRESULT = $this->_db->query("SELECT command_line FROM command WHERE command_id = '$command_id'");
            if (($row = $DBRESULT->fetchRow())) {
                if (preg_match_all("/\\\$_SERVICE(.*?)\\\$/", $row['command_line'], $matches)) {
                    foreach ($matches[1] as $val) {
                        $macros[$val] = 1;
                    }
                }
            }
        }
        
        return $macros;
    }
    
    /**
     * Get clone macros
     *
     * @param int $ruleId
     * @param int $service_template_id
     * @param int $force_request_array
     * @return array
     */
    public function getCloneMacros($ruleId, $service_template_id, $force_request_array = null) {
        # Get from templates (mandatory)
        $macros_service_template = array();
        if (isset($service_template_id)) {
            $macros_service_template = $this->_getMacrosServiceTemplate($service_template_id);
        }
        
        $macros_autodisco = $this->_getMacrosAutodisco($ruleId);
        $macros_request = $this->_getMacrosFromRequest($force_request_array);
        $macros_current = array_merge($macros_autodisco, $macros_request);
        
        $macroArray = array();
        $i = 0;
        foreach ($macros_service_template as $key => $value) {
            if (isset($macros_current[$key])) {
                $macroArray[$i]['macroName_#index#'] = $macros_current[$key]['macro_name'];
                $macroArray[$i]['macroValue_#index#'] = $macros_current[$key]['macro_value'];
                $macroArray[$i]['macroFrom_#index#'] = $macros_current[$key]['macro_from'];
                $macroArray[$i]['macroEmpty_#index#'] = ($macros_current[$key]['is_empty'] == 1) ? 1 : null;
            } else {
                $macroArray[$i]['macroName_#index#'] = $key;
                $macroArray[$i]['macroValue_#index#'] = '';
                $macroArray[$i]['macroFrom_#index#'] = 2;
                $macroArray[$i]['macroEmpty_#index#'] = null;
            }
            $i++;
        }
        
        return $macroArray;
    }
    
    public function getExInc($ruleId) {
        $excincArray = array();
        
        $DBRESULT = $this->_db->query("SELECT * FROM mod_auto_disco_inclusion_exclusion WHERE rule_id = '" . $this->_db->escape($ruleId) . "' ORDER BY exinc_order ASC");
        while (($row = $DBRESULT->fetchRow())) {
            $excincArray[] = $row;
        }
        
        return $excincArray;
    }
    
     /**
     * Get clone exclusion inclusion
     *
     * @param int $ruleId
     * @return array
     */
    public function getCloneExInc($ruleId)  {
        $excincArray = array();
        
        if (isset($_REQUEST['exinc_submit']) && $_REQUEST['exinc_submit'] == 1) {
            if (isset($_REQUEST['exincType'])) {
                $i = 0;
                foreach ($_REQUEST['exincType'] as $key => $val) {
                    $excincArray[$i]['exincStr_#index#'] = $_REQUEST['exincStr'][$key];
                    $excincArray[$i]['exincRegexp_#index#'] = $_REQUEST['exincRegexp'][$key];
                    $excincArray[$i]['exincType_#index#'] = $val;
                    $i++;
                }
            }
        } else {
            $exincDbArray = $this->getExInc($ruleId);
            $i = 0;
            foreach ($exincDbArray as $val) {
                $excincArray[$i]['exincStr_#index#'] = $val['exinc_str'];
                $excincArray[$i]['exincRegexp_#index#'] = $val['exinc_regexp'];
                $excincArray[$i]['exincType_#index#'] = $val['exinc_type'];
                $i++;
            }
        }

        return $excincArray;
    }
    
    public function getChange($ruleId) {
        $changeArray = array();
        
        $DBRESULT = $this->_db->query("SELECT * FROM mod_auto_disco_change WHERE rule_id = '" . $this->_db->escape($ruleId) . "' ORDER BY change_order ASC");
        while (($row = $DBRESULT->fetchRow())) {
            $changeArray[] = $row;
        }
        
        return $changeArray;
    }
    
     /**
     * Get clone change
     *
     * @param int $ruleId
     * @return array
     */
    public function getCloneChange($ruleId)  {
        $changeArray = array();
        
        if (isset($_REQUEST['change_submit']) && $_REQUEST['change_submit'] == 1) {
            if (isset($_REQUEST['changeStr'])) {
                $i = 0;
                foreach ($_REQUEST['changeStr'] as $key => $val) {
                    $changeArray[$i]['changeStr_#index#'] = $_REQUEST['changeStr'][$key];
                    $changeArray[$i]['changeRegexp_#index#'] = $_REQUEST['changeRegexp'][$key];
                    $changeArray[$i]['changeReplace_#index#'] = $_REQUEST['changeReplace'][$key];
                    $i++;
                }
            }
        } else {
            $changeDbArray = $this->getChange($ruleId);
            $i = 0;
            foreach ($changeDbArray as $val) {
                $changeArray[$i]['changeStr_#index#'] = $val['change_str'];
                $changeArray[$i]['changeRegexp_#index#'] = $val['change_regexp'];
                $changeArray[$i]['changeReplace_#index#'] = $val['change_replace'];
                $i++;
            }
        }

        return $changeArray;
    }
    
    /**
     * Get command show replaced
     *
     * @param int $command_id
     * @return array
     */
     public function getCommandShow($command_id) {
        $DBRESULT = $this->_db->query("SELECT command_line FROM command WHERE command_id = '" . $this->_db->escape($command_id) . "'");
        $row = $DBRESULT->fetchRow();
        if (!$row) {
            return array('code' => 1, 'msg' => _("Cannot find command"), 'command' => null);
        }
        $command = $row['command_line'];
        while (preg_match('/\$(USER[0-9]+)\$/', $command, $matches)) {
            $DBRESULT = $this->_db->query("SELECT resource_line FROM cfg_resource WHERE resource_name = '$" . $matches[1] . "$' LIMIT 1");
            if (($row = $DBRESULT->fetchRow())) {
                $command = str_replace("$" . $matches[1] . "$", $row["resource_line"], $command);
            }
        }
        
        return array('code' => 0, 'msg' => '', 'command' => $command);
    }
}

?>
