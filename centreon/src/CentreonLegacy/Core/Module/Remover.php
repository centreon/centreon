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

namespace CentreonLegacy\Core\Module;

use App\Upgrade\Infrastructure\Doctrine\MigrationSetConfiguration;
use CentreonLegacy\ServiceProvider;

class Remover extends Module
{
    /**
     * @return bool
     */
    public function remove()
    {
        $this->removePhpFiles(true);
        $this->removeSqlFiles();
        $this->forgetUpgradeHistory();
        $this->removePhpFiles(false);
        $this->removeModuleConfiguration();

        return true;
    }

    /**
     * Remove module information except version.
     *
     * @throws \Exception
     *
     * @return mixed
     */
    private function removeModuleConfiguration()
    {
        $configurationFile = $this->getModulePath($this->moduleName) . '/conf.php';
        if (! $this->services->get('filesystem')->exists($configurationFile)) {
            throw new \Exception('Module configuration file not found.');
        }

        $query = 'DELETE FROM modules_informations WHERE id = :id ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':id', $this->moduleId, \PDO::PARAM_INT);

        $sth->execute();

        return true;
    }

    /**
     * So that a reinstallation starts over.
     */
    private function forgetUpgradeHistory(): void
    {
        $this->services->get(ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS)->clear($this->moduleName);

        $table = MigrationSetConfiguration::moduleTable($this->moduleName);
        if ($table === null) {
            return;
        }

        $this->services->get('configuration_db')->query('DROP TABLE IF EXISTS `' . $table . '`');
    }

    /**
     * @return bool
     */
    private function removeSqlFiles()
    {
        $removed = false;

        $sqlFile = $this->getModulePath($this->moduleName) . '/sql/uninstall.sql';
        if ($this->services->get('filesystem')->exists($sqlFile)) {
            $this->utils->executeSqlFile($sqlFile);
            $removed = true;
        }

        return $removed;
    }

    /**
     * Indicates whether or not it is a pre-uninstall.
     *
     * @param bool $isPreUninstall
     *
     * @return bool
     */
    private function removePhpFiles(bool $isPreUninstall)
    {
        $removed = false;

        $phpFile = $this->getModulePath($this->moduleName)
        . '/php/uninstall' . ($isPreUninstall ? '.pre' : '') . '.php';
        if ($this->services->get('filesystem')->exists($phpFile)) {
            $this->utils->executePhpFile($phpFile);
            $removed = true;
        }

        return $removed;
    }
}
