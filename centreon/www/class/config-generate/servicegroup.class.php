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

use Pimple\Container;

/**
 * Class
 *
 * @class Servicegroup
 */
class Servicegroup extends AbstractObject
{
    private const TAG_TYPE = 'servicegroup';
    private const SERVICEGROUP_FILENAME = 'servicegroups.cfg';
    private const SERVICEGROUP_OBJECT_NAME = 'servicegroup';
    private const TAG_FILENAME = 'tags.cfg';
    private const TAG_OBJECT_NAME = 'tag';

    /** @var int */
    private $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $sg = [];

    /** @var array */
    private $sg_relation_cache = [];

    /** @var string */
    protected $generate_filename = self::SERVICEGROUP_FILENAME;

    /** @var string */
    protected string $object_name = self::SERVICEGROUP_OBJECT_NAME;

    /** @var string */
    protected $attributes_select = '
        sg_id,
        sg_name as servicegroup_name,
        sg_alias as alias
    ';

    /** @var null */
    protected $stmt_sg = null;

    /** @var null */
    protected $stmt_service_sg = null;

    /** @var null */
    protected $stmt_stpl_sg = null;

    /**
     * Servicegroup constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * @param $sg_id
     *
     * @throws PDOException
     * @return void
     */
    private function getServicegroupFromId($sg_id): void
    {
        if (is_null($this->stmt_sg)) {
            $this->stmt_sg = $this->backend_instance->db->prepare("SELECT
                {$this->attributes_select}
            FROM servicegroup
            WHERE sg_id = :sg_id AND sg_activate = '1'
            ");
        }

        $this->stmt_sg->bindParam(':sg_id', $sg_id, PDO::PARAM_INT);
        $this->stmt_sg->execute();

        if ($serviceGroup = $this->stmt_sg->fetch(PDO::FETCH_ASSOC)) {
            $this->sg[$sg_id] = $serviceGroup;
            $this->sg[$sg_id]['members_cache'] = [];
            $this->sg[$sg_id]['members'] = [];
        }
    }

    /**
     * @param $sg_id
     * @param $service_id
     * @param $service_description
     * @param $host_id
     * @param $host_name
     *
     * @throws PDOException
     * @return int
     */
    public function addServiceInSg($sg_id, $service_id, $service_description, $host_id, $host_name)
    {
        if (! isset($this->sg[$sg_id])) {
            $this->getServicegroupFromId($sg_id);
        }
        if (is_null($this->sg[$sg_id]) || isset($this->sg[$sg_id]['members_cache'][$host_id . '_' . $service_id])) {
            return 1;
        }

        $this->sg[$sg_id]['members_cache'][$host_id . '_' . $service_id] = [$host_name, $service_description];

        return 0;
    }

    /**
     * @throws PDOException
     * @return int|void
     */
    private function buildCache()
    {
        if ($this->done_cache == 1) {
            return 0;
        }

        $stmt = $this->backend_instance->db->prepare(
            "SELECT service_service_id, servicegroup_sg_id, host_host_id
            FROM servicegroup_relation sgr, servicegroup sg
            WHERE sgr.servicegroup_sg_id = sg.sg_id AND sg.sg_activate = '1'"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->sg_relation_cache[$value['service_service_id']])) {
                $this->sg_relation_cache[$value['service_service_id']][] = $value;
            } else {
                $this->sg_relation_cache[$value['service_service_id']] = [$value];
            }
        }

        $this->done_cache = 1;
    }

    /**
     * @param $service_id
     *
     * @throws PDOException
     * @return array|mixed
     */
    public function getServiceGroupsForStpl($service_id)
    {
        // Get from the cache
        if (isset($this->sg_relation_cache[$service_id])) {
            return $this->sg_relation_cache[$service_id];
        }
        if ($this->done_cache == 1) {
            return [];
        }

        if (is_null($this->stmt_stpl_sg)) {
            // Meaning, linked with the host or hostgroup (for the null expression)
            $this->stmt_stpl_sg = $this->backend_instance->db->prepare(
                "SELECT servicegroup_sg_id, host_host_id, service_service_id
                FROM servicegroup_relation sgr, servicegroup sg
                WHERE service_service_id = :service_id
                AND sgr.servicegroup_sg_id = sg.sg_id AND sg.sg_activate = '1'"
            );
        }
        $this->stmt_stpl_sg->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $this->stmt_stpl_sg->execute();
        $this->sg_relation_cache[$service_id] = array_merge(
            $this->stmt_stpl_sg->fetchAll(PDO::FETCH_ASSOC),
            $this->sg_relation_cache[$service_id]
        );

        return $this->sg_relation_cache[$service_id];
    }

    /**
     * @param $host_id
     * @param $service_id
     *
     * @throws PDOException
     * @return array|mixed
     */
    public function getServiceGroupsForService($host_id, $service_id)
    {
        // Get from the cache
        if (isset($this->sg_relation_cache[$service_id])) {
            return $this->sg_relation_cache[$service_id];
        }
        if ($this->done_cache == 1) {
            return [];
        }

        if (is_null($this->stmt_service_sg)) {
            // Meaning, linked with the host or hostgroup (for the null expression)
            $this->stmt_service_sg = $this->backend_instance->db->prepare(
                "SELECT servicegroup_sg_id, host_host_id, service_service_id
                FROM servicegroup_relation sgr, servicegroup sg
                WHERE service_service_id = :service_id AND (host_host_id = :host_id OR host_host_id IS NULL)
                AND sgr.servicegroup_sg_id = sg.sg_id AND sg.sg_activate = '1'"
            );
        }
        $this->stmt_service_sg->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $this->stmt_service_sg->bindParam(':host_id', $host_id, PDO::PARAM_INT);
        $this->stmt_service_sg->execute();
        $this->sg_relation_cache[$service_id] = array_merge(
            $this->stmt_service_sg->fetchAll(PDO::FETCH_ASSOC),
            $this->sg_relation_cache[$service_id]
        );

        return $this->sg_relation_cache[$service_id];
    }

    /**
     * Generate service groups / tags and write in file
     */
    public function generateObjects(): void
    {
        $this->generateServiceGroups();
        $this->generateTags();
    }

    /**
     * Generate service groups and write in file
     */
    private function generateServiceGroups(): void
    {
        $this->generate_filename = self::SERVICEGROUP_FILENAME;
        $this->object_name = self::SERVICEGROUP_OBJECT_NAME;
        $this->attributes_write = [
            'servicegroup_id',
            'servicegroup_name',
            'alias',
        ];
        $this->attributes_array = [
            'members',
        ];

        // reset cache to allow export of same ids
        parent::reset();

        foreach ($this->sg as $id => &$value) {
            if (count($value['members_cache']) == 0) {
                continue;
            }

            $value['servicegroup_id'] = $value['sg_id'];

            foreach ($value['members_cache'] as $content) {
                array_push($this->sg[$id]['members'], $content[0], $content[1]);
            }
            $this->generateObjectInFile($this->sg[$id], $id);
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

        foreach ($this->sg as $id => &$value) {
            if (count($value['members_cache']) == 0) {
                continue;
            }

            $tag = [
                'id' => $value['servicegroup_id'],
                'tag_name' => $value['servicegroup_name'],
                'type' => self::TAG_TYPE,
            ];

            $this->generateObjectInFile($tag, $id);
        }
    }

    /**
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
     * @throws Exception
     * @return void
     */
    public function reset(): void
    {
        parent::reset();
        foreach ($this->sg as &$value) {
            if (! is_null($value)) {
                $value['members_cache'] = [];
                $value['members'] = [];
            }
        }
    }

    /**
     * @param $sg_id
     * @param $attr
     *
     * @return mixed|null
     */
    public function getString($sg_id, $attr)
    {
        return $this->sg[$sg_id][$attr] ?? null;
    }
}
