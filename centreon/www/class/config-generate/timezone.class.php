<?php

/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
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
     * @return mixed|null
     * @throws PDOException
     */
    public function getDefaultTimezone()
    {
        if (!is_null($this->defaultTimezone)) {
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
     * @return void|null
     * @throws PDOException
     */
    private function getTimezone()
    {
        if (!is_null($this->aTimezone)) {
            return $this->aTimezone;
        }

        $this->aTimezone = [];
        $stmt = $this->backend_instance->db->prepare("SELECT 
                timezone_id,
                timezone_name
            FROM timezone");
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
     * @return mixed|null
     * @throws PDOException
     */
    public function getTimezoneFromId($iTimezone, $returnDefault = false)
    {
        if (is_null($this->aTimezone)) {
            $this->getTimezone();
        }

        $result = null;
        if (!is_null($iTimezone) && isset($this->aTimezone[$iTimezone])) {
            $result = $this->aTimezone[$iTimezone];
        } elseif ($returnDefault === true) {
            $result = $this->getDefaultTimezone();
        }

        return $result;
    }
}
