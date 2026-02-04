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

require_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonInstance.class.php';

/**
 * Class
 *
 * @class CentreonServicetemplates
 * @description Class that contains various methods for managing services
 */
class CentreonServicetemplates extends CentreonService
{
    /**
     *  Constructor
     *
     * @param CentreonDB $db
     *
     * @throws PDOException
     */
    public function __construct($db)
    {
        parent::__construct($db);
    }

    /**
     * @param int $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'service';
        $parameters['currentObject']['id'] = 'service_id';
        $parameters['currentObject']['name'] = 'service_description';
        $parameters['currentObject']['comparator'] = 'service_id';

        switch ($field) {
            case 'service_hPars':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonHosttemplates';
                $parameters['externalObject']['table'] = 'host';
                $parameters['externalObject']['id'] = 'host_id';
                $parameters['externalObject']['name'] = 'host_name';
                $parameters['externalObject']['comparator'] = 'host_id';
                $parameters['relationObject']['table'] = 'host_service_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            default:
                $parameters = parent::getDefaultValuesParameters($field);
                break;
        }

        return $parameters;
    }

    /**
     * @param array $values
     * @param array $options
     * @param string $register
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [], $register = '1')
    {
        $serviceList = [];
        if (isset($options['withHosttemplate']) && $options['withHosttemplate'] === true) {
            $serviceList = parent::getObjectForSelect2($values, $options, '0');
        } else {
            $selectedServices = '';
            $listValues = '';
            $queryValues = [];
            if (! empty($values)) {
                foreach ($values as $k => $v) {
                    $listValues .= ':service' . $v . ',';
                    $queryValues['service' . $v] = (int) $v;
                }
                $listValues = rtrim($listValues, ',');
                $selectedServices .= "AND s.service_id IN ({$listValues}) ";
            }

            $queryService = 'SELECT DISTINCT s.service_id, s.service_description FROM service s '
                . 'WHERE s.service_register = "0" ' . $selectedServices . 'ORDER BY s.service_description ';

            $stmt = $this->db->prepare($queryService);
            if ($queryValues !== []) {
                foreach ($queryValues as $key => $id) {
                    $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
                }
            }
            $stmt->execute();

            while ($data = $stmt->fetch()) {
                $serviceList[] = ['id' => $data['service_id'], 'text' => $data['service_description']];
            }
        }

        return $serviceList;
    }

    /**
     * @param $serviceTemplateName
     * @param bool $checkTemplates
     * @throws Exception
     * @return array
     */
    public function getLinkedServicesByName($serviceTemplateName, $checkTemplates = true)
    {
        $register = $checkTemplates ? 0 : 1;

        $linkedServices = [];
        $query = 'SELECT DISTINCT s.service_description '
            . 'FROM service s, service st '
            . 'WHERE s.service_template_model_stm_id = st.service_id '
            . 'AND st.service_register = "0" '
            . 'AND s.service_register = "' . $register . '" '
            . 'AND st.service_description = "' . $this->db->escape($serviceTemplateName) . '" ';

        try {
            $result = $this->db->query($query);
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked services of ' . $serviceTemplateName);
        }

        while ($row = $result->fetchRow()) {
            $linkedServices[] = $row['service_description'];
        }

        return $linkedServices;
    }

    /**
     * @param string $serviceTemplateName linked service template
     * @param string $hostTemplateName linked host template
     *
     * @throws PDOException
     * @return array service ids
     */
    public function getServiceIdsLinkedToSTAndCreatedByHT($serviceTemplateName, $hostTemplateName)
    {
        $serviceIds = [];

        $query = 'SELECT DISTINCT(s.service_id) '
            . 'FROM service s, service st, host h, host ht, host_service_relation hsr, host_service_relation hsrt,'
            . ' host_template_relation htr '
            . 'WHERE st.service_description = "' . $this->db->escape($serviceTemplateName) . '" '
            . 'AND s.service_template_model_stm_id = st.service_id '
            . 'AND st.service_id = hsrt.service_service_id '
            . 'AND hsrt.host_host_id = ht.host_id '
            . 'AND ht.host_name = "' . $this->db->escape($hostTemplateName) . '" '
            . 'AND ht.host_id = htr.host_tpl_id '
            . 'AND htr.host_host_id = h.host_id '
            . 'AND h.host_id = hsr.host_host_id '
            . 'AND hsr.service_service_id = s.service_id '
            . 'AND s.service_register = "1" ';
        $result = $this->db->query($query);
        while ($row = $result->fetchRow()) {
            $serviceIds[] = $row['service_id'];
        }

        return $serviceIds;
    }

    /**
     * @param bool $enable
     *
     * @return array
     */
    public function getList($enable = false)
    {
        $serviceTemplates = [];

        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT service_id, service_description '
            . 'FROM service '
            . "WHERE service_register = '0' ";

        if ($enable) {
            $query .= "AND service_activate = '1' ";
        }

        $query .= 'ORDER BY service_description ';

        try {
            $res = $this->db->query($query);
        } catch (PDOException $e) {
            return [];
        }

        $serviceTemplates = [];
        while ($row = $res->fetchRow()) {
            $serviceTemplates[$row['service_id']] = $row['service_description'];
        }

        return $serviceTemplates;
    }
}
