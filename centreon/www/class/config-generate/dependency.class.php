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
 * @class Dependency
 */
class Dependency extends AbstractObject
{
    /** @var */
    public $dependency_cache;

    // Not done system without cache. TODO
    /** @var int */
    private $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var int */
    private $has_dependency = 1; // by default, we have.

    /** @var array */
    private $generated_dependencies = [];

    /** @var array */
    private $dependency_linked_host_parent_cache = [];

    /** @var array */
    private $dependency_linked_host_child_cache = [];

    /** @var array */
    private $dependency_linked_hg_parent_cache = [];

    /** @var array */
    private $dependency_linked_hg_child_cache = [];

    /** @var array */
    private $dependency_linked_service_parent_cache = [];

    /** @var array */
    private $dependency_linked_service_child_cache = [];

    /** @var array */
    private $dependency_linked_sg_parent_cache = [];

    /** @var array */
    private $dependency_linked_sg_child_cache = [];

    /** @var array */
    private $dependency_linked_meta_parent_cache = [];

    /** @var array */
    private $dependency_linked_meta_child_cache = [];

    /** @var string */
    protected $generate_filename = 'dependencies.cfg';

    /** @var string */
    protected string $object_name = 'hostdependency';

    /** @var string */
    protected $attributes_select = "
        dep_id,
        dep_name as ';dependency_name',
        execution_failure_criteria,
        notification_failure_criteria,
        inherits_parent
    ";

    /** @var string[] */
    protected $attributes_write = [';dependency_name', 'execution_failure_criteria', 'notification_failure_criteria', 'inherits_parent'];

    /** @var string[] */
    protected $attributes_array = ['dependent_host_name', 'host_name', 'dependent_service_description', 'service_description', 'dependent_hostgroup_name', 'hostgroup_name', 'dependent_servicegroup_name', 'servicegroup_name'];

    /** @var Host|null */
    protected $host_instance = null;

    /** @var Service|null */
    protected $service_instance = null;

    /** @var Hostgroup|null */
    protected $hg_instance = null;

    /** @var Servicegroup|null */
    protected $sg_instance = null;

    /**
     * Dependency constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->host_instance = Host::getInstance($this->dependencyInjector);
        $this->service_instance = Service::getInstance($this->dependencyInjector);
        $this->hg_instance = Hostgroup::getInstance($this->dependencyInjector);
        $this->sg_instance = Servicegroup::getInstance($this->dependencyInjector);
        $this->buildCache();
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function getDependencyCache(): void
    {
        $stmt = $this->backend_instance->db->prepare("SELECT 
                    {$this->attributes_select}
                FROM dependency
        ");
        $stmt->execute();
        $this->dependency_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

        if (count($this->dependency_cache) == 0) {
            $this->has_dependency = 0;
        }
    }

    /**
     * @throws PDOException
     * @return int|void
     */
    private function getDependencyLinkedCache()
    {
        if ($this->has_dependency == 0) {
            return 0;
        }

        // Host dependency
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, host_host_id
                FROM dependency_hostParent_relation
        ');
        $stmt->execute();
        $this->dependency_linked_host_parent_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, host_host_id
                FROM dependency_hostChild_relation
        ');
        $stmt->execute();
        $this->dependency_linked_host_child_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

        // Hostgroup dependency
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, hostgroup_hg_id
                FROM dependency_hostgroupParent_relation
        ');
        $stmt->execute();
        $this->dependency_linked_hg_parent_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, hostgroup_hg_id
                FROM dependency_hostgroupChild_relation
        ');
        $stmt->execute();
        $this->dependency_linked_hg_child_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

        // Servicegroup dependency
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, servicegroup_sg_id
                FROM dependency_servicegroupParent_relation
        ');
        $stmt->execute();
        $this->dependency_linked_sg_parent_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, servicegroup_sg_id
                FROM dependency_servicegroupChild_relation
        ');
        $stmt->execute();
        $this->dependency_linked_sg_child_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

        // Metaservice dependency
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, meta_service_meta_id
                FROM dependency_metaserviceParent_relation
        ');
        $stmt->execute();
        $this->dependency_linked_meta_parent_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, meta_service_meta_id
                FROM dependency_metaserviceChild_relation
        ');
        $stmt->execute();
        $this->dependency_linked_meta_child_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

        // Service dependency
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, host_host_id, service_service_id
                FROM dependency_serviceParent_relation
        ');
        $stmt->execute();
        $this->dependency_linked_service_parent_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
        $stmt = $this->backend_instance->db->prepare('SELECT 
                    dependency_dep_id, host_host_id, service_service_id 
                FROM dependency_serviceChild_relation
        ');
        $stmt->execute();
        $this->dependency_linked_service_child_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
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

        $this->getDependencyCache();
        $this->getDependencyLinkedCache();
        $this->done_cache = 1;
    }

    /**
     * @throws Exception
     * @return void
     */
    public function doHost(): void
    {
        $this->object_name = 'hostdependency';
        foreach ($this->dependency_cache as $dp_id => $dependency) {
            $dependency['host_name'] = [];
            if (isset($this->dependency_linked_host_parent_cache[$dp_id])) {
                foreach ($this->dependency_linked_host_parent_cache[$dp_id] as $value) {
                    if ($this->host_instance->checkGenerate($value)) {
                        $dependency['host_name'][] = $this->host_instance->getString($value, 'host_name');
                    }
                }
            }

            $dependency['dependent_host_name'] = [];
            if (isset($this->dependency_linked_host_child_cache[$dp_id])) {
                foreach ($this->dependency_linked_host_child_cache[$dp_id] as $value) {
                    if ($this->host_instance->checkGenerate($value)) {
                        $dependency['dependent_host_name'][] = $this->host_instance->getString($value, 'host_name');
                    }
                }
            }

            if (count($dependency['host_name']) == 0 || count($dependency['dependent_host_name']) == 0) {
                continue;
            }

            $this->generateObjectInFile($dependency, 0);
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    public function doService(): void
    {
        $this->object_name = 'servicedependency';
        foreach ($this->dependency_cache as $dp_id => $dependency) {
            if (! isset($this->dependency_linked_service_parent_cache[$dp_id])) {
                continue;
            }
            foreach ($this->dependency_linked_service_parent_cache[$dp_id] as $value) {
                if (! isset($this->dependency_linked_service_child_cache[$dp_id])) {
                    continue;
                }
                if ($this->service_instance->checkGenerate(
                    $value['host_host_id'] . '.' . $value['service_service_id']
                )) {
                    $dependency['host_name'] = [$this->host_instance->getString($value['host_host_id'], 'host_name')];
                    $dependency['service_description'] = [$this->service_instance->getString($value['service_service_id'], 'service_description')];

                    foreach ($this->dependency_linked_service_child_cache[$dp_id] as $value2) {
                        if ($this->service_instance->checkGenerate(
                            $value2['host_host_id'] . '.' . $value2['service_service_id']
                        )) {
                            $dependency['dependent_host_name'] = [$this->host_instance->getString($value2['host_host_id'], 'host_name')];
                            $dependency['dependent_service_description'] = [$this->service_instance->getString($value2['service_service_id'], 'service_description')];

                            $this->generateObjectInFile($dependency, 0);
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     * @return int|void
     */
    public function doMetaService()
    {
        $meta_instance = MetaService::getInstance($this->dependencyInjector);
        if (! $meta_instance->hasMetaServices()) {
            return 0;
        }

        $this->object_name = 'servicedependency';
        foreach ($this->dependency_cache as $dp_id => $dependency) {
            if (! isset($this->dependency_linked_meta_parent_cache[$dp_id])) {
                continue;
            }
            foreach ($this->dependency_linked_meta_parent_cache[$dp_id] as $meta_id) {
                if (! isset($this->dependency_linked_meta_child_cache[$dp_id])) {
                    continue;
                }
                if ($meta_instance->checkGenerate($meta_id)) {
                    $dependency['host_name'] = ['_Module_Meta'];
                    $dependency['service_description'] = ['meta_' . $meta_id];

                    foreach ($this->dependency_linked_meta_child_cache[$dp_id] as $meta_id2) {
                        if ($meta_instance->checkGenerate($meta_id2)) {
                            $dependency['dependent_host_name'] = ['_Module_Meta'];
                            $dependency['dependent_service_description'] = ['meta_' . $meta_id2];

                            $this->generateObjectInFile($dependency, 0);
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    public function doHostgroup(): void
    {
        $this->object_name = 'hostdependency';
        foreach ($this->dependency_cache as $dp_id => $dependency) {
            $dependency['hostgroup_name'] = [];
            if (isset($this->dependency_linked_hg_parent_cache[$dp_id])) {
                foreach ($this->dependency_linked_hg_parent_cache[$dp_id] as $value) {
                    if ($this->hg_instance->checkGenerate($value)) {
                        $dependency['hostgroup_name'][] = $this->hg_instance->getString($value, 'hostgroup_name');
                    }
                }
            }

            $dependency['dependent_hostgroup_name'] = [];
            if (isset($this->dependency_linked_hg_child_cache[$dp_id])) {
                foreach ($this->dependency_linked_hg_child_cache[$dp_id] as $value) {
                    if ($this->hg_instance->checkGenerate($value)) {
                        $dependency['dependent_hostgroup_name'][] = $this->hg_instance->getString(
                            $value,
                            'hostgroup_name'
                        );
                    }
                }
            }

            if (count($dependency['dependent_hostgroup_name']) == 0 || count($dependency['hostgroup_name']) == 0) {
                continue;
            }

            $this->generateObjectInFile($dependency, 0);
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    public function doServicegroup(): void
    {
        $this->object_name = 'servicedependency';
        foreach ($this->dependency_cache as $dp_id => $dependency) {
            $dependency['servicegroup_name'] = [];
            if (isset($this->dependency_linked_sg_parent_cache[$dp_id])) {
                foreach ($this->dependency_linked_sg_parent_cache[$dp_id] as $value) {
                    if ($this->sg_instance->checkGenerate($value)) {
                        $dependency['servicegroup_name'][] = $this->sg_instance->getString($value, 'servicegroup_name');
                    }
                }
            }

            $dependency['dependent_servicegroup_name'] = [];
            if (isset($this->dependency_linked_sg_child_cache[$dp_id])) {
                foreach ($this->dependency_linked_sg_child_cache[$dp_id] as $value) {
                    if ($this->sg_instance->checkGenerate($value)) {
                        $dependency['dependent_servicegroup_name'][] = $this->sg_instance->getString(
                            $value,
                            'servicegroup_name'
                        );
                    }
                }
            }
            if (count($dependency['dependent_servicegroup_name']) == 0
                || count($dependency['servicegroup_name']) == 0) {
                continue;
            }
            $this->generateObjectInFile($dependency, 0);
        }
    }

    /**
     * @throws Exception
     * @return int|void
     */
    public function generateObjects()
    {
        if ($this->has_dependency == 0) {
            return 0;
        }

        $this->doHost();
        $this->doService();
        $this->doHostgroup();
        $this->doServicegroup();
        $this->doMetaService();
    }

    /**
     * @throws Exception
     * @return void
     */
    public function reset(): void
    {
        $this->generated_dependencies = [];
        parent::reset();
    }

    /**
     * @return int
     */
    public function hasDependency()
    {
        return $this->has_dependency;
    }

    /**
     * @return array
     */
    public function getGeneratedDependencies()
    {
        return $this->generated_dependencies;
    }
}
