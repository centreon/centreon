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
 * @class Timezone
 */
class Timezone extends AbstractObject
{
    /** @var null */
    private $aTimezone = null;

    /** @var null */
    private $defaultTimezone = null;

    /**
     * @throws PDOException
     * @return mixed|null
     */
    public function getDefaultTimezone()
    {
        if (! is_null($this->defaultTimezone)) {
            return $this->defaultTimezone;
        }

        $stmt = $this->backend_instance->db->prepare("SELECT `value` from options WHERE `key` = 'gmt'");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) > 0 && isset($this->aTimezone[$results[0]['value']])) {
            $this->defaultTimezone = $this->aTimezone[$results[0]['value']];
        }

        return $this->defaultTimezone;
    }

    /**
     * @throws PDOException
     * @return void|null
     */
    private function getTimezone()
    {
        if (! is_null($this->aTimezone)) {
            return $this->aTimezone;
        }

        $this->aTimezone = [];
        $stmt = $this->backend_instance->db->prepare('SELECT 
                timezone_id,
                timezone_name
            FROM timezone');
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $res) {
            $this->aTimezone[$res['timezone_id']] = $res['timezone_name'];
        }
    }

    /**
     * @param $iTimezone
     * @param $returnDefault
     *
     * @throws PDOException
     * @return mixed|null
     */
    public function getTimezoneFromId($iTimezone, $returnDefault = false)
    {
        if (is_null($this->aTimezone)) {
            $this->getTimezone();
        }

        $result = null;
        if (! is_null($iTimezone) && isset($this->aTimezone[$iTimezone])) {
            $result = $this->aTimezone[$iTimezone];
        } elseif ($returnDefault === true) {
            $result = $this->getDefaultTimezone();
        }

        return $result;
    }
}
