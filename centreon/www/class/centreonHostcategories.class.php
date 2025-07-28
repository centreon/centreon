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
 * @class CentreonHostcategories
 */
class CentreonHostcategories
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonHostcategories constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->db = $pearDB;
    }

    /**
     * @param int $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'hostcategories';
        $parameters['currentObject']['id'] = 'hc_id';
        $parameters['currentObject']['name'] = 'hc_name';
        $parameters['currentObject']['comparator'] = 'hc_id';

        switch ($field) {
            case 'hc_hosts':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonHost';
                $parameters['relationObject']['table'] = 'hostcategories_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['comparator'] = 'hostcategories_hc_id';
                break;
            case 'hc_hostsTemplate':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonHosttemplates';
                $parameters['relationObject']['table'] = 'hostcategories_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['comparator'] = 'hostcategories_hc_id';
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

        // get list of authorized host categories
        if (! $centreon->user->access->admin) {
            $hcAcl = $centreon->user->access->getHostCategories();
        }

        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $v) {
                // As it happens that $v could be like "X,Y" when two hostgroups are selected, we added a second foreach
                $multiValues = explode(',', $v);
                foreach ($multiValues as $item) {
                    $listValues .= ':sc' . $item . ', ';
                    $queryValues['sc' . $item] = (int) $item;
                }
            }
            $listValues = rtrim($listValues, ', ');
        } else {
            $listValues .= '""';
        }

        // get list of selected host categories
        $query = 'SELECT hc_id, hc_name FROM hostcategories '
            . 'WHERE hc_id IN (' . $listValues . ') ORDER BY hc_name ';

        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            // hide unauthorized host categories
            $hide = false;
            if (! $centreon->user->access->admin && count($hcAcl) && ! in_array($row['hc_id'], array_keys($hcAcl))) {
                $hide = true;
            }

            $items[] = ['id' => $row['hc_id'], 'text' => $row['hc_name'], 'hide' => $hide];
        }

        return $items;
    }
}
