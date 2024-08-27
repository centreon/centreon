<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
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

namespace ConfigGenerateRemote\Relations;

use ConfigGenerateRemote\Abstracts\AbstractObject;

class MacroHost extends AbstractObject
{
    private array $macroHostCache = [];

    private bool $hasCache = false;

    private \CentreonDB $databaseConnection;

    /**
     * Constructor
     *
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        if (!isset($this->backendInstance->db)) {
            throw new \Exception('Database connection is not set');
        }
        $this->databaseConnection = $this->backendInstance->db;
        $this->buildCache();
    }

    protected $table = 'on_demand_macro_host';
    protected $generateFilename = 'on_demand_macro_host.infile';
    protected $attributesWrite = [
        'host_host_id',
        'host_macro_name',
        'host_macro_value',
        'is_password',
        'description',
    ];

    /**
     * Get host macro from host id
     *
     * @param integer $host
     * @return null|array
     */
    public function getHostMacroByHostId(int $hostId)
    {
        // Get from the cache
        if (isset($this->macroHostCache[$hostId])) {
            $this->writeMacrosHost($hostId);

            return $this->macroHostCache[$hostId];
        }
        if ($this->hasCache === true) {
            return null;
        }

        try {
            $statement = $this->databaseConnection->prepareQuery(
                <<<SQL
                SELECT host_macro_id, host_host_id, host_macro_name, host_macro_value, is_password, description
                FROM on_demand_macro_host
                WHERE host_host_id = :hostId
                SQL
            );

            $this->databaseConnection->executePreparedQuery($statement, ['hostId' => $hostId]);
            $this->macroHostCache[$hostId] = [];
            while ($macro = $statement->fetch(PDO::FETCH_ASSOC)) {
                $this->macroServiceCache[$macro[$hostId]][$macro['host_macro_id']] = [
                    'host_host_id' => $macro['host_host_id'],
                    'host_macro_name' => $macro['host_macro_name'],
                    'host_macro_value' => $macro['host_macro_value'],
                    'is_password' => $macro['is_password'] ?? 0,
                    'description' => $macro['description'],
                ];
            }

            $this->writeMacrosHost($hostId);

            return $this->macroHostCache[$hostId];
        } catch(\CentreonDbException) {
            return null;
        }
    }

    /**
     * Build cache
     *
     * @return void
     */
    private function buildCache()
    {
        if ($this->hasCache === true) {
            return;
        }

        $this->cacheMacroHost();
        $this->hasCache = true;
    }

    /**
     * Build cache of service macros
     *
     * @return void
     */
    private function cacheMacroHost()
    {
        try {
            $statement = $this->databaseConnection->executeQuery(
                <<<SQL
                SELECT host_macro_id, host_host_id, host_macro_name, host_macro_value, is_password, description
                FROM on_demand_macro_host
                SQL
            );

            while (($macro = $statement->fetch(PDO::FETCH_ASSOC))) {
                if (! isset($this->macroServiceCache[$macro['host_host_id']])) {
                    $this->macroServiceCache[$macro['host_host_id']] = [];
                }
                $this->macroServiceCache[$macro['host_host_id']][$macro['host_macro_id']] = [
                    'host_host_id' => $macro['host_host_id'],
                    'host_macro_name' => $macro['host_macro_name'],
                    'host_macro_value' => $macro['host_macro_value'],
                    'is_password' => $macro['is_password'] ?? 0,
                    'description' => $macro['description'],
                ];
            }
        } catch(\CentreonDbException) {

        }
    }

    /**
     * Generate host macros
     *
     * @param int $hostId
     * @return void
     */
    private function writeMacrosHost(int $hostId): void
    {
        if ($this->checkGenerate($hostId)) {
            return;
        }

        foreach ($this->macroHostCache[$hostId] as $value) {
            $this->generateObjectInFile($value, $hostId);
        }
    }
}
