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
 * @class Hostgroup
 */
class Hostgroup extends AbstractObject
{
    private const TAG_TYPE = 'hostgroup';
    private const HOSTGROUP_FILENAME = 'hostgroups.cfg';
    private const HOSTGROUP_OBJECT_NAME = 'hostgroup';
    private const TAG_FILENAME = 'tags.cfg';
    private const TAG_OBJECT_NAME = 'tag';

    /** @var array */
    private $hg = [];

    /** @var string */
    protected $generate_filename = self::HOSTGROUP_FILENAME;

    /** @var string */
    protected string $object_name = self::HOSTGROUP_OBJECT_NAME;

    /** @var string */
    protected $attributes_select = '
        hg_id,
        hg_name as hostgroup_name,
        hg_alias as alias
    ';

    /** @var null */
    protected $stmt_hg = null;

    /**
     * @param $hg_id
     *
     * @throws PDOException
     * @return void
     */
    private function getHostgroupFromId($hg_id): void
    {
        if (is_null($this->stmt_hg)) {
            $this->stmt_hg = $this->backend_instance->db->prepare(
                "SELECT  {$this->attributes_select}
                FROM hostgroup
                WHERE hg_id = :hg_id AND hg_activate = '1'"
            );
        }
        $this->stmt_hg->bindParam(':hg_id', $hg_id, PDO::PARAM_INT);
        $this->stmt_hg->execute();
        if ($hostGroup = $this->stmt_hg->fetch(PDO::FETCH_ASSOC)) {
            $this->hg[$hg_id] = $hostGroup;
            $this->hg[$hg_id]['members'] = [];
        }
    }

    /**
     * @param $hg_id
     * @param $host_id
     * @param $host_name
     *
     * @throws PDOException
     * @return int
     */
    public function addHostInHg($hg_id, $host_id, $host_name)
    {
        if (! isset($this->hg[$hg_id])) {
            $this->getHostgroupFromId($hg_id);
        }
        if (is_null($this->hg[$hg_id]) || isset($this->hg[$hg_id]['members'][$host_id])) {
            return 1;
        }

        $this->hg[$hg_id]['members'][$host_id] = $host_name;

        return 0;
    }

    /**
     * Generate host groups / tags and write in file
     */
    public function generateObjects(): void
    {
        $this->generateHostGroups();
        $this->generateTags();
    }

    /**
     * Generate host groups and write in file
     */
    private function generateHostGroups(): void
    {
        $this->generate_filename = self::HOSTGROUP_FILENAME;
        $this->object_name = self::HOSTGROUP_OBJECT_NAME;
        $this->attributes_write = [
            'hostgroup_id',
            'hostgroup_name',
            'alias',
        ];
        $this->attributes_array = [
            'members',
        ];

        // reset cache to allow export of same ids
        parent::reset();

        foreach ($this->hg as $id => &$value) {
            if (count($value['members']) == 0) {
                continue;
            }
            $value['hostgroup_id'] = $value['hg_id'];

            $this->generateObjectInFile($value, $id);
        }
    }

    /**
     * Generate tags and write in file
     */
    private function generateTags(): void
    {
        $this->generate_filename = self::TAG_FILENAME;
        $this->object_name = self::TAG_OBJECT_NAME;
        $this->attributes_write = [
            'id',
            'tag_name',
            'type',
        ];
        $this->attributes_array = [];

        // reset cache to allow export of same ids
        parent::reset();

        foreach ($this->hg as $id => $value) {
            if (count($value['members']) == 0) {
                continue;
            }

            $tag = [
                'id' => $value['hostgroup_id'],
                'tag_name' => $value['hostgroup_name'],
                'type' => self::TAG_TYPE,
            ];

            $this->generateObjectInFile($tag, $id);
        }
    }

    /**
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
     * @throws Exception
     * @return void
     */
    public function reset(): void
    {
        parent::reset();
        foreach ($this->hg as &$value) {
            if (! is_null($value)) {
                $value['members'] = [];
            }
        }
    }

    /**
     * @param $hg_id
     * @param $attr
     *
     * @return mixed|null
     */
    public function getString($hg_id, $attr)
    {
        return $this->hg[$hg_id][$attr] ?? null;
    }
}
