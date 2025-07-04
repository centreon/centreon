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

define('PROCEDURE_SIMPLE_MODE', 0);
define('PROCEDURE_INHERITANCE_MODE', 1);
require_once _CENTREON_PATH_ . '/www/class/centreon-knowledge/wikiApi.class.php';

/**
 * Class
 *
 * @class procedures
 */
class procedures
{
    /** @var array */
    private $procList = [];

    /** @var CentreonDB */
    public $DB;

    /** @var CentreonDB */
    public $centreon_DB;

    /** @var WikiApi */
    public $api;

    /**
     * procedures constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->api = new WikiApi();
        $this->centreon_DB = $pearDB;
    }

    /**
     * Set procedures
     *
     * @return void
     */
    public function fetchProcedures()
    {
        if ($this->procList !== []) {
            return null;
        }

        $pages = $this->api->getAllPages();
        // replace space
        foreach ($pages as $page) {
            $page = str_replace(' ', '_', $page);
            $this->procList[$page] = '';
        }
    }

    /**
     * Get service template
     *
     * @param null $service_id
     *
     * @throws PDOException
     * @return array
     */
    public function getMyServiceTemplateModels($service_id = null)
    {
        $tplArr = [];

        $dbResult = $this->centreon_DB->query(
            'SELECT service_description, service_template_model_stm_id '
            . 'FROM service '
            . "WHERE service_id = '" . $service_id . "' LIMIT 1"
        );
        $row = $dbResult->fetch();
        if (isset($row['service_template_model_stm_id']) && $row['service_template_model_stm_id'] != '') {
            $dbResult->closeCursor();
            $service_id = $row['service_template_model_stm_id'];
            if ($row['service_description']) {
                $tplArr[$service_id] = html_entity_decode($row['service_description'], ENT_QUOTES);
            }
            while (1) {
                $dbResult = $this->centreon_DB->query(
                    'SELECT service_description, service_template_model_stm_id '
                    . 'FROM service '
                    . "WHERE service_id = '" . $service_id . "' LIMIT 1"
                );
                $row = $dbResult->fetch();
                $dbResult->closeCursor();
                if ($row['service_description']) {
                    $tplArr[$service_id] = html_entity_decode($row['service_description'], ENT_QUOTES);
                } else {
                    break;
                }
                if ($row['service_template_model_stm_id']) {
                    $service_id = $row['service_template_model_stm_id'];
                } else {
                    break;
                }
            }
        }

        return $tplArr;
    }

    /**
     * Get host template models
     *
     * @param null $host_id
     *
     * @throws PDOException
     * @return array
     */
    public function getMyHostMultipleTemplateModels($host_id = null)
    {
        if (! $host_id) {
            return [];
        }

        $tplArr = [];
        $dbResult = $this->centreon_DB->query(
            'SELECT host_tpl_id '
            . 'FROM `host_template_relation` '
            . "WHERE host_host_id = '" . $host_id . "' "
            . 'ORDER BY `order`'
        );
        $statement = $this->centreon_DB->prepare(
            'SELECT host_name '
            . 'FROM host '
            . 'WHERE host_id = :host_id LIMIT 1'
        );
        while ($row = $dbResult->fetch()) {
            $statement->bindValue(':host_id', $row['host_tpl_id'], PDO::PARAM_INT);
            $statement->execute();
            $hTpl = $statement->fetch(PDO::FETCH_ASSOC);
            $tplArr[$row['host_tpl_id']] = html_entity_decode($hTpl['host_name'], ENT_QUOTES);
        }
        unset($row, $hTpl);

        return $tplArr;
    }

    /**
     * Check if Service has procedure
     *
     * @param string $key
     * @param array $templates
     * @param int $mode
     * @return bool
     */
    public function serviceHasProcedure($key, $templates = [], $mode = PROCEDURE_SIMPLE_MODE)
    {
        if (isset($this->procList['Service_:_' . $key])) {
            return true;
        }
        if ($mode == PROCEDURE_SIMPLE_MODE) {
            return false;
        }
        if ($mode == PROCEDURE_INHERITANCE_MODE) {
            foreach ($templates as $templateId => $templateName) {
                $res = $this->serviceTemplateHasProcedure($templateName, null, PROCEDURE_SIMPLE_MODE);
                if ($res == true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if Host has procedure
     *
     * @param string $key
     * @param array $templates
     * @param int $mode
     * @return bool
     */
    public function hostHasProcedure($key, $templates = [], $mode = PROCEDURE_SIMPLE_MODE)
    {
        if (isset($this->procList['Host_:_' . $key])) {
            return true;
        }

        if ($mode == PROCEDURE_SIMPLE_MODE) {
            return false;
        }
        if ($mode == PROCEDURE_INHERITANCE_MODE) {
            foreach ($templates as $templateId => $templateName) {
                $res = $this->hostTemplateHasProcedure($templateName, null, PROCEDURE_SIMPLE_MODE);
                if ($res == true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if Service template has procedure
     *
     * @param string $key
     * @param array $templates
     * @param int $mode
     * @return bool
     */
    public function serviceTemplateHasProcedure($key = '', $templates = [], $mode = PROCEDURE_SIMPLE_MODE)
    {
        if (isset($this->procList['Service-Template_:_' . $key])) {
            return true;
        }
        if ($mode == PROCEDURE_SIMPLE_MODE) {
            return false;
        }
        if ($mode == PROCEDURE_INHERITANCE_MODE) {
            foreach ($templates as $templateId => $templateName) {
                if (isset($this->procList['Service-Template_:_' . $templateName])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if Host template has procedures
     *
     * @param string $key
     * @param array $templates
     * @param mixed $mode
     * @return bool
     */
    public function hostTemplateHasProcedure($key = '', $templates = [], $mode = PROCEDURE_SIMPLE_MODE)
    {
        if (isset($this->procList['Host-Template_:_' . $key])) {
            return true;
        }
        if ($mode == PROCEDURE_SIMPLE_MODE) {
            return false;
        }
        if ($mode == PROCEDURE_INHERITANCE_MODE) {
            foreach ($templates as $templateId => $templateName) {
                if (isset($this->procList['Host-Template_:_' . $templateName])) {
                    return true;
                }
            }
        }

        return false;
    }
}
