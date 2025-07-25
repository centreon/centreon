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

    /** @var null */
    protected $generate_filename = null;

    /** @var string */
    protected string $object_name;

    /** @var null */
    protected $stmt_service = null;

    /** @var int */
    private $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $macro_service_cache = [];

    private $macroHostCache = [];

    /** @var array<int, bool> */
    private $pollersEncryptionReadyStatusByHosts = [];

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

        $this->setPollersEncryptionReadyStatusByHosts();
    }

    private function setPollersEncryptionReadyStatusByHosts(): void
    {
        $result = $this->backend_instance->db->fetchAllAssociativeIndexed(
            <<<'SQL'
                SELECT nsr.host_host_id, ns.is_encryption_ready FROM ns_host_relation nsr
                    INNER JOIN nagios_server ns ON ns.id = nsr.nagios_server_id
                SQL
        );
        foreach ($result as $hostId => $value) {
            $this->pollersEncryptionReadyStatusByHosts[$hostId] = (bool) $value['is_encryption_ready'];
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
}
