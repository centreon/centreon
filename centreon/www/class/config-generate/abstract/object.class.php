<?php
/*
 * Copyright 2005-2023 Centreon
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
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class AbstractObject
 */
abstract class AbstractObject
{

    protected const VAULT_PATH_REGEX = '/^secret::[^:]*::/';

    /** @var string */
    protected string $object_name;

    /** @var Backend|null  */
    protected $backend_instance = null;
    /** @var string */
    protected $generate_subpath = 'nagios';
    /** @var null */
    protected $generate_filename = null;
    /** @var array */
    protected $exported = [];
    /** @var null */
    protected $fp = null;

    /** @var array */
    protected $attributes_write = [];
    /** @var array */
    protected $attributes_array = [];
    /** @var array */
    protected $attributes_hash = [];
    /** @var array */
    protected $attributes_default = [];
    /** @var null */
    protected $notificationOption = null;

    /** @var bool */
    protected $engine = true;
    /** @var bool */
    protected $broker = false;
    /** @var Container */
    protected $dependencyInjector;

    /** @var bool */
    protected $isVaultEnabled = false;

    /** @var null|ReadVaultRepositoryInterface */
    protected $readVaultRepository = null;

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

        if (!isset($instances[$calledClass])) {
            $instances[$calledClass] = new $calledClass($dependencyInjector);
        }

        return $instances[$calledClass];
    }

    /**
     * AbstractObject constructor
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
    public function close_file(): void
    {
        if (!is_null($this->fp)) {
            fclose($this->fp);
        }
        $this->fp = null;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function reset(): void
    {
        $this->close_file();
        $this->exported = [];
        $this->openFileForUpdate(
            $this->backend_instance->getPath() . DIRECTORY_SEPARATOR . $this->generate_filename
        );
    }

    /**
     * Get the global inheritance option of notification
     * 1 = vertical, 2 = close, 3 = cumulative
     *
     * @return int
     * @throws PDOException
     */
    public function getInheritanceMode() : int
    {
        if (is_null($this->notificationOption)) {
            $stmtNotification = $this->backend_instance->db->query(
                "SELECT `value` FROM options WHERE `key` = 'inheritance_mode'"
            );
            $value = $stmtNotification->fetch();
            $this->notificationOption = (int)$value['value'];
        }
        return $this->notificationOption;
    }

    /**
     * @return void
     */
    private function setHeader(): void
    {
        $header =
            "###################################################################\n" .
            "#                                                                 #\n" .
            "#                       GENERATED BY CENTREON                     #\n" .
            "#                                                                 #\n" .
            "#               Developed by :                                    #\n" .
            "#                   - Julien Mathis                               #\n" .
            "#                   - Romain Le Merlus                            #\n" .
            "#                                                                 #\n" .
            "#                           www.centreon.com                      #\n" .
            "#                For information : contact@centreon.com           #\n" .
            "###################################################################\n" .
            "#                                                                 #\n" .
            "#         Last modification " . sprintf("%-38s#\n", date('Y-m-d H:i')) .
            "#         By " . sprintf("%-53s#\n", $this->backend_instance->getUserName()) .
            "#                                                                 #\n" .
            "###################################################################\n";
        fwrite($this->fp, $this->toUTF8($header));
    }

    /**
     * open file for update and move pointer to the end
     * write header if file is created
     *
     * @param string $filePath
     *
     * @throws Exception
     */
    protected function openFileForUpdate(string $filePath): void
    {
        $alreadyExists = file_exists($filePath);

        if (!($this->fp = @fopen($filePath, 'a+'))) {
            throw new Exception("Cannot open file (writing permission) '" . $filePath . "'");
        }

        if (posix_getuid() === fileowner($filePath)) {
            chmod($filePath, 0660);
        }

        if (! $alreadyExists) {
            $this->setHeader();
        }
    }

    /**
     * @param $str
     *
     * @return array|false|mixed|mixed[]|string|string[]|null
     */
    private function toUTF8($str)
    {
        $finalString = $str;
        if (mb_detect_encoding($finalString, 'UTF-8', true) !== 'UTF-8') {
            $finalString = mb_convert_encoding($finalString, 'UTF-8');
        }
        return $finalString;
    }

    /**
     * @param $object
     *
     * @return void
     */
    protected function writeObject($object)
    {
        $object_file = "\n";
        $object_file .= 'define ' . $this->object_name . " {\n";

        foreach ($this->attributes_write as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != '') {
                $object_file .= sprintf("    %-30s %s \n", $attr, $object[$attr]);
            }
        }

        foreach ($this->attributes_default as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != 2) {
                $object_file .= sprintf("    %-30s %s \n", $attr, $object[$attr]);
            }
        }

        foreach ($this->attributes_array as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr])) {
                $str = '';
                $str_append = '';
                foreach ($object[$attr] as &$value) {
                    if (!is_null($value)) {
                        $str .= $str_append . $value;
                        $str_append = ',';
                    }
                }

                if ($str != '') {
                    $object_file .= sprintf("    %-30s %s \n", $attr, $str);
                }
            }
        }

        foreach ($this->attributes_hash as &$attr) {
            if (!isset($object[$attr])) {
                continue;
            }
            foreach ($object[$attr] as $key => &$value) {
                $object_file .= sprintf("    %-30s %s \n", $key, $value);
            }
        }

        $object_file .= "}\n";
        fwrite($this->fp, $this->toUTF8($object_file));
    }

    /**
     * @param $object
     * @param $id
     *
     * @return void
     * @throws Exception
     */
    protected function generateObjectInFile($object, $id)
    {
        if (is_null($this->fp)) {
            $this->openFileForUpdate(
                $this->backend_instance->getPath() . DIRECTORY_SEPARATOR . $this->generate_filename
            );
        }
        $this->writeObject($object);
        $this->exported[$id] = 1;
    }

    /**
     * @param $object
     *
     * @return void
     */
    private function writeNoObject($object): void
    {
        foreach ($this->attributes_array as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && is_array($object[$attr])) {
                foreach ($object[$attr] as $v) {
                    fwrite($this->fp, $this->toUTF8($attr . "=" . $v . "\n"));
                }
            }
        }

        foreach ($this->attributes_hash as &$attr) {
            if (!isset($object[$attr])) {
                continue;
            }
            foreach ($object[$attr] as $key => &$value) {
                fwrite($this->fp, $this->toUTF8($key . "=" . $value . "\n"));
            }
        }

        foreach ($this->attributes_write as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != '') {
                fwrite($this->fp, $this->toUTF8($attr . "=" . $object[$attr] . "\n"));
            }
        }

        foreach ($this->attributes_default as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != 2) {
                fwrite($this->fp, $this->toUTF8($attr . "=" . $object[$attr] . "\n"));
            }
        }
    }

    /**
     * @param $object
     *
     * @return void
     * @throws Exception
     */
    protected function generateFile($object)
    {
        if (is_null($this->fp)) {
            $this->openFileForUpdate(
                $this->backend_instance->getPath() . DIRECTORY_SEPARATOR . $this->generate_filename
            );
        }

        $this->writeNoObject($object);
    }

    /**
     * @param $id
     *
     * @return int
     */
    public function checkGenerate($id)
    {
        if (isset($this->exported[$id])) {
            return 1;
        }
        return 0;
    }

    /**
     * @return array
     */
    public function getExported()
    {
        return $this->exported ?? [];
    }

    /**
     * @return bool
     */
    public function isEngineObject(): bool
    {
        return $this->engine;
    }

    /**
     * @return bool
     */
    public function isBrokerObject(): bool
    {
        return $this->broker;
    }
}
