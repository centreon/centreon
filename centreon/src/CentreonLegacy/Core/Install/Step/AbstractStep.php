<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonLegacy\Core\Install\Step;

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
     * Get base configuration (paths).
     *
     * @return array<string,string>
     */
    public function getBaseConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/configuration.json');
    }

    /**
     * Get database access configuration.
     *
     * @return array<string,string>
     */
    public function getDatabaseConfiguration()
    {
        $configuration = [
            'address' => '',
            'port' => '',
            'root_user' => 'root',
            'root_password' => '',
            'db_configuration' => 'centreon',
            'db_storage' => 'centreon_storage',
            'db_user' => 'centreon',
            'db_password' => '',
            'db_password_confirm' => '',
        ];

        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/database.json', $configuration);
    }

    /**
     * Get admin user configuration.
     *
     * @return array<string,string>
     */
    public function getAdminConfiguration()
    {
        $configuration = [
            'admin_password' => '',
            'confirm_password' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
        ];

        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/admin.json', $configuration);
    }

    public function getVaultConfiguration()
    {
        $configuration = [
            'address' => '',
            'port' => '443',
            'root_path' => '',
            'role_id' => '',
            'secret_id' => '',
        ];

        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/vault.json', $configuration);
    }

    /**
     * Get centreon-engine configuration.
     *
     * @return array<string,string>
     */
    public function getEngineConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/engine.json');
    }

    /**
     * Get centreon-broker configuration.
     *
     * @return array<string,string>
     */
    public function getBrokerConfiguration()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/broker.json');
    }

    /**
     * Get centreon version.
     *
     * @return array<string,string>
     */
    public function getVersion()
    {
        return $this->getConfiguration(self::TMP_INSTALL_DIR . '/version.json', '1.0.0');
    }

    /**
     * Get configuration from json file.
     *
     * @param string $file
     * @param array|string $configuration
     *
     * @return array<int|string,string>|string
     */
    private function getConfiguration($file, $configuration = [])
    {
        if ($this->dependencyInjector['filesystem']->exists($file)) {
            $configuration = json_decode(file_get_contents($file), true);
            if (is_array($configuration)) {
                foreach ($configuration as $key => $configurationValue) {
                    $configuration[$key] = htmlspecialchars((string) $configurationValue, ENT_QUOTES);
                }
            } else {
                $configuration = htmlspecialchars((string) $configuration, ENT_QUOTES);
            }
        }

        return $configuration;
    }
}
