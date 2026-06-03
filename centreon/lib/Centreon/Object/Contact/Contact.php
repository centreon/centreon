<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
class Centreon_Object_Contact extends Centreon_Object
{
    /** @var string */
    protected $table = 'contact';

    /** @var string */
    protected $primaryKey = 'contact_id';

    /** @var string */
    protected $uniqueLabelField = 'contact_alias';

    /**
     * @param $params
     *
     * @throws PDOException
     * @return false|string|null
     */
    public function insert($params = [])
    {
        if (isset($params['contact_passwd'])) {
            $password = $params['contact_passwd'];
            unset($params['contact_passwd']);
        }

        $fields = [];
        $placeholders = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            $key = $this->sanitizeIdentifier($key);
            if ($key == $this->primaryKey) {
                continue;
            }
            $fields[] = $key;
            $placeholders[] = ':' . $key;
            $bindParams[':' . $key] = trim($value);
        }

        if ($fields === []) {
            return null;
        }

        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ') VALUES ('
            . implode(',', $placeholders) . ')';
        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $paramName => $paramValue) {
            $statement->bindValue($paramName, $paramValue);
        }
        $statement->execute();

        $contactId = $this->db->lastInsertId();
        if (isset($password, $contactId)) {
            $contact = new CentreonContact($this->db);
            $contact->addPasswordByContactId($contactId, $password);
        }

        return $contactId;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        $parameterNames = '*',
        $count = -1,
        $offset = 0,
        $order = null,
        $sort = 'ASC',
        $filters = [],
        $filterType = 'OR',
    ) {
        if ($filterType != 'OR' && $filterType != 'AND') {
            throw new Exception('Unknown filter type');
        }

        if (is_array($parameterNames)) {
            $parameterNames = array_map([$this, 'sanitizeIdentifier'], $parameterNames);
            if (($key = array_search('contact_id', $parameterNames)) !== false) {
                $parameterNames[$key] = $this->table . '.contact_id';
            }
            $params = implode(',', $parameterNames);
        } elseif ($parameterNames === 'contact_id') {
            $params = $this->table . '.contact_id';
        } else {
            $params = $parameterNames !== '*' ? $this->sanitizeIdentifier($parameterNames) : $parameterNames;
        }
        $sql = "SELECT {$params} FROM {$this->table}";
        $bindParams = [];
        $filterIndex = 0;
        $whereClauses = [];
        foreach ($filters as $key => $rawvalue) {
            $key = $this->sanitizeIdentifier($key);
            if (is_array($rawvalue)) {
                if ($rawvalue === []) {
                    $whereClauses[] = '1 = 0';
                    continue;
                }
                $inPlaceholders = [];
                foreach ($rawvalue as $inValue) {
                    $paramName = ':filter_' . $filterIndex++;
                    $inPlaceholders[] = $paramName;
                    $bindParams[$paramName] = $inValue;
                }
                $whereClauses[] = "{$key} IN (" . implode(',', $inPlaceholders) . ')';
            } else {
                $paramName = ':filter_' . $filterIndex++;
                $value = trim($rawvalue);
                $value = str_replace('\\', '\\\\', $value);
                $value = str_replace('_', "\_", $value);
                $value = str_replace(' ', "\ ", $value);
                $whereClauses[] = "{$key} LIKE {$paramName}";
                $bindParams[$paramName] = $value;
            }
        }
        if ($whereClauses !== []) {
            $sql .= ' WHERE ' . implode(" {$filterType} ", $whereClauses);
        }
        if (isset($order, $sort) && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $order = $this->sanitizeIdentifier($order);
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->db->limit($sql, $count, $offset);
        }

        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $paramName => $paramValue) {
            $statement->bindValue($paramName, $paramValue);
        }
        $statement->execute();
        $contacts = $statement->fetchAll();
        foreach ($contacts as &$contact) {
            if (! isset($contact['contact_id'])) {
                continue;
            }
            $statement = $this->db->prepare(
                'SELECT password FROM contact_password WHERE contact_id = :contactId '
                . 'ORDER BY creation_date DESC LIMIT 1'
            );
            $statement->bindValue(':contactId', $contact['contact_id'], PDO::PARAM_INT);
            $statement->execute();
            $contact['contact_passwd'] = ($result = $statement->fetch(PDO::FETCH_ASSOC)) ? $result['password'] : null;
        }

        return $contacts;
    }

    /**
     * @inheritDoc
     */
    public function update($contactId, $params = []): void
    {
        $notNullAttributes = [];

        // Store password value and remove it from the array to not inserting it in contact table.
        if (isset($params['contact_passwd'])) {
            $password = $params['contact_passwd'];
            unset($params['contact_passwd']);
        }
        if (isset($params['contact_autologin_key'])) {
            $statement = $this->db->prepare(
                'SELECT password FROM contact_password WHERE contact_id = :contactId '
                . 'ORDER BY creation_date DESC LIMIT 1'
            );
            $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
            $statement->execute();
            if (
                ($result = $statement->fetch(PDO::FETCH_ASSOC))
                && password_verify($params['contact_autologin_key'], $result['password'])
            ) {
                throw new Exception(_('Your autologin key must be different than your current password'));
            }
        }

        if (array_search('', $params, true) !== false) {
            $res = $this->getResult("SHOW FIELDS FROM {$this->table}", [], 'fetchAll');
            foreach ($res as $tab) {
                if ($tab['Null'] == 'NO') {
                    $notNullAttributes[$tab['Field']] = true;
                }
            }
        }

        $setClauses = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            $key = $this->sanitizeIdentifier($key);
            if ($key == $this->primaryKey) {
                continue;
            }
            $setClauses[] = $key . ' = :' . $key;
            if ($value === '' && ! isset($notNullAttributes[$key])) {
                $value = null;
            }
            if (! is_null($value)) {
                $value = str_replace('<br/>', "\n", $value);
            }
            $bindParams[':' . $key] = $value;
        }

        if ($setClauses !== []) {
            $sql = "UPDATE {$this->table} SET " . implode(',', $setClauses)
                . " WHERE {$this->primaryKey} = :primaryKeyId";
            $statement = $this->db->prepare($sql);
            foreach ($bindParams as $paramName => $paramValue) {
                $statement->bindValue($paramName, $paramValue);
            }
            $statement->bindValue(':primaryKeyId', $contactId, PDO::PARAM_INT);
            $statement->execute();
        }

        if (isset($password, $contactId)) {
            $contact = new CentreonContact($this->db);
            $contact->renewPasswordByContactId($contactId, $password);
        }
    }
}
