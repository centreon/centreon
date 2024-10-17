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

/**
 * Class
 *
 * @class AdditionalConnectorVmWareV6
 */
class AdditionalConnectorVmWareV6 extends AbstractObjectJSON
{
    /**
     * AdditionalConnectorVmWareV6 constructor
     *
     * @param Backend $backend
     * @param ReadAccRepositoryInterface $readAdditionalConnectorRepository
     */
    public function __construct(
        private readonly Backend $backend,
        private readonly ReadAccRepositoryInterface $readAdditionalConnectorRepository
    ) {
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

            $VSphereServers = array_map(function (array $parameters): VSphereServer {
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
        $directory = $this->backend->generate_path . '/vmware/' . $pollerId;
        $this->backend->createDirectories([$directory]);
        $this->generateFile($object, false);
        $this->writeFile($directory);
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
}
