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
 * @class CentreonConfigurationHostcategory
 */
class CentreonConfigurationHostcategory extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationHostcategory constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $queryValues = [];
        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $aclHostCategories = '';

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclHostCategoryIds = $acl->getHostCategoriesString('ID');
            if ($aclHostCategoryIds != "''") {
                $aclHostCategories .= 'AND hc.hc_id IN (' . $aclHostCategoryIds . ') ';
            }
        }
        /* Check for select2
        't' argument
        'a' or empty = category and severitiy
        'c' = catagory only
        's' = severity only */
        if (isset($this->arguments['t'])) {
            $selectList = ['a', 'c', 's'];
            if (in_array(strtolower($this->arguments['t']), $selectList)) {
                $t = $this->arguments['t'];
            } else {
                throw new RestBadRequestException('Error, type must be numerical');
            }
        } else {
            $t = '';
        }

        // Check for select2 'q' argument
        $queryValues['hcName'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $queryHostCategory = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT hc.hc_name, hc.hc_id '
            . 'FROM hostcategories hc '
            . 'WHERE hc.hc_name LIKE :hcName '
            . $aclHostCategories;
        if (! empty($t) && $t == 'c') {
            $queryHostCategory .= 'AND level IS NULL ';
        }
        if (! empty($t) && $t == 's') {
            $queryHostCategory .= 'AND level IS NOT NULL ';
        }
        $queryHostCategory .= 'ORDER BY hc.hc_name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryHostCategory .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($queryHostCategory);
        $stmt->bindParam(':hcName', $queryValues['hcName'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $hostCategoryList = [];
        while ($data = $stmt->fetch()) {
            $hostCategoryList[] = ['id' => htmlentities($data['hc_id']), 'text' => $data['hc_name']];
        }

        return ['items' => $hostCategoryList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
