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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationCommand
 */
class CentreonConfigurationCommand extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationCommand constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        $queryValues = [];
        // Check for select2 'q' argument
        if (false === isset($this->arguments['q'])) {
            $queryValues['commandName'] = '%%';
        } else {
            $queryValues['commandName'] = '%' . (string) $this->arguments['q'] . '%';
        }

        if (isset($this->arguments['t'])) {
            if (! is_numeric($this->arguments['t'])) {
                throw new RestBadRequestException('Error, command type must be numerical');
            }
            $queryCommandType = 'AND command_type = :commandType ';
            $queryValues['commandType'] = (int) $this->arguments['t'];
        } else {
            $queryCommandType = '';
        }

        $queryCommand = 'SELECT SQL_CALC_FOUND_ROWS command_id, command_name '
            . 'FROM command '
            . 'WHERE command_name LIKE :commandName AND command_activate = "1" '
            . $queryCommandType
            . 'ORDER BY command_name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryCommand .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($queryCommand);
        $stmt->bindParam(':commandName', $queryValues['commandName'], PDO::PARAM_STR);
        if (isset($queryValues['commandType'])) {
            $stmt->bindParam(':commandType', $queryValues['commandType'], PDO::PARAM_INT);
        }
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();

        $commandList = [];
        while ($data = $stmt->fetch()) {
            $commandList[] = ['id' => $data['command_id'], 'text' => $data['command_name']];
        }

        return ['items' => $commandList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
