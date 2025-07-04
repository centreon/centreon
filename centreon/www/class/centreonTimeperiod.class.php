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

/**
 * Class
 *
 * @class CentreonTimeperiod
 */
class CentreonTimeperiod
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonTimeperiod constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        $items = [];
        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':tp' . $v . ',';
                $queryValues['tp' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected timeperiods
        $query = 'SELECT tp_id, tp_name FROM timeperiod '
            . 'WHERE tp_id IN (' . $listValues . ') ORDER BY tp_name ';
        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $items[] = ['id' => $row['tp_id'], 'text' => $row['tp_name']];
        }

        return $items;
    }

    /**
     * @param string $name
     *
     * @throws PDOException
     * @return string
     */
    public function getTimperiodIdByName($name)
    {
        $query = "SELECT tp_id FROM timeperiod 
                WHERE tp_name = '" . $this->db->escape($name) . "'";

        $res = $this->db->query($query);

        if (! $res->rowCount()) {
            return null;
        }
        $row = $res->fetchRow();

        return $row['tp_id'];
    }

    /**
     * @param int $tpId
     *
     * @throws PDOException
     * @return string
     */
    public function getTimeperiodException($tpId)
    {
        $query = 'SELECT `exception_id` FROM `timeperiod_exceptions`
                WHERE `timeperiod_id` = ' . (int) $tpId;
        $res = $this->db->query($query);
        if (! $res->rowCount()) {
            return null;
        }

        $row = $res->fetchRow();

        return $row['exception_id'];
    }

    /**
     * Insert in database a command
     *
     * @param array $parameters Values to insert (command_name and command_line is mandatory)
     * @throws Exception
     */
    public function insert($parameters): void
    {
        $sQuery = 'INSERT INTO `timeperiod` '
            . '(`tp_name`, `tp_alias`, `tp_sunday`, `tp_monday`, `tp_tuesday`, `tp_wednesday`, '
            . '`tp_thursday`, `tp_friday`, `tp_saturday`) '
            . "VALUES ('" . $parameters['name'] . "',"
            . "'" . $parameters['alias'] . "',"
            . "'" . $parameters['sunday'] . "',"
            . "'" . $parameters['monday'] . "',"
            . "'" . $parameters['tuesday'] . "',"
            . "'" . $parameters['wednesday'] . "',"
            . "'" . $parameters['thursday'] . "',"
            . "'" . $parameters['friday'] . "',"
            . "'" . $parameters['saturday'] . "')";

        try {
            $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while insert timeperiod ' . $parameters['name']);
        }
    }

    /**
     * Update in database a command
     *
     * @param string|int $tp_id
     * @param array $parameters
     *
     * @throws Exception
     * @return void
     */
    public function update($tp_id, $parameters): void
    {

        $sQuery = "UPDATE `timeperiod` SET `tp_alias` = '" . $parameters['alias'] . "', "
            . "`tp_sunday` = '" . $parameters['sunday'] . "',"
            . "`tp_monday` = '" . $parameters['monday'] . "',"
            . "`tp_tuesday` = '" . $parameters['tuesday'] . "',"
            . "`tp_wednesday` = '" . $parameters['wednesday'] . "',"
            . "`tp_thursday` = '" . $parameters['thursday'] . "',"
            . "`tp_friday` = '" . $parameters['friday'] . "',"
            . "`tp_saturday` = '" . $parameters['saturday'] . "'"
            . ' WHERE `tp_id` = ' . $tp_id;

        try {
            $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while update timeperiod ' . $parameters['name']);
        }
    }

    /**
     * Insert in database a timeperiod exception
     *
     * @param int $tpId
     * @param array $parameters Values to insert (days and timerange)
     * @throws Exception
     */
    public function setTimeperiodException($tpId, $parameters): void
    {
        foreach ($parameters as $exception) {
            $sQuery = 'INSERT INTO `timeperiod_exceptions` '
                . '(`timeperiod_id`, `days`, `timerange`) '
                . 'VALUES (' . (int) $tpId . ','
                . "'" . $exception['days'] . "',"
                . "'" . $exception['timerange'] . "')";

            try {
                $this->db->query($sQuery);
            } catch (PDOException $e) {
                throw new Exception('Error while insert timeperiod exception' . $tpId);
            }
        }
    }

    /**
     * Insert in database a timeperiod dependency
     *
     * @param int $timeperiodId
     * @param int $depId
     * @throws Exception
     */
    public function setTimeperiodDependency($timeperiodId, $depId): void
    {
        $sQuery = 'INSERT INTO `timeperiod_include_relations` '
            . '(`timeperiod_id`,`timeperiod_include_id`) '
            . 'VALUES (' . (int) $timeperiodId . ',' . (int) $depId . ')';

        try {
            $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while insert timeperiod dependency' . $timeperiodId);
        }
    }

    /**
     * Delete in database a timeperiod exception
     *
     * @param int $tpId
     * @throws Exception
     */
    public function deleteTimeperiodException($tpId): void
    {
        $sQuery = 'DELETE FROM `timeperiod_exceptions` WHERE `timeperiod_id` = ' . (int) $tpId;

        try {
            $res = $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while delete timeperiod exception' . $tpId);
        }
    }

    /**
     * Delete in database a timeperiod include
     *
     * @param int $tpId
     * @throws Exception
     */
    public function deleteTimeperiodInclude($tpId): void
    {
        $sQuery = 'DELETE FROM `timeperiod_include_relations` WHERE `timeperiod_id` = ' . (int) $tpId;

        try {
            $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while delete timeperiod include' . $tpId);
        }
    }

    /**
     * Delete timeperiod in database
     *
     * @param string $tp_name timperiod name
     * @throws Exception
     */
    public function deleteTimeperiodByName($tp_name): void
    {
        $sQuery = 'DELETE FROM timeperiod '
            . 'WHERE tp_name = "' . $this->db->escape($tp_name) . '"';

        try {
            $this->db->query($sQuery);
        } catch (PDOException $e) {
            throw new Exception('Error while delete timperiod ' . $tp_name);
        }
    }

    /**
     * Returns array of Host linked to the timeperiod
     *
     * @param string $timeperiodName
     * @param bool $register
     *
     * @throws Exception
     * @return array
     */
    public function getLinkedHostsByName($timeperiodName, $register = false)
    {
        $registerClause = '';
        if ($register === '0' || $register === '1') {
            $registerClause = 'AND h.host_register = "' . $register . '" ';
        }

        $linkedHosts = [];
        $query = 'SELECT DISTINCT h.host_name '
            . 'FROM host h, timeperiod t '
            . 'WHERE (h.timeperiod_tp_id = t.tp_id OR h.timeperiod_tp_id2 = t.tp_id) '
            . $registerClause
            . 'AND t.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        try {
            $result = $this->db->query($query);
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked hosts of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedHosts[] = $row['host_name'];
        }

        return $linkedHosts;
    }

    /**
     * Returns array of Service linked to the timeperiod
     *
     * @param string $timeperiodName
     * @param bool $register
     *
     * @throws Exception
     * @return array
     */
    public function getLinkedServicesByName($timeperiodName, $register = false)
    {
        $registerClause = '';
        if ($register === '0' || $register === '1') {
            $registerClause = 'AND s.service_register = "' . $register . '" ';
        }

        $linkedServices = [];
        $query = 'SELECT DISTINCT s.service_description '
            . 'FROM service s, timeperiod t '
            . 'WHERE (s.timeperiod_tp_id = t.tp_id OR s.timeperiod_tp_id2 = t.tp_id) '
            . $registerClause
            . 'AND t.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        try {
            $result = $this->db->query($query);
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked services of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedServices[] = $row['service_description'];
        }

        return $linkedServices;
    }

    /**
     * Returns array of Contacts linked to the timeperiod
     *
     * @param string $timeperiodName
     * @throws Exception
     * @return array
     */
    public function getLinkedContactsByName($timeperiodName)
    {
        $linkedContacts = [];
        $query = 'SELECT DISTINCT c.contact_name '
            . 'FROM contact c, timeperiod t '
            . 'WHERE (c.timeperiod_tp_id = t.tp_id OR c.timeperiod_tp_id2 = t.tp_id) '
            . 'AND t.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        try {
            $result = $this->db->query($query);
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked contacts of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedContacts[] = $row['contact_name'];
        }

        return $linkedContacts;
    }

    /**
     * Returns array of Timeperiods linked to the timeperiod
     *
     * @param string $timeperiodName
     * @throws Exception
     * @return array
     */
    public function getLinkedTimeperiodsByName($timeperiodName)
    {
        $linkedTimeperiods = [];

        $query = 'SELECT DISTINCT t1.tp_name '
            . 'FROM timeperiod t1, timeperiod_include_relations tir1, timeperiod t2 '
            . 'WHERE t1.tp_id = tir1.timeperiod_id '
            . 'AND t2.tp_id = tir1.timeperiod_include_id '
            . 'AND t2.tp_name = "' . $this->db->escape($timeperiodName) . '" '
            . 'UNION '
            . 'SELECT DISTINCT t3.tp_name '
            . 'FROM timeperiod t3, timeperiod_include_relations tir2, timeperiod t4 '
            . 'WHERE t3.tp_id = tir2.timeperiod_include_id '
            . 'AND t4.tp_id = tir2.timeperiod_id '
            . 'AND t4.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        try {
            $result = $this->db->query($query);
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked timeperiods of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedTimeperiods[] = $row['tp_name'];
        }

        return $linkedTimeperiods;
    }
}
