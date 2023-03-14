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

class Vault extends AbstractObjectJSON
{
    protected $vaultConfiguration = null;

    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        // Get Centeron Vault Storage configuration
        $kernel = \App\Kernel::createForWeb();
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        $uuidGenerator = $kernel->getContainer()->get(Utility\Interfaces\UUIDGeneratorInterface::class);
        $logger = $kernel->getContainer()->get(\Centreon\Domain\Log\LegacyLogger::class);
        $this->vaultConfiguration = $readVaultConfigurationRepository->findDefaultVaultConfiguration();
    }

    private function generate($poller_id, $localhost): void
    {
        if ($this->vaultConfiguration === null) {
            return;
        }
        // Base parameters
        $object[$this->vaultConfiguration->getName()] = [
            'vault-address'=> $this->vaultConfiguration->getAddress(),
            'vault-port' => $this->vaultConfiguration->getPort(),
            'vault-protocol' => 'https'
        ];

        // Generate file
        $this->generate_filename = 'centreonvault.json';
        $this->generateFile($object, false);
        $this->writeFile($this->backend_instance->getPath());
    }


    public function generateFromPoller($poller): void
    {
        $this->generate($poller['id'], $poller['localhost']);
    }
}
