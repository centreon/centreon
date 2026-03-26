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
 * @class CentreonResources
 */
class CentreonResources
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonResources constructor
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
        $parameters['currentObject']['table'] = 'cfg_resource';
        $parameters['currentObject']['id'] = 'resource_id';
        $parameters['currentObject']['name'] = 'resource_name';
        $parameters['currentObject']['comparator'] = 'resource_id';

        switch ($field) {
            case 'instance_id':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonInstance';
                $parameters['relationObject']['table'] = 'cfg_resource_instance_relations';
                $parameters['relationObject']['field'] = 'instance_id';
                $parameters['relationObject']['comparator'] = 'resource_id';
                break;
        }

        return $parameters;
    }

    /**
     * @param CentreonDB $db
     * @param string $name
     *
     * @throws Exception
     * @return array
     */
    public static function getResourceByName($db, $name)
    {
        $queryResources = "SELECT * FROM cfg_resource WHERE resource_name = '{$name}'";
        $resultQueryResources = $db->query($queryResources);

        $finalResource = [];
        while ($resultResources = $resultQueryResources->fetchRow()) {
            $finalResource = $resultResources;
        }

        if (count($finalResource) === 0) {
            throw new Exception('No central broker found', 500);
        }

        return $finalResource;
    }
}
