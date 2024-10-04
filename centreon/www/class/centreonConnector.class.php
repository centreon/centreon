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

/*
 *  Class that contains various methods for managing connectors
 *
 * Usage example:
 *
 * <?php
 * require_once realpath(dirname(__FILE__) . "/../../config/centreon.config.php");
 * require_once _CENTREON_PATH_ . 'www/class/centreonConnector.class.php';
 * require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
 *
 * $connector = new CentreonConnector(new CentreonDB);
 *
 * //$connector->create(array(
 * //    'name' => 'jackyse',
 * //    'description' => 'some jacky',
 * //    'command_line' => 'ls -la',
 * //    'enabled' => true
 * //        ), true);
 *
 * //$connector->update(10, array(
 * //    'name' => 'soapy',
 * //    'description' => 'Lorem ipsum',
 * //    'enabled' => true,
 * //    'command_line' => 'ls -laph --color'
 * //));
 *
 * //$connector->getList(false, 20, false);
 *
 * //$connector->delete(10);
 *
 * //$connector->read(7);
 *
 * //$connector->copy(1, 5, true);
 *
 * //$connector->count(false);
 *
 * //$connector->isNameAvailable('norExists');
 */

/**
 * Class
 *
 * @class CentreonConnector
 */
class CentreonConnector
{

    /** @var CentreonDB */
    protected $dbConnection;

    /**
     * CentreonConnector constructor
     *
     * @param CentreonDB $dbConnection
     */
    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Adds a connector to the database
     *
     * @param array $connector
     * @param bool $returnId
     * @return CentreonConnector|int
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $connector, $returnId = false)
    {
        /*
         * Checking data
         */
        if (!isset($connector['name'])) {
            throw new InvalidArgumentException('No name for the connector set');
        }

        if (empty($connector['name'])) {
            throw new InvalidArgumentException('Empty name for the connector');
        }

        if (!array_key_exists('description', $connector)) {
            $connector['description'] = null;
        }

        if (!array_key_exists('command_line', $connector)) {
            $connector['command_line'] = null;
        }

        if (!array_key_exists('enabled', $connector)) {
            $connector['enabled'] = true;
        }

        /*
         * Inserting into database
         */
        try {
            $success = $this->dbConnection->prepare(
                <<<SQL
                    INSERT INTO `connector` (
                                `name`,
                                `description`,
                                `command_line`,
                                `enabled`,
                                `created`,
                                `modified`
                                ) VALUES (?, ?, ?, ?, ?, ?)
                    SQL
            );
            $success->execute([
                $connector['name'],
                $connector['description'],
                $connector['command_line'],
                $connector['enabled'],
                $now = time(),
                $now
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Cannot insert connector; Check the database schema');
        }

        /*
         * in case last inserted id needed
         */
        if ($returnId) {
            try {
                $lastIdQueryResult = $this->dbConnection->prepare(
                    'SELECT `id` FROM `connector` WHERE `name` = ? LIMIT 1'
                );
                $lastIdQueryResult->execute([$connector['name']]);
            } catch (PDOException $e) {
                throw new RuntimeException('Cannot get last insert ID');
            }
            $lastId = $lastIdQueryResult->fetchRow();
            if (!isset($lastId['id'])) {
                throw new RuntimeException('Field id for connector not selected in query or connector not inserted');
            } elseif (isset($connector["command_id"])) {
                $statement = $this->dbConnection->prepare("UPDATE `command` " .
                    "SET connector_id = :conId WHERE `command_id` = :value");
                foreach ($connector["command_id"] as $key => $value) {
                    try {
                        $statement->bindValue(':conId', (int) $lastId['id'], PDO::PARAM_INT);
                        $statement->bindValue(':value', (int) $value, PDO::PARAM_INT);
                        $statement->execute();
                    } catch (PDOException $e) {
                        throw new RuntimeException('Cannot update connector');
                    }
                }
            }
            return $lastId['id'];
        }
        return $this;
    }

    /**
     * Reads the connector
     *
     * @param int $id
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function read($id)
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Id is not integer');
        }
        try {
            $result = $this->dbConnection->prepare(
                <<<SQL
                    SELECT
                       `id`,
                       `name`,
                       `description`,
                       `command_line`,
                       `enabled`,
                       `created`,
                       `modified`
                    FROM
                       `connector`
                    WHERE
                       `id` = ?
                    LIMIT
                       1
                    SQL
            );
            $result->execute([$id]);
        } catch (PDOException $e) {
            throw new RuntimeException('Cannot select connector');
        }

        $connector = $result->fetchRow();

        $connector['id'] = (int)$connector['id'];
        $connector['enabled'] = (boolean)$connector['enabled'];
        $connector['created'] = (int)$connector['created'];
        $connector['modified'] = (int)$connector['modified'];

        $connector['command_id'] = [];
        $DBRESULT = $this->dbConnection->query("SELECT command_id FROM command WHERE connector_id = '$id'");
        while ($row = $DBRESULT->fetchRow()) {
            $connector['command_id'][] = $row["command_id"];
        }
        unset($row);
        $DBRESULT->closeCursor();

        return $connector;
    }

    /**
     * Updates connector
     *
     * @param int $connectorId
     * @param array $connector
     *
     * @throws RuntimeException
     *
     * @return CentreonConnector
     */
    public function update(int $connectorId, array $connector = []): self
    {
        if ($connector === []) {
            return $this;
        }

        try {
            $this->dbConnection->beginTransaction();
            $bindValues = [];
            $subRequest = '';

            if (isset($connector['name'])) {
                $bindValues[':name'] = $connector['name'];
                $subRequest = ', `name` = :name';
            }

            if (isset($connector['description'])) {
                $bindValues[':description'] = $connector['description'];
                $subRequest .= ', `description` = :description';
            }

            if (isset($connector['command_line'])) {
                $bindValues[':command_line'] = $connector['command_line'];
                $subRequest .= ', `command_line` = :command_line';
            }

            if (isset($connector['enabled'])) {
                $bindValues[':enabled'] = $connector['enabled'];
                $subRequest .= ', `enabled` = :enabled';
            }

            if ($bindValues !== []) {
                $bindValues[':date_now'] = time();
                $subRequest = '`modified` = :date_now' . $subRequest;

                $statement = $this->dbConnection->prepare(
                    <<<SQL
                        UPDATE `connector`
                            SET $subRequest
                        WHERE `connector`.`id` = :id
                        LIMIT 1
                        SQL
                );
                $statement->bindValue(':id', (int) $connectorId, PDO::PARAM_INT);
                foreach ($bindValues as $fieldName => $fieldValue) {
                    $statement->bindValue($fieldName, $fieldValue);
                }
                $statement->execute();
            }

            $statement = $this->dbConnection->prepare(
                "UPDATE `command` SET connector_id = NULL WHERE `connector_id` = :id"
            );
            $statement->bindValue(':id', (int) $connectorId, PDO::PARAM_INT);
            $statement->execute();

            $commandIds = $connector["command_id"] ?? [];
            foreach ($commandIds as $commandId) {
                $statement = $this->dbConnection->prepare(
                    "UPDATE `command` SET `connector_id` = :id WHERE `command_id` = :command_id"
                );
                $statement->bindValue(':id', (int) $connectorId, PDO::PARAM_INT);
                $statement->bindValue(':command_id', (int) $commandId, PDO::PARAM_INT);
                $statement->execute();
            }
            $this->dbConnection->commit();
        } catch (Throwable) {
            $this->dbConnection->rollBack();
            throw new RuntimeException('Cannot update connector');
        }

        return $this;
    }

    /**
     * Deletes connector
     *
     * @param int $id
     *
     * @return CentreonConnector
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Id should be integer');
        }
        try {
            $result = $this->dbConnection->prepare('DELETE FROM `connector` WHERE `id` = ? LIMIT 1');
            $result->execute([$id]);
        } catch (PDOException $e) {
            throw new RuntimeException('Cannot delete connector');
        }
        return $this;
    }

    /**
     * Gets list of connectors
     *
     * @param bool $onlyEnabled
     * @param int|bool $page When false all connectors are returned
     * @param int $perPage Ignored if $page == false
     * @param bool $usedByCommand
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function getList($onlyEnabled = true, $page = false, $perPage = 30, $usedByCommand = false)
    {
        /**
         * Checking parameters
         */
        if (!is_numeric($page) && $page !== false) {
            throw new InvalidArgumentException('Page number should be integer');
        }
        if (!is_numeric($perPage)) {
            throw new InvalidArgumentException('Per page parameter should be integer');
        }

        if ($page === false) {
            $restrictSql = '';
        } else {
            /**
             * Calculating offset
             */
            $offset = $page * $perPage;
            $restrictSql = " LIMIT $perPage OFFSET $offset";
        }

        $sql = "SELECT 
                    `id`,
                    `name`,
                    `description`,
                    `command_line`,
                    `enabled`,
                    `created`,
                    `modified`
                FROM
                    `connector`";
        $whereClauses = [];
        if ($onlyEnabled) {
            $whereClauses[] = " `enabled` = 1 ";
        }
        if ($usedByCommand) {
            $whereClauses[] = " `id` IN (SELECT DISTINCT `connector_id` FROM `command`) ";
        }
        foreach ($whereClauses as $i => $clause) {
            if (!$i) {
                $sql .= " WHERE ";
            } else {
                $sql .= " AND ";
            }
            $sql .= $clause;
        }
        $sql .= $restrictSql;

        try {
            $connectorsResult = $this->dbConnection->query($sql);
        } catch (PDOException $e) {
            throw new RuntimeException('Cannot select connectors');
        }
        $connectors = [];
        while ($connector = $connectorsResult->fetchRow()) {
            $connector['id'] = (int)$connector['id'];
            $connector['enabled'] = (boolean)$connector['enabled'];
            $connector['created'] = (int)$connector['created'];
            $connector['modified'] = (int)$connector['modified'];
            $connectors[] = $connector;
        }
        return $connectors;
    }

    /**
     * Copies existing connector
     *
     * @param int $id
     * @param int $numberOfcopies
     * @param bool $returnIds
     *
     * @return CentreonConnector|array
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function copy($id, $numberOfcopies = 1, $returnIds = false)
    {
        try {
            $connector = $this->read($id);
        } catch (Exception $e) {
            throw new RuntimeException('Cannot read connector', 404);
        }

        $ids = [];
        $originalName = $connector['name'];
        $suffix = 1;

        for ($i = 0; $i < $numberOfcopies; $i++) {
            $available = 0;
            while (!$available) {
                $newName = $originalName . '_' . $suffix;
                $available = $this->isNameAvailable($newName);
                ++$suffix;
            }
            try {
                $connector['name'] = $newName;
                if ($returnIds) {
                    $ids[] = $this->create($connector, true);
                } else {
                    $this->create($connector, false);
                }
            } catch (Exception $e) {
                throw new RuntimeException('Cannot write one duplicated connector', 500);
            }
        }

        if ($returnIds) {
            return $ids;
        }
        return $this;
    }

    /**
     * Counts total number of connectors
     *
     * @param bool $onlyEnabled
     * @return int
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function count($onlyEnabled = true)
    {
        if (!is_bool($onlyEnabled)) {
            throw new InvalidArgumentException('Parameter "onlyEnabled" should be boolean');
        }
        $error = false;
        try {
            if ($onlyEnabled) {
                $countResult = $this->dbConnection->query(
                    'SELECT COUNT(*) AS \'count\' FROM `connector` WHERE `enabled` = 1'
                );
            } else {
                $countResult = $this->dbConnection->query('SELECT COUNT(*) \'count\' FROM `connector`');
            }
        } catch (PDOException $e) {
            $error = true;
        }

        if ($error || !($count = $countResult->fetchRow())) {
            throw new RuntimeException('Cannot count connectors');
        }

        return $count['count'];
    }

    /**
     * Verifies if connector exists by name
     *
     * @param string $name
     * @param null $connectorId
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function isNameAvailable($name, $connectorId = null)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Name is not intrger');
        }
        if ($connectorId) {
            if (!is_numeric($connectorId)) {
                throw new InvalidArgumentException('Id is not an integer');
            }
            $existsResult = $this->dbConnection->prepare(
                'SELECT `id` FROM `connector` WHERE `id` = ? AND `name` = ? LIMIT 1'
            );
            $existsResult->execute([$connectorId, $name]);
            if ((boolean)$existsResult->fetchRow()) {
                return true;
            }
        }

        try {
            $existsResult = $this->dbConnection->prepare(
                'SELECT `id` FROM `connector` WHERE `name` = ? LIMIT 1'
            );
            $existsResult->execute([$name]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Cannot verify if connector name already in use; Query not valid; Check the database schema'
            );
        }
        return !((boolean)$existsResult->fetchRow());
    }

    /**
     *
     * @param int $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'connector';
        $parameters['currentObject']['id'] = 'connector_id';
        $parameters['currentObject']['name'] = 'connector_name';
        $parameters['currentObject']['comparator'] = 'connector_id';

        switch ($field) {
            case 'command_id':
                $parameters['type'] = 'simple';
                $parameters['reverse'] = true;
                $parameters['externalObject']['table'] = 'command';
                $parameters['externalObject']['id'] = 'command_id';
                $parameters['externalObject']['name'] = 'command_name';
                $parameters['externalObject']['comparator'] = 'command_id';
                break;
        }

        return $parameters;
    }

}
