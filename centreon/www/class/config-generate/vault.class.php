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
use Centreon\Domain\Log\Logger;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Vault
 */
class Vault extends AbstractObjectJSON
{
    /** @var null */
    protected $vaultConfiguration = null;

    /**
     * Vault constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        // Get Centeron Vault Storage configuration
        $kernel = Kernel::createForWeb();
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        $uuidGenerator = $kernel->getContainer()->get(Utility\Interfaces\UUIDGeneratorInterface::class);
        $logger = $kernel->getContainer()->get(Logger::class);
        $this->vaultConfiguration = $readVaultConfigurationRepository->find();
    }

    /**
     * @param $poller_id
     * @param $localhost
     *
     * @return void
     * @throws RuntimeException
     */
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


    /**
     * @param $poller
     *
     * @return void
     * @throws RuntimeException
     */
    public function generateFromPoller($poller): void
    {
        $this->generate($poller['id'], $poller['localhost']);
    }
}
