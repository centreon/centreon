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
 * @class CentreonConfigurationMeta
 */
class CentreonConfigurationMeta extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationMeta constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $aclMetaServices = '';
        $queryValues = [];

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclMetaServices .= 'AND meta_id IN (' . $acl->getMetaServiceString() . ') ';
        }

        // Check for select2 'q' argument
        $queryValues['name'] = isset($this->arguments['q']) ? '%' . (string) $this->arguments['q'] . '%' : '%%';

        $queryMeta = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT meta_id, meta_name, meta_activate FROM meta_service '
            . 'WHERE meta_name LIKE :name '
            . $aclMetaServices
            . 'ORDER BY meta_name ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $queryMeta .= 'LIMIT :offset,:limit';
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($queryMeta);
        $stmt->bindParam(':name', $queryValues['name'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $metaList = [];
        while ($data = $stmt->fetch()) {
            $metaList[] = ['id' => $data['meta_id'], 'text' => $data['meta_name'], 'status' => (bool) $data['meta_activate']];
        }

        return ['items' => $metaList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
