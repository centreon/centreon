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

require_once 'Centreon/Object/Relation/Relation.php';

class Centreon_Object_Relation_Service_Group_Host_Group_Service extends Centreon_Object_Relation
{
    protected $relationTable = 'servicegroup_relation';

    protected $firstKey = 'servicegroup_sg_id';

    protected $secondKey = 'service_service_id';

    /**
     * Used for inserting relation into database
     * @param int $fkey
     * @param null $key
     */
    public function insert($fkey, $key = null): void
    {
        $hgId = $key['hostId'];
        $serviceId = $key['serviceId'];
        $sql = "INSERT INTO {$this->relationTable} ({$this->firstKey}, hostgroup_hg_id, {$this->secondKey}) VALUES (?, ?, ?)";
        $this->db->query($sql, [$fkey, $hgId, $serviceId]);
    }

    /**
     * Used for deleting relation from database
     *
     * @param int $fkey
     * @param int $hgId
     * @param int $serviceId
     * @return void
     */
    public function delete($fkey, $hgId = null, $serviceId = null): void
    {
        if (isset($fkey, $hgId, $serviceId)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ? AND hostgroup_hg_id = ? AND {$this->secondKey} = ?";
            $args = [$fkey, $hgId, $serviceId];
        } elseif (isset($hgId, $serviceId)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE hostgroup_hg_id = ? AND {$this->secondKey} = ?";
            $args = [$hgId, $serviceId];
        } else {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ?";
            $args = [$fkey];
        }
        $this->db->query($sql, $args);
    }

    /**
     * Get service group id from host id, service id
     *
     * @param int $hgId
     * @param int $serviceId
     * @return array
     */
    public function getServicegroupIdFromHostIdServiceId($hgId, $serviceId)
    {
        $sql = "SELECT {$this->firstKey} FROM {$this->relationTable} WHERE hostgroup_hg_id = ? AND {$this->secondKey} = ?";
        $result = $this->getResult($sql, [$hgId, $serviceId]);
        $tab = [];
        foreach ($result as $rez) {
            $tab[] = $rez[$this->firstKey];
        }

        return $tab;
    }

    /**
     * Get Host id service id from service group id
     *
     * @param int $servicegroupId
     * @return array multidimentional array with hostgroup_id and service_id indexes
     */
    public function getHostGroupIdServiceIdFromServicegroupId($servicegroupId)
    {
        $sql = "SELECT hostgroup_hg_id, {$this->secondKey} FROM {$this->relationTable} WHERE {$this->firstKey} = ?";
        $result = $this->getResult($sql, [$servicegroupId]);
        $tab = [];
        $i = 0;
        foreach ($result as $rez) {
            $tab[$i]['hostgroup_id'] = $rez['hostgroup_hg_id'];
            $tab[$i]['service_id'] = $rez[$this->secondKey];
            $i++;
        }

        return $tab;
    }

    /**
     * This call will directly throw an exception
     *
     * @param string $name
     * @param array $arg
     * @throws Exception
     */
    public function __call($name, $arg = [])
    {
        throw new Exception('Unknown method');
    }
}
