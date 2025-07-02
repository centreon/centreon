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

use App\Kernel;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Pimple\Container;

/**
 * Class
 *
 * @class AbstractObjectJSON
 */
abstract class AbstractObjectJSON
{
    /** @var Backend|null */
    protected $backend_instance = null;

    /** @var string|null */
    protected $generate_filename = null;

    /** @var Container */
    protected $dependencyInjector;

    /** @var array */
    protected $content = [];

    /** @var bool */
    protected $isVaultEnabled = false;

    /** @var null|ReadVaultRepositoryInterface */
    protected $readVaultRepository = null;

    /**
     * Get Centreon Vault Configuration Status
     *
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return void
     */
    public function getVaultConfigurationStatus(): void
    {
        $kernel = Kernel::createForWeb();
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        $featureFlag = $kernel->getContainer()->get(FeatureFlags::class);
        $vaultConfiguration = $readVaultConfigurationRepository->find();
        if ($vaultConfiguration !== null && $featureFlag->isEnabled('vault')) {
            $this->isVaultEnabled = true;
            $this->readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
        }
    }

    /**
     * @param Container $dependencyInjector
     * @return static
     */
    public static function getInstance(Container $dependencyInjector): static
    {
        /**
         * @var array<string, static>
         */
        static $instances = [];

        /**
         * @var class-string<static>
         */
        $calledClass = static::class;

        if (! isset($instances[$calledClass])) {
            $instances[$calledClass] = new $calledClass($dependencyInjector);
        }

        return $instances[$calledClass];
    }

    /**
     * AbstractObjectJSON constructor
     *
     * @param Container $dependencyInjector
     */
    protected function __construct(Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->backend_instance = Backend::getInstance($this->dependencyInjector);
    }

    /**
     * @return void
     */
    public function reset()
    {
    }

    /**
     * @param $dir
     *
     * @throws RuntimeException
     * @return void
     */
    protected function writeFile($dir)
    {
        $full_file = $dir . '/' . $this->generate_filename;
        if ($handle = fopen($full_file, 'w')) {
            if (! fwrite($handle, $this->content)) {
                throw new RuntimeException('Cannot write to file "' . $full_file . '"');
            }
            fclose($handle);
        } else {
            throw new Exception('Cannot open file ' . $full_file);
        }
    }

    /**
     * @param $object
     * @param $brokerType
     *
     * @return void
     */
    protected function generateFile($object, $brokerType = true): void
    {
        $data = $brokerType ? ['centreonBroker' => $object] : $object;

        $this->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
