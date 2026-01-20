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
 * @class CentreonGraphVirtualMetric
 */
class CentreonGraphVirtualMetric
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonGraphVirtualMetric constructor
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
        $parameters['currentObject']['table'] = 'virtual_metrics';
        $parameters['currentObject']['id'] = 'vmetric_id';
        $parameters['currentObject']['name'] = 'vmetric_name';
        $parameters['currentObject']['comparator'] = 'vmetric_id';

        switch ($field) {
            case 'host_id':
                $parameters['type'] = 'simple';
                $parameters['currentObject']['additionalField'] = 'service_id';
                $parameters['externalObject']['object'] = 'centreonService';
                break;
        }

        return $parameters;
    }
}
