<?php
<<<<<<< HEAD

/*
 * Copyright 2005-2022 Centreon
=======
/*
 * Copyright 2005-2015 Centreon
>>>>>>> centreon/dev-21.10.x
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
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

namespace CentreonLegacy\Core\Install\Step;

<<<<<<< HEAD
use Pimple\Container;

abstract class AbstractStep implements StepInterface
{
    protected const TMP_INSTALL_DIR = __DIR__ . '/../../../../../www/install/tmp';

    /**
     * @param Container $dependencyInjector
     */
    public function __construct(protected Container $dependencyInjector)
    {
    }

    /**
     * Get configuration from json file
     *
     * @param string $file
     * @param array|string $configuration
     * @return array<int|string,string>|string
     */
    private function getConfiguration($file, $configuration = [])
    {
        if ($this->dependencyInjector['filesystem']->exists($file)) {
            $configuration = json_decode(file_get_contents($file), true);
            foreach ($configuration as $key => $configurationValue) {
                $configuration[$key] = htmlspecialchars($configurationValue, ENT_QUOTES);
            }
=======
abstract class AbstractStep implements StepInterface
{
    protected $dependencyInjector;

    public function __construct($dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * @param $file
     * @param array $configuration
     * @return array|string
     */
    private function getConfiguration($file, $configuration = array())
    {
        if ($this->dependencyInjector['filesystem']->exists($file)) {
            $configuration = json_decode(file_get_contents($file), true);
>>>>>>> centreon/dev-21.10.x
        }

        return $configuration;
    }

<<<<<<< HEAD
    /**
     * Get base configuration (paths)
     *
     * @return array<string,string>
     */
    public function getBaseConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/configuration.json');
    }

    /**
     * Get database access configuration
     *
     * @return array<string,string>
     */
    public function getDatabaseConfiguration()
    {
        $configuration = [
=======
    public function getBaseConfiguration()
    {
        return $this->getConfiguration(__DIR__ . '/../../../../../www/install/tmp/configuration.json');
    }

    public function getDatabaseConfiguration()
    {
        $configuration = array(
>>>>>>> centreon/dev-21.10.x
            'address' => '',
            'port' => '',
            'root_user' => 'root',
            'root_password' => '',
            'db_configuration' => 'centreon',
            'db_storage' => 'centreon_storage',
            'db_user' => 'centreon',
            'db_password' => '',
            'db_password_confirm' => ''
<<<<<<< HEAD
        ];

        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/database.json', $configuration);
    }

    /**
     * Get admin user configuration
     *
     * @return array<string,string>
     */
    public function getAdminConfiguration()
    {
        $configuration = [
=======
        );

        return $this->getConfiguration(__DIR__ . "/../../../../../www/install/tmp/database.json", $configuration);
    }

    public function getAdminConfiguration()
    {
        $configuration = array(
>>>>>>> centreon/dev-21.10.x
            'admin_password' => '',
            'confirm_password' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => ''
<<<<<<< HEAD
        ];

        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/admin.json', $configuration);
    }

    /**
     * Get centreon-engine configuration
     *
     * @return array<string,string>
     */
    public function getEngineConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/engine.json');
    }

    /**
     * Get centreon-broker configuration
     *
     * @return array<string,string>
     */
    public function getBrokerConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/broker.json');
    }

    /**
     * Get centreon version
     *
     * @return array<string,string>
     */
    public function getVersion()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/version.json', '1.0.0');
=======
        );

        return $this->getConfiguration(__DIR__ . "/../../../../../www/install/tmp/admin.json", $configuration);
    }

    public function getEngineConfiguration()
    {
        return $this->getConfiguration(__DIR__ . "/../../../../../www/install/tmp/engine.json");
    }

    public function getBrokerConfiguration()
    {
        return $this->getConfiguration(__DIR__ . "/../../../../../www/install/tmp/broker.json");
    }

    public function getVersion()
    {
        return $this->getConfiguration(__DIR__ . "/../../../../../www/install/tmp/version.json", '1.0.0');
>>>>>>> centreon/dev-21.10.x
    }
}
