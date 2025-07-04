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

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/centreonDB.class.php';

use Psr\Log\LoggerInterface;

/**
 * Class
 *
 * @class CentreonStatsModules
 */
class CentreonStatsModules
{
    /** @var CentreonDB */
    private $db;

    /** @var LoggerInterface */
    private $logger;

    /**
     * CentreonStatsModules constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->db = new centreonDB();
        $this->logger = $logger;
    }

    /**
     * Get list of installed modules
     *
     * @throws PDOException
     * @return array Return the names of installed modules [['name' => string], ...]
     */
    private function getInstalledModules()
    {
        $installedModules = [];
        $stmt = $this->db->prepare('SELECT name FROM modules_informations');
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $installedModules[] = $value['name'];
        }

        return $installedModules;
    }

    /**
     * Get statistics module objects
     *
     * @param array $installedModules Names of installed modules for which you want
     *                                to retrieve statistics module [['name' => string], ...]
     *
     * @return array Return a list of statistics module found
     * @see    CentreonStatsModules::getInstalledModules()
     */
    private function getModuleObjects(array $installedModules)
    {
        $moduleObjects = [];

        foreach ($installedModules as $module) {
            if ($files = glob(_CENTREON_PATH_ . 'www/modules/' . $module . '/statistics/*.class.php')) {
                foreach ($files as $fullFile) {
                    try {
                        include_once $fullFile;
                        $fileName = str_replace('.class.php', '', basename($fullFile));
                        if (class_exists(ucfirst($fileName))) {
                            $moduleObjects[] = ucfirst($fileName);
                        }
                    } catch (Throwable $e) {
                        $this->logger->error('Cannot get stats of module ' . $module);
                    }
                }
            }
        }

        return $moduleObjects;
    }

    /**
     * Get statistics from module
     *
     * @throws PDOException
     * @return array The statistics of each module
     */
    public function getModulesStatistics()
    {
        $data = [];
        $moduleObjects = $this->getModuleObjects(
            $this->getInstalledModules()
        );
        if (is_array($moduleObjects)) {
            foreach ($moduleObjects as $moduleObject) {
                try {
                    $oModuleObject = new $moduleObject();
                    $data[] = $oModuleObject->getStats();
                } catch (Throwable $e) {
                    $this->logger->error($e->getMessage(), ['context' => $e]);
                }
            }
        }

        return $data;
    }
}
