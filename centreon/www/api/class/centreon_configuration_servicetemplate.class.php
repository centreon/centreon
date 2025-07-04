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
require_once __DIR__ . '/centreon_configuration_service.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationServicetemplate
 */
class CentreonConfigurationServicetemplate extends CentreonConfigurationService
{
    /**
     * CentreonConfigurationServicetemplate constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        $range = [];
        // Check for select2 'q' argument
        $q = isset($this->arguments['q']) ? (string) $this->arguments['q'] : '';

        if (isset($this->arguments['l'])) {
            $templateType = ['0', '1'];
            if (in_array($this->arguments['l'], $templateType)) {
                $l = $this->arguments['l'];
            } else {
                throw new RestBadRequestException('Error, bad list parameter');
            }
        } else {
            $l = '0';
        }

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $range[] = (int) $offset;
            $range[] = (int) $this->arguments['page_limit'];
        }

        return $l == '1' ? $this->listWithHostTemplate($q, $range) : $this->listClassic($q, $range);
    }

    /**
     * @param $q
     * @param array $range
     *
     * @throws PDOException
     * @return array
     */
    private function listClassic($q, $range = [])
    {
        $serviceList = [];
        $queryValues = [];

        $queryContact = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT service_id, service_description '
            . 'FROM service '
            . 'WHERE service_description LIKE :description '
            . 'AND service_register = "0" '
            . 'ORDER BY service_description ';
        if (isset($range) && ! empty($range)) {
            $queryContact .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $range[0];
            $queryValues['limit'] = (int) $range[1];
        }
        $queryValues['description'] = '%' . (string) $q . '%';
        $stmt = $this->pearDB->prepare($queryContact);
        $stmt->bindParam(':description', $queryValues['description'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        while ($data = $stmt->fetch()) {
            $serviceList[] = ['id' => $data['service_id'], 'text' => $data['service_description']];
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDB->numberRows()];
    }

    /**
     * @param string $q
     * @param array $range
     *
     * @throws PDOException
     * @return array
     */
    private function listWithHostTemplate($q = '', $range = [])
    {
        $queryValues = [];
        $queryValues['description'] = '%' . (string) $q . '%';
        $queryService = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT s.service_description, s.service_id, '
            . 'h.host_name, h.host_id '
            . 'FROM host h, service s, host_service_relation hsr '
            . 'WHERE hsr.host_host_id = h.host_id '
            . 'AND hsr.service_service_id = s.service_id '
            . 'AND h.host_register = "0" '
            . 'AND s.service_register = "0" '
            . 'AND CONCAT(h.host_name, " - ", s.service_description) LIKE :description '
            . 'ORDER BY h.host_name ';
        if (isset($range) && ! empty($range)) {
            $queryService .= 'LIMIT :offset, :limit';
            $queryValues['offset'] = (int) $range[0];
            $queryValues['limit'] = (int) $range[1];
        }
        $stmt = $this->pearDB->prepare($queryService);
        $stmt->bindParam(':description', $queryValues['description'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $serviceList = [];
        while ($data = $stmt->fetch()) {
            $serviceCompleteName = $data['host_name'] . ' - ' . $data['service_description'];
            $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];

            $serviceList[] = ['id' => htmlentities($serviceCompleteId), 'text' => $serviceCompleteName];
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
