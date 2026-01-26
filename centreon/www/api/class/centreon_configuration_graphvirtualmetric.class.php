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
 * @class CentreonConfigurationGraphvirtualmetric
 */
class CentreonConfigurationGraphvirtualmetric extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationGraphvirtualmetric constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $currentObject
     * @param int $id
     * @param string $field
     * @throws Exception
     * @return array
     */
    protected function retrieveSimpleValues($currentObject, $id, $field)
    {
        $tmpValues = [];
        // Getting Current Values
        $query = 'SELECT id.host_id, id.service_id '
            . 'FROM ' . dbcstg . '.index_data id, virtual_metrics vm '
            . 'WHERE id.id = vm.index_id '
            . 'AND vm.vmetric_id = :metricId ';

        $stmt = $this->pearDB->prepare($query);
        $stmt->bindParam(':metricId', $id, PDO::PARAM_INT);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }
        while ($row = $stmt->fetch()) {
            $tmpValues[] = $row['host_id'] . '-' . $row['service_id'];
        }

        return $tmpValues;
    }
}
