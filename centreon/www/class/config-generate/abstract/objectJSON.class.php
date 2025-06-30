<?php

/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use App\Kernel;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Pimple\Container;
use Security\Interfaces\EncryptionInterface;

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

    protected Kernel $kernel;

    protected EncryptionInterface $engineContextEncryption;

    /**
     * Get Centreon Vault Configuration Status
     *
     * @return void
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function getVaultConfigurationStatus(): void
    {
        $readVaultConfigurationRepository = $this->kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        $featureFlag = $this->kernel->getContainer()->get(FeatureFlags::class);
        $vaultConfiguration = $readVaultConfigurationRepository->find();
        if ($vaultConfiguration !== null && $featureFlag->isEnabled('vault')) {
            $this->isVaultEnabled = true;
            $this->readVaultRepository = $this->kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
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

        if (!isset($instances[$calledClass])) {
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
        $this->kernel = Kernel::createForWeb();
        $this->dependencyInjector = $dependencyInjector;
        $this->backend_instance = Backend::getInstance($this->dependencyInjector);
        $this->engineContextEncryption = $this->kernel->getContainer()->get(EncryptionInterface::class);
        $engineContext = file_get_contents('/etc/centreon-engine/engine-context.json');
        if ($engineContext === false) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Unable to parse content of '/etc/centreon-engine/engine-context.json', credentials will not be encrypted"
            );
        }
        try {
            $engineContext = json_decode($engineContext, true, flags: JSON_THROW_ON_ERROR);
            $this->engineContextEncryption->setFirstKey($engineContext['app_secret'])->setSecondKey($engineContext['salt']);
        } catch (\JsonException $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Unable to parse content of '/etc/centreon-engine/engine-context.json', credentials will not be encrypted",
                exception: $ex
            );

            throw $ex;
        }
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
     * @return void
     * @throws RuntimeException
     */
    protected function writeFile($dir)
    {
        $full_file = $dir . '/' . $this->generate_filename;
        if ($handle = fopen($full_file, 'w')) {
            if (!fwrite($handle, $this->content)) {
                throw new RuntimeException('Cannot write to file "' . $full_file . '"');
            }
            fclose($handle);
        } else {
            throw new Exception("Cannot open file " . $full_file);
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

        $this->content = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }
}
