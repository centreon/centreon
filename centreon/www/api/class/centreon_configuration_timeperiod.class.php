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
 * @class CentreonConfigurationTimeperiod
 */
class CentreonConfigurationTimeperiod extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationTimeperiod constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a list of time periods as a source of data for the select2 widget
     *
     * @throws PDOException
     * @throws RestBadRequestException If some parameter is missing will throw this exception
     * @return array
     */
    public function getList()
    {
        $queryWhere = [];
        $queryValues = [];
        // Check for select2 'q' argument
        if (isset($this->arguments['q'])) {
            $queryWhere[] = 'tp_name LIKE :name';
            $queryValues['name'] = [
                PDO::PARAM_STR => "%{$this->arguments['q']}%",
            ];
        }

        // exclude some values from the result
        if (isset($this->arguments['exclude'])) {
            $queryWhere[] = 'tp_id <> :exclude';
            $queryValues['exclude'] = [
                PDO::PARAM_INT => (int) $this->arguments['exclude'],
            ];
        }

        $queryTimePeriod = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT tp_id, tp_name FROM timeperiod '
            . ($queryWhere ? 'WHERE ' . join(' AND ', $queryWhere) : '')
            . ' ORDER BY tp_name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }

            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];

            $queryTimePeriod .= 'LIMIT :offset, :limit';

            $queryValues['offset'] = [
                PDO::PARAM_INT => (int) $offset,
            ];
            $queryValues['limit'] = [
                PDO::PARAM_INT => (int) $this->arguments['page_limit'],
            ];
        }

        $stmt = $this->pearDB->prepare($queryTimePeriod);

        foreach ($queryValues as $bindId => $bindData) {
            foreach ($bindData as $bindType => $bindValue) {
                $stmt->bindValue($bindId, $bindValue, $bindType);
                break;
            }
        }

        $stmt->execute();
        $timePeriodList = [];

        while ($data = $stmt->fetch()) {
            $timePeriodList[] = [
                'id' => $data['tp_id'],
                'text' => html_entity_decode($data['tp_name']),
            ];
        }

        return [
            'items' => $timePeriodList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }
}
