<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */
require_once(__DIR__ . '/centreonAuth.class.php');

/**
 * Class
 *
 * @class CentreonLogAction
 */
class CentreonLogAction
{
    /**
     * Const use to keep the changelog mechanism with hidden password values
     */
    public const PASSWORD_BEFORE = '*******';
    public const PASSWORD_AFTER = CentreonAuth::PWS_OCCULTATION;

    /** @var CentreonUser */
    protected $logUser;
    /** @var array */
    protected $uselessKey;

    /**
     * CentreonLogAction constructor
     *
     * @param CentreonUser $usr
     */
    public function __construct($usr)
    {
        $this->logUser = $usr;
        $this->uselessKey = array();
        $this->uselessKey['submitA'] = 1;
        $this->uselessKey['submitC'] = 1;
        $this->uselessKey['o'] = 1;
        $this->uselessKey['initialValues'] = 1;
        $this->uselessKey['centreon_token'] = 1;
        $this->uselessKey['resource'] = 1;
        $this->uselessKey['plugins'] = 1;
    }

    /**
     * @param string|int $logId
     * @param array $fields
     *
     * @return void
     * @throws PDOException
     */
    public function insertFieldsNameValue($logId, $fields): void
    {
        global $pearDBO;

        $query = "INSERT INTO `log_action_modification` (field_name, field_value, action_log_id) VALUES ";
        $values = [];
        $index = 0;
        foreach ($fields as $field_key => $field_value) {
            $values[] = "(:field_key_" . $index . ", :field_value_" . $index . ", :logId)";
            $index++;
        }
        $query .= implode(", ", $values);
        $statement = $pearDBO->prepare($query);
        $index = 0;
        foreach ($fields as $field_key => $field_value) {
            $key = CentreonDB::escape($field_key);
            $value = CentreonDB::escape($field_value);
            $statement->bindValue(':field_key_' . $index, $key);
            $statement->bindValue(':field_value_' . $index, $value);
            $statement->bindValue(':logId', $logId, PDO::PARAM_INT);
            $index++;
        }
        $statement->execute();
    }

    /**
     * Inserts logs : add, delete or modification of an object
     *
     * @param $object_type
     * @param $object_id
     * @param $object_name
     * @param $action_type
     * @param $fields
     *
     * @return void
     * @throws PDOException
     */
    public function insertLog($object_type, $object_id, $object_name, $action_type, $fields = null): void
    {
        global $pearDBO;

        // Check if audit log option is activated
        $optLogs = $pearDBO->query("SELECT 1 AS REALTIME, audit_log_option FROM `config`");
        $auditLog = $optLogs->fetchRow();

        if (($auditLog) && ($auditLog['audit_log_option'] == '1')) {
            $str_query = "INSERT INTO `log_action`
                (action_log_date, object_type, object_id, object_name, action_type, log_contact_id)
                VALUES ('" . time() . "', :object_type, :object_id, :object_name, :action_type, :user_id)";
            $statement1 = $pearDBO->prepare($str_query);
            $statement1->bindValue(':object_type', $object_type);
            $statement1->bindValue(':object_id', $object_id, PDO::PARAM_INT);
            $statement1->bindValue(':object_name', $object_name);
            $statement1->bindValue(':action_type', $action_type);
            $statement1->bindValue(':user_id', $this->logUser->user_id, PDO::PARAM_INT);
            $statement1->execute();
            $statement2 = $pearDBO->prepare("SELECT MAX(action_log_id) FROM `log_action`");
            $statement2->execute();
            $logId = $statement2->fetch(PDO::FETCH_ASSOC);
            if ($fields) {
                $this->insertFieldsNameValue($logId["MAX(action_log_id)"], $fields);
            }
        }
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws PDOException
     */
    public function getContactname($id): mixed
    {
        global $pearDB;

        $DBRESULT = $pearDB->prepare(
            "SELECT contact_name FROM `contact` WHERE contact_id = :contact_id LIMIT 1"
        );
        $DBRESULT->bindValue(':contact_id', $id, PDO::PARAM_INT);
        $DBRESULT->execute();
        /** @var  $name */
        while ($data = $DBRESULT->fetch(PDO::FETCH_ASSOC)) {
            $name = $data["contact_name"];
        }
        $DBRESULT->closeCursor();
        return $name;
    }

    /**
     * returns the list of actions ("create","delete","change","mass change", "enable", "disable")
     *
     * @param $id
     * @param $object_type
     *
     * @return array
     * @throws PDOException
     */
    public function listAction($id, $object_type): array
    {
        global $pearDBO;
        $list_actions = array();
        $i = 0;

        $statement = $pearDBO->prepare(
            "SELECT *
                FROM log_action
                WHERE object_id =:id
                AND object_type = :object_type ORDER BY action_log_date DESC"
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':object_type', $object_type);
        $statement->execute();
        while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
            $list_actions[$i]["action_log_id"] = $data["action_log_id"];
            $list_actions[$i]["action_log_date"] = date("Y/m/d H:i", $data["action_log_date"]);
            $list_actions[$i]["object_type"] = $data["object_type"];
            $list_actions[$i]["object_id"] = $data["object_id"];
            $list_actions[$i]["object_name"] = $data["object_name"];
            $list_actions[$i]["action_type"] = $this->replaceActiontype($data["action_type"]);
            if ($data["log_contact_id"] != 0) {
                $list_actions[$i]["log_contact_id"] = $this->getContactname($data["log_contact_id"]);
            } else {
                $list_actions[$i]["log_contact_id"] = "System";
            }
            $i++;
        }
        $statement->closeCursor();
        unset($data);
        return $list_actions;
    }

    /**
     * returns list of host for this service
     *
     * @param $service_id
     *
     * @return array|int
     * @throws PDOException
     */
    public function getHostId($service_id): array|int
    {
        global $pearDBO;

        /* Get Hosts */
        $query = "SELECT a.action_log_id, field_value 
                    FROM log_action a, log_action_modification m 
                    WHERE m.action_log_id = a.action_log_id 
                    AND field_name LIKE 'service_hPars' 
                    AND object_id = :service_id
                    AND object_type = 'service' 
                    AND field_value <> ''
                    ORDER BY action_log_date DESC 
                    LIMIT 1";
        $statement = $pearDBO->prepare($query);
        $statement->bindValue(':service_id', $service_id, PDO::PARAM_INT);
        $statement->execute();
        $info = $statement->fetch(PDO::FETCH_ASSOC);
        if (isset($info['field_value']) && $info['field_value'] != '') {
            return array('h' => $info['field_value']);
        }

        /* Get hostgroups */
        $query = "SELECT a.action_log_id, field_value 
                    FROM log_action a, log_action_modification m 
                    WHERE m.action_log_id = a.action_log_id 
                    AND field_name LIKE 'service_hgPars' 
                    AND object_id = :service_id
                    AND object_type = 'service'
                    AND field_value <> '' 
                    ORDER BY action_log_date DESC 
                    LIMIT 1";
        $statement = $pearDBO->prepare($query);
        $statement->bindValue(':service_id', $service_id, PDO::PARAM_INT);
        $statement->execute();
        $info = $statement->fetch(PDO::FETCH_ASSOC);
        if (isset($info['field_value']) && $info['field_value'] != '') {
            return array('hg' => $info['field_value']);
        }
        return -1;
    }

    /**
     * @param $host_id
     *
     * @return mixed
     * @throws PDOException
     */
    public function getHostName($host_id): mixed
    {
        global $pearDB, $pearDBO;

        $statement = $pearDB->prepare("SELECT host_name FROM host WHERE host_register = '1' AND host_id = :host_id");
        $statement->bindValue(':host_id', $host_id, PDO::PARAM_INT);
        $statement->execute();
        $info = $statement->fetch(PDO::FETCH_ASSOC);
        if (isset($info['host_name'])) {
            return $info['host_name'];
        }

        $statement = $pearDBO->prepare("SELECT object_id, object_name FROM log_action WHERE object_type = 'service' AND object_id = :host_id");
        $statement->bindValue(':host_id', $host_id, PDO::PARAM_INT);
        $statement->execute();
        $info = $statement->fetch(PDO::FETCH_ASSOC);
        if (isset($info['object_name'])) {
            return $info['object_name'];
        }

        $statement = $pearDBO->prepare("SELECT name FROM hosts WHERE host_id = :host_id");
        $statement->bindValue(':host_id', $host_id, PDO::PARAM_INT);
        $statement->execute();
        $info = $statement->fetch(PDO::FETCH_ASSOC);

        return $info['name'] ?? -1;
    }

    /**
     * @param $hg_id
     *
     * @return mixed
     * @throws PDOException
     */
    public function getHostGroupName($hg_id): mixed
    {
        global $pearDB, $pearDBO;

        $query = "SELECT hg_name FROM hostgroup WHERE hg_id = :hg_id";
        $DBRESULT2 = $pearDB->prepare($query);
        $DBRESULT2->bindValue(':hg_id', $hg_id, PDO::PARAM_INT);
        $DBRESULT2->execute();
        $info = $DBRESULT2->fetch(PDO::FETCH_ASSOC);
        if (isset($info['hg_name'])) {
            return $info['hg_name'];
        }

        $query = "SELECT object_id, object_name FROM log_action WHERE object_type = 'service' AND object_id = :hg_id";
        $DBRESULT2 = $pearDBO->prepare($query);
        $DBRESULT2->bindValue(':hg_id', $hg_id, PDO::PARAM_INT);
        $DBRESULT2->execute();
        $info = $DBRESULT2->fetch(PDO::FETCH_ASSOC);
        if (isset($info['object_name'])) {
            return $info['object_name'];
        }
        return -1;
    }

    /**
     * returns list of modifications
     *
     * @param int $id
     * @param string $objectType
     *
     * @return array
     * @throws PDOException
     */
    public function listModification(int $id, string $objectType): array
    {
        global $pearDBO;
        $list_modifications = [];
        $ref = [];
        $i = 0;

        $objectType = \HtmlAnalyzer::sanitizeAndRemoveTags($objectType);

        $statement1 = $pearDBO->prepare("
            SELECT action_log_id, action_log_date, action_type FROM log_action
            WHERE object_id = :object_id
            AND object_type = :object_type ORDER BY action_log_date ASC
        ");
        $statement1->bindValue(':object_id', $id, PDO::PARAM_INT);
        $statement1->bindValue(':object_type', $objectType);
        $statement1->execute();
        while ($row = $statement1->fetch(PDO::FETCH_ASSOC)) {
            $DBRESULT2 = $pearDBO->prepare(
                "SELECT action_log_id,field_name,field_value
                FROM `log_action_modification`
                WHERE action_log_id = :action_log_id"
            );
            $DBRESULT2->bindValue(':action_log_id', $row['action_log_id'], PDO::PARAM_INT);
            $DBRESULT2->execute();
            $macroPasswordStatement = $pearDBO->prepare(
                "SELECT field_value
                    FROM `log_action_modification`
                    WHERE action_log_id = :action_log_id
                    AND field_name = 'refMacroPassword'"
            );
            $macroPasswordStatement->bindValue(':action_log_id', $row['action_log_id'], PDO::PARAM_INT);
            $macroPasswordStatement->execute();
            $macroPasswordRef = [];
            if ($result = $macroPasswordStatement->fetch(PDO::FETCH_ASSOC)) {
                $macroPasswordRef = explode(',', $result['field_value']);
            }
            while ($field = $DBRESULT2->fetch(PDO::FETCH_ASSOC)) {
                switch ($field['field_name']) {
                    case 'macroValue':
                        /*
                         * explode the macroValue string to easily change any password to ****** on the "After" part
                         * of the changeLog
                         */
                        $macroValueArray = explode(',', $field['field_value']);
                        foreach ($macroPasswordRef as $macroIdPassword) {
                            if (!empty($macroValueArray[$macroIdPassword])) {
                                $macroValueArray[$macroIdPassword] = self::PASSWORD_AFTER;
                            }
                        }
                        $field['field_value'] = implode(',', $macroValueArray);
                        /*
                         * change any password to ****** on the "Before" part of the changeLog
                         * and don't change anything if the 'macroValue' string only contains commas
                         */
                        if (
                            isset($ref[$field["field_name"]])
                            && !empty(str_replace(',', '', $ref[$field["field_name"]]))
                        ) {
                            foreach ($macroPasswordRef as $macroIdPassword) {
                                $macroValueArray[$macroIdPassword] = self::PASSWORD_BEFORE;
                            }
                            $ref[$field["field_name"]] = implode(',', $macroValueArray);
                        }
                        break;
                    case 'contact_passwd':
                    case 'contact_passwd2':
                        $field['field_value'] = self::PASSWORD_AFTER;
                        if (isset($ref[$field["field_name"]])) {
                            $ref[$field["field_name"]] = self::PASSWORD_BEFORE;
                        }
                }
                if (!isset($ref[$field["field_name"]]) && $field["field_value"] != "") {
                    $list_modifications[$i]["action_log_id"] = $field["action_log_id"];
                    $list_modifications[$i]["field_name"] = $field["field_name"];
                    $list_modifications[$i]["field_value_before"] = "";
                    $list_modifications[$i]["field_value_after"] = $field["field_value"];
                    foreach ($macroPasswordRef as $macroPasswordId) {
                        // handle the display modification for the fields macroOldValue_n while nothing was set before
                        if (strpos($field["field_name"], 'macroOldValue_' . $macroPasswordId) !== false) {
                            $list_modifications[$i]["field_value_after"] = self::PASSWORD_AFTER;
                        }
                    }
                } elseif (isset($ref[$field["field_name"]]) && $ref[$field["field_name"]] != $field["field_value"]) {
                    $list_modifications[$i]["action_log_id"] = $field["action_log_id"];
                    $list_modifications[$i]["field_name"] = $field["field_name"];
                    $list_modifications[$i]["field_value_before"] = $ref[$field["field_name"]];
                    $list_modifications[$i]["field_value_after"] = $field["field_value"];
                    foreach ($macroPasswordRef as $macroPasswordId) {
                        // handle the display modification for the fields macroOldValue_n for "Before" and "After" value
                        if (strpos($field["field_name"], 'macroOldValue_' . $macroPasswordId) !== false) {
                            $list_modifications[$i]["field_value_before"] = self::PASSWORD_BEFORE;
                            $list_modifications[$i]["field_value_after"] = self::PASSWORD_AFTER;
                        }
                    }
                }
                $ref[$field["field_name"]] = $field["field_value"];
                $i++;
            }
        }
        return $list_modifications;
    }

    /**
     * Display clear action labels
     *
     * @param string $action
     *
     * @return mixed
     */
    public function replaceActiontype($action): mixed
    {
        $actionList = array();
        $actionList["d"] = "Delete";
        $actionList["c"] = "Change";
        $actionList["a"] = "Create";
        $actionList["disable"] = "Disable";
        $actionList["enable"] = "Enable";
        $actionList["mc"] = "Mass change";

        foreach ($actionList as $key => $value) {
            if ($action == $key) {
                $action = $value;
            }
        }
        return $action;
    }

    /**
     * @return array
     */
    public function listObjecttype(): array
    {
        return [
            0 => _("All"),
            1 => "command",
            2 => "timeperiod",
            3 => "contact",
            4 => "contactgroup",
            5 => "host",
            6 => "hostgroup",
            7 => "service",
            8 => "servicegroup",
            9 => "traps",
            10 => "escalation",
            11 => "host dependency",
            12 => "hostgroup dependency",
            13 => "service dependency",
            14 => "servicegroup dependency",
            15 => "poller",
            16 => "engine",
            17 => "broker",
            18 => "resources",
            19 => "meta",
            20 => "access group",
            21 => "menu access",
            22 => "resource access",
            23 => "action access",
            24 => "manufacturer",
            25 => "hostcategories"
        ];
    }

    /**
     * @param array $ret
     *
     * @return array
     */
    public static function prepareChanges($ret): array
    {
        global $pearDB;

        $uselessKey = [];
        $uselessKey['submitA'] = 1;
        $uselessKey['o'] = 1;
        $uselessKey['initialValues'] = 1;
        $uselessKey['centreon_token'] = 1;

        if (!isset($ret)) {
            return [];
        } else {
            $info = [];
            $oldMacroPassword = [];
            foreach ($ret as $key => $value) {
                if (!isset($uselessKey[trim($key)])) {
                    if (is_array($value)) {
                        /*
                         * Set a new refMacroPassword value to be able to find which macro index is a password
                         * in the listModification method and hash password in log_action_modification table
                         */
                        if ($key === 'macroValue' && isset($ret['macroPassword'])) {
                            foreach ($value as $macroId => $macroValue) {
                                if (array_key_exists($macroId, $ret['macroPassword'])) {
                                    $info['refMacroPassword'] = implode(",", array_keys($ret['macroPassword']));
                                    $value[$macroId] = md5($macroValue);
                                    if (!empty($ret['macroOldValue_' . $macroId])) {
                                        $oldMacroPassword['macroOldValue_' . $macroId] = md5(
                                            $ret['macroOldValue_' . $macroId]
                                        );
                                    }
                                }
                            }
                        }
                        if (isset($value[$key])) {
                            $info[$key] = $value[$key];
                        } else {
                            $info[$key] = implode(",", $value);
                        }
                    } else {
                        $info[$key] = CentreonDB::escape($value);
                    }
                }
            }
        }
        foreach ($oldMacroPassword as $oldMacroPasswordName => $oldMacroPasswordValue) {
            $info[$oldMacroPasswordName] = $oldMacroPasswordValue;
        }
        return $info;
    }
}
