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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

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
     * @throws ConnectionException
     * @return string
     */
    public function getTimperiodIdByName($name)
    {
        $query = 'SELECT tp_id FROM timeperiod WHERE tp_name = :name';

        $row = $this->db->fetchAssociative(
            $query,
            QueryParameters::create([QueryParameter::string('name', $name)])
        );

        if ($row === false) {
            return null;
        }

        return $row['tp_id'];
    }

    /**
     * @param int $tpId
     *
     * @throws ConnectionException
     * @return string
     */
    public function getTimeperiodException($tpId)
    {
        $query = 'SELECT `exception_id` FROM `timeperiod_exceptions` WHERE `timeperiod_id` = :timeperiodId';
        $row = $this->db->fetchAssociative(
            $query,
            QueryParameters::create([QueryParameter::int('timeperiodId', (int) $tpId)])
        );
        if ($row === false) {
            return null;
        }

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
            . 'VALUES (:name, :alias, :sunday, :monday, :tuesday, :wednesday, '
            . ':thursday, :friday, :saturday)';

        try {
            $this->db->insert(
                $sQuery,
                QueryParameters::create([
                    QueryParameter::string('name', $parameters['name']),
                    QueryParameter::string('alias', $parameters['alias']),
                    QueryParameter::string('sunday', $parameters['sunday']),
                    QueryParameter::string('monday', $parameters['monday']),
                    QueryParameter::string('tuesday', $parameters['tuesday']),
                    QueryParameter::string('wednesday', $parameters['wednesday']),
                    QueryParameter::string('thursday', $parameters['thursday']),
                    QueryParameter::string('friday', $parameters['friday']),
                    QueryParameter::string('saturday', $parameters['saturday']),
                ])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while insert timeperiod ' . $parameters['name'], 0, $e);
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

        $sQuery = 'UPDATE `timeperiod` SET `tp_alias` = :alias, '
            . '`tp_sunday` = :sunday,'
            . '`tp_monday` = :monday,'
            . '`tp_tuesday` = :tuesday,'
            . '`tp_wednesday` = :wednesday,'
            . '`tp_thursday` = :thursday,'
            . '`tp_friday` = :friday,'
            . '`tp_saturday` = :saturday'
            . ' WHERE `tp_id` = :tpId';

        try {
            $this->db->update(
                $sQuery,
                QueryParameters::create([
                    QueryParameter::string('alias', $parameters['alias']),
                    QueryParameter::string('sunday', $parameters['sunday']),
                    QueryParameter::string('monday', $parameters['monday']),
                    QueryParameter::string('tuesday', $parameters['tuesday']),
                    QueryParameter::string('wednesday', $parameters['wednesday']),
                    QueryParameter::string('thursday', $parameters['thursday']),
                    QueryParameter::string('friday', $parameters['friday']),
                    QueryParameter::string('saturday', $parameters['saturday']),
                    QueryParameter::int('tpId', (int) $tp_id),
                ])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while update timeperiod ' . $parameters['name'], 0, $e);
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
        $sQuery = 'INSERT INTO `timeperiod_exceptions` '
            . '(`timeperiod_id`, `days`, `timerange`) '
            . 'VALUES (:timeperiodId, :days, :timerange)';

        foreach ($parameters as $exception) {
            try {
                $this->db->insert(
                    $sQuery,
                    QueryParameters::create([
                        QueryParameter::int('timeperiodId', (int) $tpId),
                        QueryParameter::string('days', $exception['days']),
                        QueryParameter::string('timerange', $exception['timerange']),
                    ])
                );
            } catch (ConnectionException $e) {
                throw new Exception('Error while insert timeperiod exception' . $tpId, 0, $e);
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
            . 'VALUES (:timeperiodId, :timeperiodIncludeId)';

        try {
            $this->db->insert(
                $sQuery,
                QueryParameters::create([
                    QueryParameter::int('timeperiodId', (int) $timeperiodId),
                    QueryParameter::int('timeperiodIncludeId', (int) $depId),
                ])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while insert timeperiod dependency' . $timeperiodId, 0, $e);
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
        $sQuery = 'DELETE FROM `timeperiod_exceptions` WHERE `timeperiod_id` = :timeperiodId';

        try {
            $this->db->delete(
                $sQuery,
                QueryParameters::create([QueryParameter::int('timeperiodId', (int) $tpId)])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while delete timeperiod exception' . $tpId, 0, $e);
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
        $sQuery = 'DELETE FROM `timeperiod_include_relations` WHERE `timeperiod_id` = :timeperiodId';

        try {
            $this->db->delete(
                $sQuery,
                QueryParameters::create([QueryParameter::int('timeperiodId', (int) $tpId)])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while delete timeperiod include' . $tpId, 0, $e);
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
        $sQuery = 'DELETE FROM timeperiod WHERE tp_name = :name';

        try {
            $this->db->delete(
                $sQuery,
                QueryParameters::create([QueryParameter::string('name', $tp_name)])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while delete timperiod ' . $tp_name, 0, $e);
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
        $queryParameters = [QueryParameter::string('name', $timeperiodName)];
        if ((string) $register === '0' || (string) $register === '1') {
            $registerClause = 'AND h.host_register = :register ';
            $queryParameters[] = QueryParameter::string('register', (string) $register);
        }

        $linkedHosts = [];
        $query = 'SELECT DISTINCT h.host_name '
            . 'FROM host h, timeperiod t '
            . 'WHERE (h.timeperiod_tp_id = t.tp_id OR h.timeperiod_tp_id2 = t.tp_id) '
            . $registerClause
            . 'AND t.tp_name = :name ';

        try {
            $rows = $this->db->fetchAllAssociative($query, QueryParameters::create($queryParameters));
        } catch (ConnectionException $e) {
            throw new Exception('Error while getting linked hosts of ' . $timeperiodName, 0, $e);
        }

        foreach ($rows as $row) {
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
        $queryParameters = [QueryParameter::string('name', $timeperiodName)];
        if ((string) $register === '0' || (string) $register === '1') {
            $registerClause = 'AND s.service_register = :register ';
            $queryParameters[] = QueryParameter::string('register', (string) $register);
        }

        $linkedServices = [];
        $query = 'SELECT DISTINCT s.service_description '
            . 'FROM service s, timeperiod t '
            . 'WHERE (s.timeperiod_tp_id = t.tp_id OR s.timeperiod_tp_id2 = t.tp_id) '
            . $registerClause
            . 'AND t.tp_name = :name ';

        try {
            $rows = $this->db->fetchAllAssociative($query, QueryParameters::create($queryParameters));
        } catch (ConnectionException $e) {
            throw new Exception('Error while getting linked services of ' . $timeperiodName, 0, $e);
        }

        foreach ($rows as $row) {
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
            . 'AND t.tp_name = :name ';

        try {
            $rows = $this->db->fetchAllAssociative(
                $query,
                QueryParameters::create([QueryParameter::string('name', $timeperiodName)])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while getting linked contacts of ' . $timeperiodName, 0, $e);
        }

        foreach ($rows as $row) {
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
            . 'AND t2.tp_name = :name1 '
            . 'UNION '
            . 'SELECT DISTINCT t3.tp_name '
            . 'FROM timeperiod t3, timeperiod_include_relations tir2, timeperiod t4 '
            . 'WHERE t3.tp_id = tir2.timeperiod_include_id '
            . 'AND t4.tp_id = tir2.timeperiod_id '
            . 'AND t4.tp_name = :name2 ';

        try {
            $rows = $this->db->fetchAllAssociative(
                $query,
                QueryParameters::create([
                    QueryParameter::string('name1', $timeperiodName),
                    QueryParameter::string('name2', $timeperiodName),
                ])
            );
        } catch (ConnectionException $e) {
            throw new Exception('Error while getting linked timeperiods of ' . $timeperiodName, 0, $e);
        }

        foreach ($rows as $row) {
            $linkedTimeperiods[] = $row['tp_name'];
        }

        return $linkedTimeperiods;
    }
}
