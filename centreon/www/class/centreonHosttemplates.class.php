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

require_once _CENTREON_PATH_ . 'www/class/centreonInstance.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonHost.class.php';

/**
 * Class
 *
 * @class CentreonHosttemplates
 * @description Class that contains various methods for managing hosts
 */
class CentreonHosttemplates extends CentreonHost
{
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
        return parent::getObjectForSelect2($values, $options, '0');
    }

    /**
     * Returns array of host linked to the template
     *
     * @param string $hostTemplateName
     * @param bool $checkTemplates
     *
     * @throws Exception
     * @return array
     */
    public function getLinkedHostsByName($hostTemplateName, $checkTemplates = true)
    {
        $register = $checkTemplates ? 0 : 1;

        $linkedHosts = [];
        $query = 'SELECT DISTINCT h.host_name
            FROM host_template_relation htr, host h, host ht
            WHERE htr.host_tpl_id = ht.host_id
            AND htr.host_host_id = h.host_id
            AND ht.host_register = "0"
            AND h.host_register = :register
            AND ht.host_name = :hostTplName';
        try {
            $result = $this->db->prepare($query);
            $result->bindValue(':register', $register, PDO::PARAM_STR);
            $result->bindValue(':hostTplName', $this->db->escape($hostTemplateName), PDO::PARAM_STR);
            $result->execute();
        } catch (PDOException $e) {
            throw new Exception('Error while getting linked hosts of ' . $hostTemplateName);
        }

        while ($row = $result->fetch()) {
            $linkedHosts[] = $row['host_name'];
        }

        return $linkedHosts;
    }
}
