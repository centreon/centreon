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

use App\Kernel;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\AdditionalConnector\Domain\Model\VMWareV6\{VMWareConfig, VSphereServer};

class AdditionalConnectorVMWareV6 extends AbstractObjectJSON
{
    public const MODULE_KEY_NAME = 'centreon_vm_ware_config';

    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
    }

    private function generate(int $pollerId): void
    {
        $kernel = Kernel::createForWeb();

        /** @var ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository */
        $readAdditionalConnectorRepository = $kernel->getContainer()->get(
            ReadAdditionalConnectorRepositoryInterface::class
        ) ?? throw new \Exception('ReadAdditionalConnectorRepositoryInterface not found');

        $additionalConnectorsVMWareV6 = $readAdditionalConnectorRepository->findByPollerAndType($pollerId, Type::VMWARE_V6->value);

        // Cast to object to ensure that an empty JSON and not an empty array is write in file if no ACC exists.
        $object = (object)[];
        if ($additionalConnectorsVMWareV6 !== null) {
            $ACCParameters = $additionalConnectorsVMWareV6->getParameters();

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
                self::MODULE_KEY_NAME => [
                    'vsphere_server' => array_map(
                        fn(VSphereServer $vSphereServer): array => [$vSphereServer->getName() => [
                            'url' => $vSphereServer->getUrl(),
                            'username' => $vSphereServer->getUsername(),
                            'password' => $vSphereServer->getPassword()
                        ]],
                        $vmWareConfig->getVSphereServers()
                    ),
                    'port' => $vmWareConfig->getPort(),
                ]
            ];
        }
        $this->generate_filename = 'centreon_vmware.json';
        $this->generateFile($object, false);
        $this->writeFile($this->backend_instance->getPath());
    }

    public function generateFromPollerId(int $pollerId): void
    {
        $this->generate($pollerId);
    }
}
