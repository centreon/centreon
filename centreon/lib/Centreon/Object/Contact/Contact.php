<?php

/*
 * Copyright 2005-2021 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once __DIR__ . '/../Object.php';
require_once __DIR__ . '/../../../../www/class/centreonContact.class.php';


/**
 * Class
 *
 * @class Centreon_Object_Contact
 */
class Centreon_Object_Contact extends \Centreon_Object
{
    /** @var string */
    protected $table = "contact";
    /** @var string */
    protected $primaryKey = "contact_id";
    /** @var string */
    protected $uniqueLabelField = "contact_alias";

    /**
     * @param $params
     *
     * @return false|string|null
     * @throws PDOException
     */
    public function insert($params = [])
    {
        $sql = "INSERT INTO $this->table ";
        $sqlFields = "";
        $sqlValues = "";
        $sqlParams = [];

        // Store password value and remove it from the array to not inserting it in contact table.
        if (isset($params['contact_passwd'])) {
            $password = $params['contact_passwd'];
            unset($params['contact_passwd']);
        }
        foreach ($params as $key => $value) {
            if ($key == $this->primaryKey) {
                continue;
            }
            if ($sqlFields != "") {
                $sqlFields .= ",";
            }
            if ($sqlValues != "") {
                $sqlValues .= ",";
            }
            $sqlFields .= $key;
            $sqlValues .= "?";
            $sqlParams[] = trim($value);
        }
        if ($sqlFields && $sqlValues) {
            $sql .= "(" . $sqlFields . ") VALUES (" . $sqlValues . ")";
            $this->db->query($sql, $sqlParams);
            $contactId = $this->db->lastInsertId();
            if (isset($password) && isset($contactId)) {
                $contact = new \CentreonContact($this->db);
                $contact->addPasswordByContactId($contactId, $password);
            }
            return $contactId;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        $parameterNames = "*",
        $count = -1,
        $offset = 0,
        $order = null,
        $sort = "ASC",
        $filters = [],
        $filterType = "OR"
    ) {
        if ($filterType != "OR" && $filterType != "AND") {
            throw new Exception('Unknown filter type');
        }

        if (is_array($parameterNames)) {
            if (($key = array_search('contact_id', $parameterNames)) !== false) {
                $parameterNames[$key] = $this->table . '.contact_id';
            }
            $params = implode(",", $parameterNames);
        } elseif ($parameterNames === "contact_id") {
            $params = $this->table . '.contact_id';
        } else {
            $params = $parameterNames;
        }
        $sql = "SELECT $params FROM $this->table";
        $filterTab = [];
        if (count($filters)) {
            foreach ($filters as $key => $rawvalue) {
                if ($filterTab === []) {
                    $sql .= " WHERE $key ";
                } else {
                    $sql .= " $filterType $key ";
                }
                if (is_array($rawvalue)) {
                    $sql .= ' IN (' . str_repeat('?,', count($rawvalue) - 1) . '?) ';
                    $filterTab = array_merge($filterTab, $rawvalue);
                } else {
                    $sql .= ' LIKE ? ';
                    $value = trim($rawvalue);
                    $value = str_replace("\\", "\\\\", $value);
                    $value = str_replace("_", "\_", $value);
                    $value = str_replace(" ", "\ ", $value);
                    $filterTab[] = $value;
                }
            }
        }
        if (isset($order) && isset($sort) && (strtoupper($sort) == "ASC" || strtoupper($sort) == "DESC")) {
            $sql .= " ORDER BY $order $sort ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->db->limit($sql, $count, $offset);
        }

        $contacts = $this->getResult($sql, $filterTab, "fetchAll");
        foreach ($contacts as &$contact) {
            $statement = $this->db->prepare(
                "SELECT password FROM contact_password WHERE contact_id = :contactId " .
                "ORDER BY creation_date DESC LIMIT 1"
            );
            $statement->bindValue(':contactId', $contact['contact_id'], \PDO::PARAM_INT);
            $statement->execute();
            $contact['contact_passwd'] = ($result = $statement->fetch(\PDO::FETCH_ASSOC)) ? $result['password'] : null;
        }

        return $contacts;
    }

    /**
     * @inheritDoc
     */
    public function update($contactId, $params = []): void
    {
        $sql = "UPDATE $this->table SET ";
        $sqlUpdate = "";
        $sqlParams = [];
        $not_null_attributes = [];

        // Store password value and remove it from the array to not inserting it in contact table.
        if (isset($params['contact_passwd'])) {
            $password = $params['contact_passwd'];
            unset($params['contact_passwd']);
        }
        if (isset($params['contact_autologin_key'])) {
            $statement = $this->db->prepare(
                "SELECT password FROM contact_password WHERE contact_id = :contactId " .
                "ORDER BY creation_date DESC LIMIT 1"
            );
            $statement->bindValue(':contactId', $contactId, \PDO::PARAM_INT);
            $statement->execute();
            if (
                ($result = $statement->fetch(\PDO::FETCH_ASSOC))
                && password_verify($params['contact_autologin_key'], $result['password'])
            ) {
                throw new \Exception(_('Your autologin key must be different than your current password'));
            }
        }

        if (array_search("", $params)) {
            $sql_attr = "SHOW FIELDS FROM $this->table";
            $res = $this->getResult($sql_attr, [], "fetchAll");
            foreach ($res as $tab) {
                if ($tab['Null'] == 'NO') {
                    $not_null_attributes[$tab['Field']] = true;
                }
            }
        }

        foreach ($params as $key => $value) {
            if ($key == $this->primaryKey) {
                continue;
            }
            if ($sqlUpdate != "") {
                $sqlUpdate .= ",";
            }
            $sqlUpdate .= $key . " = ? ";
            if ($value === "" && !isset($not_null_attributes[$key])) {
                $value = null;
            }
            if (!is_null($value)) {
                $value = str_replace("<br/>", "\n", $value);
            }
            $sqlParams[] = $value;
        }

        if ($sqlUpdate) {
            $sqlParams[] = $contactId;
            $sql .= $sqlUpdate . " WHERE $this->primaryKey = ?";
            $this->db->query($sql, $sqlParams);
        }

        if (isset($password) && isset($contactId)) {
            $contact = new \CentreonContact($this->db);
            $contact->renewPasswordByContactId($contactId, $password);
        }
    }
}
