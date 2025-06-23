<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

use Assert\AssertionFailedException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6\{VmWareConfig, VSphereServer};
use Core\Common\Application\UseCase\VaultTrait;
use Pimple\Container;

/**
 * Class
 *
 * @class AdditionalConnectorVmWareV6
 */
class AdditionalConnectorVmWareV6 extends AbstractObjectJSON
{
    use VaultTrait;

    public const CENTREON_SYSTEM_USER = 'centreon';

    /**
     * AdditionalConnectorVmWareV6 constructor
     *
     * @param Backend $backend
     * @param ReadAccRepositoryInterface $readAdditionalConnectorRepository
     */
    public function __construct(
        Container $dependencyInjector,
        private readonly Backend $backend,
        private readonly ReadAccRepositoryInterface $readAdditionalConnectorRepository
    ) {
        parent::__construct($dependencyInjector);
        if (! $this->isVaultEnabled) {
            $this->getVaultConfigurationStatus();
        }
    }

    /**
     * Generate VM Ware v6 configuration file for plugins.
     *
     * @param int $pollerId
     *
     * @throws \Exception|AssertionFailedException
     */
    private function generate(int $pollerId): void
    {
        $additionalConnectorsVMWareV6 = $this->readAdditionalConnectorRepository
            ->findByPollerAndType($pollerId, Type::VMWARE_V6->value);

        // Cast to object to ensure that an empty JSON and not an empty array is write in file if no ACC exists.
        $object = (object) [];
        if ($additionalConnectorsVMWareV6 !== null) {
            $ACCParameters = $additionalConnectorsVMWareV6->getParameters()->getDecryptedData();

            $VSphereServers = array_map(function (array $parameters) use (&$vaultData): VSphereServer {
                if (
                    $this->isVaultEnabled
                    && $this->readVaultRepository !== null
                    && $this->isAVaultPath($parameters['password'])
                ) {
                    $vaultData ??= $this->readVaultRepository->findFromPath($parameters['password']);
                    $parameters['name'] . '_' . 'password';
                    if (array_key_exists($parameters['name'] . '_' . 'password', $vaultData)) {
                        $parameters['password'] = $vaultData[$parameters['name'] . '_' . 'password'];
                    }
                    if (array_key_exists($parameters['name'] . '_' . 'username', $vaultData)) {
                        $parameters['username'] = $vaultData[$parameters['name'] . '_' . 'username'];
                    }
                }
                return new VSphereServer(
                    name: $parameters['name'],
                    url: $parameters['url'],
                    username: $parameters['username'],
                    password: $parameters['password']
                );
            }, $ACCParameters['vcenters']);

            $vmWareConfig = new VMWareConfig(vSphereServers: $VSphereServers, port: $ACCParameters['port']);

            $object = [
                'vsphere_server' => array_map(
                    fn(VSphereServer $vSphereServer): array => [
                        'name' => $vSphereServer->getName(),
                        'url' => $vSphereServer->getUrl(),
                        'username' => $vSphereServer->getUsername(),
                        'password' => $vSphereServer->getPassword()
                    ],
                    $vmWareConfig->getVSphereServers()
                ),
                'port' => $vmWareConfig->getPort(),
            ];
        }
        $this->generate_filename = 'centreon_vmware.json';
        $this->generateFile($object, false);
        $this->writeFile($this->backend->getPath());
    }

    /**
     * @param int $pollerId
     *
     * @return void
     * @throws AssertionFailedException
     */
    public function generateFromPollerId(int $pollerId): void
    {
        $this->generate($pollerId);
    }

    /**
     * Write the file ACC configuration centreon_vmware.json file in the given directory
     *
     * @param $dir
     *
     * @throws \RuntimeException|\Exception
     */
    protected function writeFile($dir)
    {
        $fullFile = $dir . '/' . $this->generate_filename;
        if ($handle = fopen($fullFile, 'w')) {
            $content = is_array($this->content) ? json_encode($this->content) : $this->content;
            if (!fwrite($handle, $content)) {
                throw new \RuntimeException('Cannot write to file "' . $fullFile . '"');
            }
            fclose($handle);

            /**
             * Change VMWare files owner to '660 apache centreon'
             * RW for centreon group are necessary for Gorgone Daemon.
             */
            chmod($fullFile, 0660);
            chgrp($fullFile, self::CENTREON_SYSTEM_USER);
        } else {
            throw new \Exception("Cannot open file " . $fullFile);
        }
    }
}
