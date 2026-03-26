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
 * @class CentreonServicecategories
 */
class CentreonServicecategories
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonServicecategories constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->db = $pearDB;
    }

    /**
     * @param int $field
     *
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'service_categories';
        $parameters['currentObject']['id'] = 'sc_id';
        $parameters['currentObject']['name'] = 'sc_name';
        $parameters['currentObject']['comparator'] = 'sc_id';

        switch ($field) {
            case 'sc_svcTpl':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonServicetemplates';
                $parameters['relationObject']['table'] = 'service_categories_relation';
                $parameters['relationObject']['field'] = 'service_service_id';
                $parameters['relationObject']['comparator'] = 'sc_id';
                break;
        }

        return $parameters;
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
        global $centreon;
        $items = [];

        // get list of authorized service categories
        if (! $centreon->user->access->admin) {
            $scAcl = $centreon->user->access->getServiceCategories();
        }

        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $multiValues = explode(',', $v);
                foreach ($multiValues as $item) {
                    $queryValues[':sc_' . $item] = (int) $item;
                }
            }
        }

        // get list of selected service categories
        $query = 'SELECT sc_id, sc_name FROM service_categories '
            . 'WHERE sc_id IN ('
            . (count($queryValues) ? implode(',', array_keys($queryValues)) : '""')
            . ') ORDER BY sc_name ';

        $stmt = $this->db->prepare($query);
        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue($key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            // hide unauthorized service categories
            $hide = false;
            if (! $centreon->user->access->admin && count($scAcl) && ! in_array($row['sc_id'], array_keys($scAcl))) {
                $hide = true;
            }

            $items[] = ['id' => $row['sc_id'], 'text' => $row['sc_name'], 'hide' => $hide];
        }

        return $items;
    }
}
