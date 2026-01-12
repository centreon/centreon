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
 * @class CentreonGraphCurve
 */
class CentreonGraphCurve
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonGraphCurve constructor
     *
     * @param $pearDB
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
        $parameters['currentObject']['table'] = 'giv_components_template';
        $parameters['currentObject']['id'] = 'compo_id';
        $parameters['currentObject']['name'] = 'name';
        $parameters['currentObject']['comparator'] = 'compo_id';

        switch ($field) {
            case 'host_id':
                $parameters['type'] = 'simple';
                $parameters['currentObject']['additionalField'] = 'service_id';
                $parameters['externalObject']['object'] = 'centreonService';
                $parameters['externalObject']['table'] = 'giv_components_template';
                $parameters['externalObject']['id'] = 'service_id';
                $parameters['externalObject']['name'] = 'service_description';
                $parameters['externalObject']['comparator'] = 'service_id';
                break;
            case 'compo_id':
                $parameters['type'] = 'simple';
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
        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':compo' . $v . ',';
                $queryValues['compo' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
            $selectedGraphCurves = 'WHERE compo_id IN (' . $listValues . ') ';
        } else {
            $selectedGraphCurves = '""';
        }

        $queryGraphCurve = 'SELECT DISTINCT compo_id as id, name FROM giv_components_template '
            . $selectedGraphCurves . ' ORDER BY name';

        $stmt = $this->db->prepare($queryGraphCurve);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($data = $stmt->fetch()) {
            $graphCurveList[] = ['id' => $data['id'], 'text' => $data['name']];
        }

        return $graphCurveList;
    }
}
