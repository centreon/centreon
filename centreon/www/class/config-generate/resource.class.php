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
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Pimple\Container;

/**
 * Class
 *
 * @class Resource
 */
class Resource extends AbstractObject
{
    use VaultTrait;

    /** @var string */
    protected $generate_filename = 'resource.cfg';

    /** @var string */
    protected string $object_name;

    /** @var null */
    protected $stmt = null;

    /** @var string[] */
    protected $attributes_hash = ['resources'];

    /** @var null */
    private $connectors = null;

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
    }

    /**
     * @param $poller_id
     *
     * @throws PDOException
     * @return int|void
     */
    public function generateFromPollerId($poller_id)
    {
        if (is_null($poller_id)) {
            return 0;
        }

        if (is_null($this->stmt)) {
            $this->stmt = $this->backend_instance->db->prepare(
                <<<'SQL'
                    SELECT cr.resource_name, cr.resource_line, cr.is_password, ns.is_encryption_ready
                    FROM cfg_resource_instance_relations cfgri
                    INNER JOIN cfg_resource cr
                        ON cr.resource_id = cfgri.resource_id
                    INNER JOIN nagios_server ns
                        ON ns.id = cfgri.instance_id
                    WHERE cfgri.instance_id = :poller_id
                        AND cfgri.resource_id = cr.resource_id
                        AND cr.resource_activate = '1';
                    SQL
            );
        }
        $this->stmt->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $this->stmt->execute();

        $object = ['resources' => []];
        $vaultPaths = [];
        $isPassword = [];

        $results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $value) {
            if ((bool) $value['is_password'] === true) {
                $isPassword[$value['resource_name']] = true;
            }
            $object['resources'][$value['resource_name']] = $value['resource_line'];
            if ($this->isAVaultPath($value['resource_line'])) {
                $vaultPaths[] = $value['resource_line'];
            }
        }
        if ($this->isVaultEnabled && $this->readVaultRepository !== null) {
            $vaultData = $this->readVaultRepository->findFromPaths($vaultPaths);
            foreach ($vaultData as $vaultValues) {
                foreach ($vaultValues as $vaultKey => $vaultValue) {
                    if (array_key_exists($vaultKey, $object['resources']) || array_key_exists('$' . $vaultKey . '$', $object['resources'])) {
                        $object['resources'][$vaultKey] = $vaultValue;
                    }
                }
            }
        }

        $readMonitoringServerRepository = $this->kernel->getContainer()->get(ReadMonitoringServerRepositoryInterface::class);
        $shouldBeEncrypted = $readMonitoringServerRepository->isEncryptionReady($poller_id);
        foreach ($object['resources'] as $macroKey => &$macroValue) {
            if (isset($isPassword[$macroKey])) {
                $macroValue =  $shouldBeEncrypted
                ? 'encrypt::' . $this->engineContextEncryption->crypt($macroValue)
                : $macroValue;
            }
        }
        $this->generateFile($object);
    }
}
