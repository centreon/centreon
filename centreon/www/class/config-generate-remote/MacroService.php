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

namespace ConfigGenerateRemote;

use Exception;
use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use PDOStatement;
use Pimple\Container;

/**
 * Class
 *
 * @class MacroService
 * @package ConfigGenerateRemote
 */
class MacroService extends AbstractObject
{
    /** @var int */
    private $useCache = 1;
    /** @var int */
    private $doneCache = 0;
    /** @var array */
    private $macroServiceCache = [];
    /** @var PDOStatement|null */
    protected $stmtService = null;
    /** @var string */
    protected $table = 'on_demand_macro_service';
    /** @var string */
    protected $generateFilename = 'on_demand_macro_service.infile';
    /** @var string[] */
    protected $attributesWrite = [
        'svc_svc_id',
        'svc_macro_name',
        'svc_macro_value',
        'is_password',
        'description',
    ];

    /**
     * MacroService constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * Build cache of service macros
     *
     * @return void
     */
    private function cacheMacroService(): void
    {
        $stmt = $this->backendInstance->db->prepare(
            "SELECT svc_macro_id, svc_svc_id, svc_macro_name, svc_macro_value, is_password, description
            FROM on_demand_macro_service"
        );
        $stmt->execute();
        while (($macro = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (!isset($this->macroServiceCache[$macro['svc_svc_id']])) {
                $this->macroServiceCache[$macro['svc_svc_id']] = [];
            }
            $this->macroServiceCache[$macro['svc_svc_id']][$macro['svc_macro_id']] = [
                'svc_svc_id' => $macro['svc_svc_id'],
                'svc_macro_name' => $macro['svc_macro_name'],
                'svc_macro_value' => $macro['svc_macro_value'],
                'is_password' => $macro['is_password'],
                'description' => $macro['description'],
            ];
        }
    }

    /**
     * Generate service macros
     *
     * @param int $serviceId
     *
     * @return null|void
     * @throws Exception
     */
    private function writeMacrosService(int $serviceId)
    {
        if ($this->checkGenerate($serviceId)) {
            return null;
        }

        foreach ($this->macroServiceCache[$serviceId] as $value) {
            $this->generateObjectInFile($value, $serviceId);
        }
    }

    /**
     * Get service macro from service id
     *
     * @param int $serviceId
     *
     * @return null|array
     * @throws Exception
     */
    public function getServiceMacroByServiceId(int $serviceId)
    {
        // Get from the cache
        if (isset($this->macroServiceCache[$serviceId])) {
            $this->writeMacrosService($serviceId);
            return $this->macroServiceCache[$serviceId];
        }
        if ($this->doneCache == 1) {
            return null;
        }

        // We get unitary
        if (is_null($this->stmtService)) {
            $this->stmtService = $this->backendInstance->db->prepare(
                "SELECT svc_macro_id, svc_macro_name, svc_macro_value, is_password, description
                FROM on_demand_macro_service
                WHERE svc_svc_id = :service_id"
            );
        }

        $this->stmtService->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $this->stmtService->execute();
        $this->macroServiceCache[$serviceId] = [];
        while ($macro = $this->stmtService->fetch(PDO::FETCH_ASSOC)) {
            $this->macroServiceCache[$serviceId][$macro['svc_macro_id']] = [
                'svc_svc_id' => $macro['svc_svc_id'],
                'svc_macro_name' => $macro['svc_macro_name'],
                'svc_macro_value' => $macro['svc_macro_value'],
                'is_password' => $macro['is_password'],
                'description' => $macro['description'],
            ];
        }

        $this->writeMacrosService($serviceId);

        return $this->macroServiceCache[$serviceId];
    }

    /**
     * Build cache
     *
     * @return void
     */
    private function buildCache()
    {
        if ($this->doneCache == 1) {
            return 0;
        }

        $this->cacheMacroService();
        $this->doneCache = 1;
    }
}
