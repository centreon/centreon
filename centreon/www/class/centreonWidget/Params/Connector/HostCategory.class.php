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

require_once __DIR__ . '/../List.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsConnectorHostCategory
 */
class CentreonWidgetParamsConnectorHostCategory extends CentreonWidgetParamsList
{
    /**
     * CentreonWidgetParamsConnectorHostCategory constructor
     *
     * @param $db
     * @param $quickform
     * @param $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
    }

    /**
     * @param $paramId
     *
     * @throws PDOException
     * @return mixed|null[]
     */
    public function getListValues($paramId)
    {
        static $tab;

        if (! isset($tab)) {
            $query = 'SELECT hc_id AS id, hc_name AS name '
                . 'FROM hostcategories '
                . "WHERE hc_activate = '1' "
                . 'ORDER BY name';
            $res = $this->db->query($query);
            $tab = [null => null];
            while ($row = $res->fetchRow()) {
                $tab[$row['id']] = $row['name'];
            }
        }

        return $tab;
    }
}
