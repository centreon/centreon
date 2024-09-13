<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace ConfigGenerateRemote;

use Exception;
use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use PDOStatement;
use Pimple\Container;

/**
 * Class
 *
 * @class ServiceGroup
 * @package ConfigGenerateRemote
 */
class ServiceGroup extends AbstractObject
{
    /** @var int */
    private $useCache = 1;
    /** @var int */
    private $doneCache = 0;

    /** @var array */
    private $sg = [];
    /** @var array */
    private $sgRelationCache = [];
    /** @var string */
    protected $table = 'servicegroup';
    /** @var string */
    protected $generateFilename = 'servicegroups.infile';
    /** @var string */
    protected $attributesSelect = '
        sg_id,
        sg_name,
        sg_alias,
        geo_coords
    ';
    /** @var string[] */
    protected $attributesWrite = [
        'sg_id',
        'sg_name',
        'sg_alias',
        'geo_coords'
    ];
    /** @var PDOStatement */
    protected $stmtSg = null;
    /** @var PDOStatement */
    protected $stmtServiceSg = null;
    /** @var PDOStatement */
    protected $stmtStplSg = null;

    /**
     * ServiceGroup constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * Get servicegroup frm id
     *
     * @param int $sgId
     * @return void
     */
    private function getServicegroupFromId(int $sgId)
    {
        if (is_null($this->stmtSg)) {
            $this->stmtSg = $this->backendInstance->db->prepare(
                "SELECT $this->attributesSelect
                FROM servicegroup
                WHERE sg_id = :sg_id AND sg_activate = '1'"
            );
        }

        $this->stmtSg->bindParam(':sg_id', $sgId, PDO::PARAM_INT);
        $this->stmtSg->execute();
        $results = $this->stmtSg->fetchAll(PDO::FETCH_ASSOC);
        $this->sg[$sgId] = array_pop($results);
        if (is_null($this->sg[$sgId])) {
            return 1;
        }
        $this->sg[$sgId]['members_cache'] = [];
    }

    /**
     * Generate service group
     *
     * @param int $sgId
     * @param int $serviceId
     * @param string $serviceDescription
     * @param int $hostId
     * @param string $hostName
     *
     * @return int
     * @throws Exception
     */
    public function addServiceInSg(int $sgId, int $serviceId, string $serviceDescription, int $hostId, string $hostName)
    {
        if (!isset($this->sg[$sgId])) {
            $this->getServicegroupFromId($sgId);
            $this->generateObjectInFile($this->sg[$sgId], $sgId);
        }
        if (is_null($this->sg[$sgId]) || isset($this->sg[$sgId]['members_cache'][$hostId . '_' . $serviceId])) {
            return 1;
        }

        $this->sg[$sgId]['members_cache'][$hostId . '_' . $serviceId] = [$hostName, $serviceDescription];
        return 0;
    }

    /**
     * Build cache
     *
     * @return void
     */
    private function buildCache()
    {
        if ($this->doneCache == 1) {
            return 0;
        }

        $stmt = $this->backendInstance->db->prepare("SELECT 
                  service_service_id, servicegroup_sg_id, host_host_id
                FROM servicegroup_relation
        ");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->sgRelationCache[$value['service_service_id']])) {
                $this->sgRelationCache[$value['service_service_id']][] = $value;
            } else {
                $this->sgRelationCache[$value['service_service_id']] = [$value];
            }
        }

        $this->doneCache = 1;
    }

    /**
     * Get service group from service template id
     *
     * @param int $serviceId
     * @return void
     */
    public function getServiceGroupsForStpl(int $serviceId)
    {
        // Get from the cache
        if (isset($this->sgRelationCache[$serviceId])) {
            return $this->sgRelationCache[$serviceId];
        }
        if ($this->doneCache == 1) {
            return [];
        }

        if (is_null($this->stmtStplSg)) {
            // Meaning, linked with the host or hostgroup (for the null expression)
            $this->stmtStplSg = $this->backendInstance->db->prepare(
                "SELECT servicegroup_sg_id, host_host_id, service_service_id
                FROM servicegroup_relation
                WHERE service_service_id = :service_id"
            );
        }
        $this->stmtStplSg->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $this->stmtStplSg->execute();
        $this->sgRelationCache[$serviceId] = array_merge(
            $this->stmtStplSg->fetchAll(PDO::FETCH_ASSOC),
            $this->sgRelationCache[$serviceId]
        );
        return $this->sgRelationCache[$serviceId];
    }

    /**
     * Get service linked service groups
     *
     * @param int $hostId
     * @param int $serviceId
     * @return void
     */
    public function getServiceGroupsForService(int $hostId, int $serviceId)
    {
        // Get from the cache
        if (isset($this->sgRelationCache[$serviceId])) {
            return $this->sgRelationCache[$serviceId];
        }
        if ($this->doneCache == 1) {
            return [];
        }

        if (is_null($this->stmtServiceSg)) {
            // Meaning, linked with the host or hostgroup (for the null expression)
            $this->stmtServiceSg = $this->backendInstance->db->prepare(
                "SELECT servicegroup_sg_id, host_host_id, service_service_id
                FROM servicegroup_relation
                WHERE service_service_id = :service_id
                AND (host_host_id = :host_id OR host_host_id IS NULL)"
            );
        }
        $this->stmtServiceSg->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $this->stmtServiceSg->bindParam(':host_id', $hostId, PDO::PARAM_INT);
        $this->stmtServiceSg->execute();
        $this->sgRelationCache[$serviceId] = array_merge(
            $this->stmtServiceSg->fetchAll(PDO::FETCH_ASSOC),
            $this->sgRelationCache[$serviceId]
        );
        return $this->sgRelationCache[$serviceId];
    }

    /**
     * Generate object
     *
     * @param int $sgId
     *
     * @return void
     * @throws Exception
     */
    public function generateObject(int $sgId)
    {
        if ($this->checkGenerate($sgId)) {
            return null;
        }

        $this->generateObjectInFile($this->sg[$sgId], $sgId);
    }

    /**
     * Generate objects
     *
     * @return void
     * @throws Exception
     */
    public function generateObjects(): void
    {
        foreach ($this->sg as $id => &$value) {
            if (count($value['members_cache']) == 0) {
                continue;
            }

            $this->sg[$id]['sg_id'] = $id;
            $this->generateObjectInFile($this->sg[$id], $id);
        }
    }

    /**
     * Get service groups
     *
     * @return array
     */
    public function getServicegroups()
    {
        $result = [];
        foreach ($this->sg as $id => &$value) {
            if (is_null($value) || count($value['members_cache']) == 0) {
                continue;
            }
            $result[$id] = &$value;
        }
        return $result;
    }

    /**
     * Reset object
     *
     * @param bool $createfile
     *
     * @return void
     * @throws Exception
     */
    public function reset($createfile = false): void
    {
        $this->sg = [];
        parent::reset($createfile);
    }

    /**
     * Get servicegroup attribute
     *
     * @param int $sgId
     * @param string $attr
     * @return void
     */
    public function getString(int $sgId, string $attr)
    {
        if (isset($this->sg[$sgId][$attr])) {
            return $this->sg[$sgId][$attr];
        }
        return null;
    }
}
