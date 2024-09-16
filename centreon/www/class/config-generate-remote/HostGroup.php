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

use \PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;

/**
 * Class
 *
 * @class HostGroup
 * @package ConfigGenerateRemote
 */
class HostGroup extends AbstractObject
{
    /** @var array */
    private $hg = [];
    /** @var string */
    protected $table = 'hostgroup';
    /** @var string */
    protected $generateFilename = 'hostgroups.infile';
    /** @var string */
    protected $attributesSelect = '
        hg_id,
        hg_name,
        hg_alias,
        hg_notes,
        hg_notes_url,
        hg_action_url,
        hg_icon_image,
        hg_map_icon_image,
        geo_coords,
        hg_rrd_retention
    ';
    /** @var string[] */
    protected $attributesWrite = [
        'hg_id',
        'hg_name',
        'hg_alias',
        'hg_notes',
        'hg_notes_url',
        'hg_action_url',
        'hg_icon_image',
        'hg_map_icon_image',
        'geo_coords',
        'hg_rrd_retention'
    ];
    /** @var null */
    protected $stmtHg = null;

    /**
     * Get host group from id
     *
     * @param int $hgId
     * @return void
     */
    private function getHostgroupFromId(int $hgId)
    {
        if (is_null($this->stmtHg)) {
            $this->stmtHg = $this->backendInstance->db->prepare(
                "SELECT $this->attributesSelect
                FROM hostgroup
                WHERE hg_id = :hg_id AND hg_activate = '1'"
            );
        }
        $this->stmtHg->bindParam(':hg_id', $hgId, PDO::PARAM_INT);
        $this->stmtHg->execute();
        $results = $this->stmtHg->fetchAll(PDO::FETCH_ASSOC);
        $this->hg[$hgId] = array_pop($results);
        if (is_null($this->hg[$hgId])) {
            return null;
        }
        $this->hg[$hgId]['members'] = [];
    }

    /**
     * Add host in host group
     *
     * @param int $hgId
     * @param int $hostId
     * @param string $hostName
     *
     * @return int
     * @throws \Exception
     */
    public function addHostInHg(int $hgId, int $hostId, string $hostName)
    {
        if (!isset($this->hg[$hgId])) {
            $this->getHostgroupFromId($hgId);
            $this->generateObjectInFile($this->hg[$hgId], $hgId);
            Media::getInstance($this->dependencyInjector)->getMediaPathFromId($this->hg[$hgId]['hg_icon_image']);
            Media::getInstance($this->dependencyInjector)->getMediaPathFromId($this->hg[$hgId]['hg_map_icon_image']);
        }
        if (is_null($this->hg[$hgId]) || isset($this->hg[$hgId]['members'][$hostId])) {
            return 1;
        }

        $this->hg[$hgId]['members'][$hostId] = $hostName;
        return 0;
    }

    /**
     * Generate objects
     *
     * @return void
     * @throws \Exception
     */
    public function generateObjects(): void
    {
        foreach ($this->hg as $id => &$value) {
            if (count($value['members']) == 0) {
                continue;
            }
            $value['hostgroup_id'] = $value['hg_id'];

            $this->generateObjectInFile($value, $id);
        }
    }

    /**
     * Get host groups
     *
     * @return array
     */
    public function getHostgroups()
    {
        $result = [];
        foreach ($this->hg as $id => &$value) {
            if (is_null($value) || count($value['members']) == 0) {
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
     * @throws \Exception
     */
    public function reset($createfile = false): void
    {
        $this->hg = [];
        parent::reset($createfile);
    }

    /**
     * Get host group attribute
     *
     * @param int $hgId
     * @param string $attr
     * @return string|null
     */
    public function getString(int $hgId, string $attr)
    {
        return $this->hg[$hgId][$attr] ?? null;
    }
}
