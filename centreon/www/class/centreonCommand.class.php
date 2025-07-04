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
 * @class CentreonCommand
 */
class CentreonCommand
{
    /** @var CentreonDB */
    protected $db;

    /** @var string[] */
    public $aTypeMacro = ['1' => 'HOST', '2' => 'SERVICE'];

    /** @var array[] */
    public $aTypeCommand = ['host' => ['key' => '$_HOST', 'preg' => '/\$_HOST([\w_-]+)\$/'], 'service' => ['key' => '$_SERVICE', 'preg' => '/\$_SERVICE([\w_-]+)\$/']];

    /**
     * CentreonCommand constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param $commandType
     * @throws Exception
     * @return array
     */
    protected function getCommandList($commandType)
    {
        $query = 'SELECT command_id, command_name '
            . 'FROM command '
            . 'WHERE command_type = :type '
            . 'ORDER BY command_name';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':type', $commandType, PDO::PARAM_INT);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }
        $arr = [];
        while ($row = $stmt->fetch()) {
            $arr[$row['command_id']] = $row['command_name'];
        }

        return $arr;
    }

    /**
     * Get list of check commands
     *
     * @throws Exception
     * @return array
     */
    public function getCheckCommands()
    {
        return $this->getCommandList(2);
    }

    /**
     * Get list of notification commands
     *
     * @throws Exception
     * @return array
     */
    public function getNotificationCommands()
    {
        return $this->getCommandList(1);
    }

    /**
     * Get list of misc commands
     *
     * @throws Exception
     * @return array
     */
    public function getMiscCommands()
    {
        return $this->getCommandList(3);
    }

    /**
     * Returns array of locked commands
     *
     * @throws PDOException
     * @return array
     */
    public function getLockedCommands()
    {
        static $arr = null;
        if (is_null($arr)) {
            $arr = [];
            $res = $this->db->query('SELECT command_id FROM command WHERE command_locked = 1');
            while ($row = $res->fetch()) {
                $arr[$row['command_id']] = true;
            }
        }

        return $arr;
    }

    /**
     * @param $iIdCommand
     * @param $sType
     * @param int $iWithFormatData
     * @throws Exception
     * @return array
     */
    public function getMacroByIdAndType($iIdCommand, $sType, $iWithFormatData = 1)
    {
        $macroToFilter = ['SNMPVERSION', 'SNMPCOMMUNITY'];
        if (empty($iIdCommand) || ! array_key_exists($sType, $this->aTypeCommand)) {
            return [];
        }
        $aDescription = $this->getMacroDescription($iIdCommand);
        $query = 'SELECT command_id, command_name, command_line '
            . 'FROM command '
            . 'WHERE command_type = 2 '
            . 'AND command_id = :id '
            . 'AND command_line like :command '
            . 'ORDER BY command_name';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $iIdCommand, PDO::PARAM_INT);
        $commandLine = '%' . $this->aTypeCommand[$sType]['key'] . '%';
        $stmt->bindParam(':command', $commandLine, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        $arr = [];
        $i = 0;
        if ($iWithFormatData == 1) {
            while ($row = $stmt->fetch()) {
                preg_match_all($this->aTypeCommand[$sType]['preg'], $row['command_line'], $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    if (! in_array($match[1], $macroToFilter)) {
                        $sName = $match[1];
                        $sDesc = $aDescription[$sName]['description'] ?? '';
                        $arr[$i]['macroInput_#index#'] = $sName;
                        $arr[$i]['macroValue_#index#'] = '';
                        $arr[$i]['macroPassword_#index#'] = null;
                        $arr[$i]['macroDescription_#index#'] = $sDesc;
                        $arr[$i]['macroDescription'] = $sDesc;
                        $arr[$i]['macroCommandFrom'] = $row['command_name'];
                        $i++;
                    }
                }
            }
        } else {
            while ($row = $stmt->fetch()) {
                $arr[$row['command_id']] = $row['command_name'];
            }
        }

        return $arr;
    }

    /**
     * @param $iIdCmd
     * @throws Exception
     * @return array
     */
    public function getMacroDescription($iIdCmd)
    {
        $aReturn = [];
        $query = 'SELECT * FROM `on_demand_macro_command` WHERE `command_command_id` = :command';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':command', $iIdCmd, PDO::PARAM_INT);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }
        while ($row = $stmt->fetch()) {
            $arr['id'] = $row['command_macro_id'];
            $arr['name'] = $row['command_macro_name'];
            $arr['description'] = $row['command_macro_desciption'];
            $arr['type'] = $row['command_macro_type'];

            $aReturn[$row['command_macro_name']] = $arr;
        }
        $stmt->closeCursor();

        return $aReturn;
    }

    /**
     * @param $iCommandId
     * @param $aMacro
     * @param $sType
     * @throws Exception
     * @return array
     */
    public function getMacrosCommand($iCommandId, $aMacro, $sType)
    {
        $aReturn = [];

        if (count($aMacro) > 0 && array_key_exists($sType, $this->aTypeMacro)) {
            $queryValues = [];
            $explodedValues = '';

            $query = 'SELECT * FROM `on_demand_macro_command` '
                . 'WHERE command_command_id = ? '
                . 'AND command_macro_type = ? '
                . 'AND command_macro_name IN (';

            $queryValues[] = (int) $iCommandId;
            $queryValues[] = (string) $sType;
            if (! empty($aMacro)) {
                foreach ($aMacro as $k => $v) {
                    $explodedValues .= '?,';
                    $queryValues[] = (string) $v;
                }
                $explodedValues = rtrim($explodedValues, ',');
            } else {
                $explodedValues .= '""';
            }
            $query .= $explodedValues . ')';
            $stmt = $this->db->prepare($query);
            $dbResult = $stmt->execute($queryValues);
            if (! $dbResult) {
                throw new Exception('An error occured');
            }

            while ($row = $stmt->fetch()) {
                $arr['id'] = $row['command_macro_id'];
                $arr['name'] = $row['command_macro_name'];
                $arr['description'] = htmlentities($row['command_macro_desciption']);
                $arr['type'] = $sType;
                $aReturn[] = $arr;
            }
            $stmt->closeCursor();
        }

        return $aReturn;
    }

    /**
     * @param $iCommandId
     * @param $sStr
     * @param $sType
     *
     * @throws Exception
     * @return array
     */
    public function matchObject($iCommandId, $sStr, $sType)
    {
        $macros = [];
        $macrosDesc = [];

        if (array_key_exists($sType, $this->aTypeMacro)) {
            preg_match_all(
                $this->aTypeCommand[strtolower($this->aTypeMacro[$sType])]['preg'],
                $sStr,
                $matches1,
                PREG_SET_ORDER
            );

            foreach ($matches1 as $match) {
                $macros[] = $match[1];
            }

            if ($macros !== []) {
                $macrosDesc = $this->getMacrosCommand($iCommandId, $macros, $sType);
                $aNames = array_column($macrosDesc, 'name');

                foreach ($macros as $detail) {
                    if (! in_array($detail, $aNames) && ! empty($detail)) {
                        $arr['id'] = '';
                        $arr['name'] = $detail;
                        $arr['description'] = '';
                        $arr['type'] = $sType;
                        $macrosDesc[] = $arr;
                    }
                }
            }
        }

        return $macrosDesc;
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
                $listValues .= ':command' . $v . ',';
                $queryValues['command' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected connectors
        $query = 'SELECT command_id, command_name FROM command '
            . 'WHERE command_id IN (' . $listValues . ') '
            . 'ORDER BY command_name ';
        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $items[] = ['id' => $row['command_id'], 'text' => $row['command_name']];
        }

        return $items;
    }

    /**
     * @param $id
     * @param array $parameters
     * @throws Exception
     * @return array|mixed
     */
    public function getParameters($id, $parameters = [])
    {
        $queryValues = [];
        $explodedValues = '';
        $arr = [];
        if (empty($id)) {
            return [];
        }
        if (count($parameters) > 0) {
            foreach ($parameters as $k => $v) {
                $explodedValues .= "`{$v}`,";
            }
            $explodedValues = rtrim($explodedValues, ',');
        } else {
            $explodedValues = '*';
        }

        $query = 'SELECT ' . $explodedValues . ' FROM command WHERE command_id = ?';
        $queryValues[] = (int) $id;
        $stmt = $this->db->prepare($query);
        $dbResult = $stmt->execute($queryValues);
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        if ($stmt->rowCount()) {
            $arr = $stmt->fetch();
        }

        return $arr;
    }

    /**
     * @param $name
     * @throws Exception
     * @return array|mixed
     */
    public function getCommandByName($name)
    {
        $arr = [];
        $query = 'SELECT * FROM command WHERE command_name = :commandName';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':commandName', $name, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        if ($stmt->rowCount()) {
            $arr = $stmt->fetch();
        }

        return $arr;
    }

    /**
     * @param $name
     * @throws Exception
     * @return int|null
     */
    public function getCommandIdByName($name)
    {
        $query = 'SELECT command_id FROM command WHERE command_name = :commandName';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':commandName', $name, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        if (! $stmt->rowCount()) {
            return null;
        }
        $row = $stmt->fetch();

        return $row['command_id'];
    }

    /**
     * @param $parameters
     * @param bool $locked
     * @throws Exception
     */
    public function insert($parameters, $locked = false): void
    {
        $queryValues = [];
        $sQuery = 'INSERT INTO command '
            . '(command_name, command_line, command_type, command_locked) '
            . 'VALUES (';

        if (isset($parameters['command_name']) && $parameters['command_name'] != '') {
            $sQuery .= '?, ';
            $queryValues[] = (string) $parameters['command_name'];
        } else {
            $sQuery .= '"", ';
        }
        if (isset($parameters['command_line']) && $parameters['command_line'] != '') {
            $sQuery .= '?, ';
            $queryValues[] = (string) $parameters['command_line'];
        } else {
            $sQuery .= '"", ';
        }
        if (isset($parameters['command_type']) && $parameters['command_type'] != '') {
            $sQuery .= '?, ';
            $queryValues[] = (int) $parameters['command_type'];
        } else {
            $sQuery .= '2, ';
        }

        if ($locked === true) {
            $sQuery .= '1';
        } else {
            $sQuery .= '0';
        }

        $sQuery .= ')';
        $stmt = $this->db->prepare($sQuery);
        $dbResult = $stmt->execute($queryValues);
        if (! $dbResult) {
            throw new Exception('Error while insert command ' . $parameters['command_name']);
        }
    }

    /**
     * @param $commandId
     * @param $command
     * @throws Exception
     */
    public function update($commandId, $command): void
    {
        $sQuery = 'UPDATE `command` SET `command_line` = :line, `command_type` = :cType WHERE `command_id` = :id';
        $stmt = $this->db->prepare($sQuery);
        $stmt->bindParam(':line', $command['command_line'], PDO::PARAM_STR);
        $stmt->bindParam(':cType', $command['command_type'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $commandId, PDO::PARAM_INT);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('Error while update command ' . $command['command_name']);
        }
    }

    /**
     * @param $commandName
     * @throws Exception
     */
    public function deleteCommandByName($commandName): void
    {
        $sQuery = 'DELETE FROM command WHERE command_name = :commandName';
        $stmt = $this->db->prepare($sQuery);
        $stmt->bindParam(':commandName', $commandName, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('Error while delete command ' . $commandName);
        }
    }

    /**
     * @param $commandName
     * @param bool $checkTemplates
     * @throws Exception
     * @return array
     */
    public function getLinkedServicesByName($commandName, $checkTemplates = true)
    {
        $register = $checkTemplates ? 0 : 1;

        $linkedCommands = [];
        $query = 'SELECT DISTINCT s.service_description '
            . 'FROM service s, command c '
            . 'WHERE s.command_command_id = c.command_id '
            . 'AND s.service_register = :register '
            . 'AND c.command_name = :commandName ';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':register', $register, PDO::PARAM_STR);
        $stmt->bindParam(':commandName', $commandName, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('Error while getting linked services of ' . $commandName);
        }

        while ($row = $stmt->fetch()) {
            $linkedCommands[] = $row['service_description'];
        }

        return $linkedCommands;
    }

    /**
     * @param $commandName
     * @param bool $checkTemplates
     * @throws Exception
     * @return array
     */
    public function getLinkedHostsByName($commandName, $checkTemplates = true)
    {
        $register = $checkTemplates ? 0 : 1;

        $linkedCommands = [];
        $query = 'SELECT DISTINCT h.host_name '
            . 'FROM host h, command c '
            . 'WHERE h.command_command_id = c.command_id '
            . 'AND h.host_register = :register '
            . 'AND c.command_name = :commandName ';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':register', $register, PDO::PARAM_STR);
        $stmt->bindParam(':commandName', $commandName, PDO::PARAM_STR);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('Error while getting linked hosts of ' . $commandName);
        }

        while ($row = $stmt->fetch()) {
            $linkedCommands[] = $row['host_name'];
        }

        return $linkedCommands;
    }
}
