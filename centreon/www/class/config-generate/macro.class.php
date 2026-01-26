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
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Macro
 */
class Macro extends AbstractObject
{
    /** @var */
    public $stmt_host;

    /** @var null */
    protected $generate_filename = null;

    /** @var string */
    protected string $object_name;

    /** @var null|PDOStatement */
    protected $stmt_service = null;

    /** @var int */
    private $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $macro_service_cache = [];

    /**
     * Macro constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        if (! $this->isVaultEnabled) {
            $this->getVaultConfigurationStatus();
        }

        $this->buildCache();
    }

    /**
     * @param $service_id
     *
     * @throws PDOException
     * @return array|mixed|null
     */
    public function getServiceMacroByServiceId($service_id)
    {
        // Get from the cache
        if (isset($this->macro_service_cache[$service_id])) {
            return $this->macro_service_cache[$service_id];
        }
        if ($this->done_cache == 1) {
            return null;
        }

        // We get unitary
        if (is_null($this->stmt_service)) {
            $this->stmt_service = $this->backend_instance->db->prepare('SELECT 
                    svc_macro_name, svc_macro_value, is_password
                FROM on_demand_macro_service
                WHERE svc_svc_id = :service_id
            ');
        }

        $this->stmt_service->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $this->stmt_service->execute();
        $this->macro_service_cache[$service_id] = [];
        while (($macro = $this->stmt_service->fetch(PDO::FETCH_ASSOC))) {
            $serviceMacroName = preg_replace(
                '/\$_SERVICE(.*)\$/',
                '_$1',
                $macro['svc_macro_name']
            );

            $this->macro_service_cache[$service_id][$serviceMacroName] = $macro['svc_macro_value'];
        }

        return $this->macro_service_cache[$service_id];
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function cacheMacroService(): void
    {
        $stmt = $this->backend_instance->db->prepare('SELECT 
              svc_svc_id, svc_macro_name, svc_macro_value, is_password
            FROM on_demand_macro_service
        ');
        $stmt->execute();
        while (($macro = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (! isset($this->macro_service_cache[$macro['svc_svc_id']])) {
                $this->macro_service_cache[$macro['svc_svc_id']] = [];
            }

            $serviceMacroName = preg_replace(
                '/\$_SERVICE(.*)\$/',
                '_$1',
                $macro['svc_macro_name']
            );
            $this->macro_service_cache[$macro['svc_svc_id']][$serviceMacroName] = $macro['svc_macro_value'];
        }
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

        $this->cacheMacroService();
        $this->done_cache = 1;
    }
}
