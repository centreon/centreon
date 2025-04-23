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

use CentreonDB;
use CentreonDbException;
use CentreonLog;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use Exception;
use PDO;
use Pimple\Container;

/**
 * Class
 *
 * @class MacroHost
 * @package ConfigGenerateRemote\Relations
 */
class MacroHost extends AbstractObject
{
    /** @var CentreonDB */
    private CentreonDB $databaseConnection;
    /** @var string */
    protected $table = 'on_demand_macro_host';
    /** @var string */
    protected $generateFilename = 'on_demand_macro_host.infile';
    /** @var string[] */
    protected $attributesWrite = [
        'host_host_id',
        'host_macro_name',
        'host_macro_value',
        'is_password',
        'description',
    ];

    /**
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        try {
            parent::__construct($dependencyInjector);
            if (!isset($this->backendInstance->db)) {
                throw new Exception('Database connection is not set');
            }
            $this->databaseConnection = $this->backendInstance->db;
        } catch (Exception $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Cannot connect to database: ' . $ex->getMessage(),
                exception: $ex
            );
        }

    }

    /**
     * Get host macro from host id
     *
     * @param int $hostId
     * @return array<array{
     *     "host_macro_id":int,
     *     "host_macro_name":string,
     *     "host_macro_value":string,
     *     "is_password":null|int,
     *     "description":null|string,
     *     "host_host_id":int
     * }>
     */
    public function getHostMacroByHostId(int $hostId): array
    {
        try {
            $statement = $this->databaseConnection->prepareQuery(
                "SELECT host_macro_id, host_macro_name, host_macro_value, is_password, description, host_host_id
                FROM on_demand_macro_host
                WHERE host_host_id = :host_id"
            );
            $this->databaseConnection->executePreparedQuery($statement, ['host_id' => $hostId]);
            $macros = $statement->fetchAll(PDO::FETCH_ASSOC);
            $this->writeMacrosHost($hostId, $macros);
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Host macros generated',
                customContext: [$macros]
            );
            return $macros;
        } catch (CentreonDbException $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: $ex->getMessage(),
                customContext: ['host_id' => $hostId],
                exception: $ex,
            );
        }
    }

    /**
     * Generate host macros
     *
     * @param int $hostId
     * @param array<array{
     *      "host_macro_id":int,
     *      "host_macro_name":string,
     *      "host_macro_value":string,
     *      "is_password":null|int,
     *      "description":null|string,
     *      "host_host_id":int
     *  }> $macros
     *
     * @throws Exception
     */
    private function writeMacrosHost(int $hostId, array $macros): void
    {
        if ($this->checkGenerate($hostId)) {
            return;
        }

        foreach ($macros as $value) {
            $this->generateObjectInFile($value, $hostId);
        }
    }
}
