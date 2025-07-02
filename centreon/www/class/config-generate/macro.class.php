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

use Core\Common\Application\UseCase\VaultTrait;
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
    use VaultTrait;

    /** @var */
    public $stmt_host;

    /** @var int */
    private $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $macro_service_cache = [];

    private $macroHostCache = [];

    /** @var null */
    protected $generate_filename = null;

    /** @var string */
    protected string $object_name;

    /** @var null */
    protected $stmt_service = null;

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

        if ($this->isVaultEnabled && $this->readVaultRepository !== null) {
            $vaultPathByServices = $this->getVaultPathByResources($this->macro_service_cache);
            $vaultData = $this->readVaultRepository->findFromPaths($vaultPathByServices);
            foreach ($vaultData as $serviceId => $macros) {
                foreach ($macros as $macroName => $macroValue) {
                    $serviceMacroName = preg_replace(
                        '/\_SERVICE(.*)$/',
                        '_$1',
                        $macroName
                    );
                    $this->macro_service_cache[$serviceId][$serviceMacroName] = $macroValue;
                }
            }
        }
    }

    private function cacheMacroHost(): void
    {
        $stmt = $this->backend_instance->db->executeQuery(
            <<<'SQL'
                SELECT
                host_host_id, host_macro_name, host_macro_value, is_password
                FROM on_demand_macro_host;
                SQL
        );

        while (($macro = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (! isset($this->macroHostCache[$macro['host_host_id']])) {
                $this->macroHostCache[$macro['host_host_id']] = [];
            }

            $hostMacroName = preg_replace(
                '/\$_HOST(.*)\$/',
                '_$1',
                $macro['host_macro_name']
            );
            $this->macroHostCache[$macro['host_host_id']][$hostMacroName] = $macro['host_macro_value'];
        }

        $stmt = $this->backend_instance->db->executeQuery(
            <<<'SQL'
                SELECT
                host_id, host_snmp_community
                FROM host
                WHERE host_snmp_community IS NOT NULL
                OR host_snmp_community != '';
                SQL
        );

        while (($hostSnmpCommunity = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $this->macroHostCache[$hostSnmpCommunity['host_id']]['_SNMPCOMMUNITY'] = $hostSnmpCommunity['host_snmp_community'];
        }

        if ($this->isVaultEnabled && $this->readVaultRepository !== null) {
            $vaultPathByHosts = $this->getVaultPathByResources($this->macroHostCache);
            $vaultData = $this->readVaultRepository->findFromPaths($vaultPathByHosts);
            foreach ($vaultData as $hostId => $macros) {
                foreach ($macros as $macroName => $macroValue) {
                    $hostMacroName = preg_replace(
                        '/\_HOST(.*)$/',
                        '_$1',
                        $macroName
                    );
                    $this->macroHostCache[$hostId][$hostMacroName] = $macroValue;
                }
            }
        }
    }

    /**
     * @param array{int, array{string, string}} $macros Macros on format [ResourceId => [MacroName, MacroValue]]
     * @return array{int, string} vault path indexed by service id
     */
    private function getVaultPathByResources(array $macros): array
    {
        $vaultPathByResources = [];
        foreach ($macros as $resourceId => $macroInformation) {
            foreach ($macroInformation as $macroValue) {
                /**
                 * Check that the value is a vault path and that we haven't store it already
                 * As macros are stored by resources in vault. All the macros for the same service has the same vault path
                 */
                if ($this->isAVaultPath($macroValue) && ! array_key_exists($resourceId, $vaultPathByResources)) {
                    $vaultPathByResources[$resourceId] = $macroValue;
                }
            }
        }

        return $vaultPathByResources;
    }

    /**
     * @param $service_id
     *
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
    }

    /**
     * @param $hostId
     *
     * @return array|mixed|null
     */
    public function getHostMacroByHostId($hostId)
    {
        // Get from the cache
        if (isset($this->macroHostCache[$hostId])) {
            return $this->macroHostCache[$hostId];
        }
        if ($this->done_cache == 1) {
            return null;
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
        $this->cacheMacroHost();
        $this->done_cache = 1;
    }
}
