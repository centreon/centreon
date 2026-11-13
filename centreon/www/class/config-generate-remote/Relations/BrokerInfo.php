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

namespace ConfigGenerateRemote\Relations;

use ConfigGenerateRemote\Abstracts\AbstractObject;
use PDO;

/**
 * Exports the broker inputs/outputs (cfg_centreonbroker_input_output) of the exported broker configurations.
 *
 * Central and remote servers always run the same version, so the rows are shipped as stored,
 * the `parameters` column being the raw JSON text.
 *
 * @class BrokerInfo
 * @package ConfigGenerateRemote\Relations
 */
class BrokerInfo extends AbstractObject
{
    /** @var string */
    protected $table = 'cfg_centreonbroker_input_output';

    /** @var string */
    protected $generateFilename = 'cfg_centreonbroker_input_output.infile';

    /** @var \PDOStatement|null */
    protected $stmtBrokerInfo = null;

    /** @var string[] */
    protected $attributesWrite = [
        'config_id',
        'tag',
        'input_output_id',
        'name',
        'type_id',
        'type',
        'parameters',
    ];

    /** @var int */
    private $useCache = 1;

    /** @var int */
    private $doneCache = 0;

    /** @var array<int, list<array<string, mixed>>> */
    private $brokerInfoCache = [];

    /**
     * BrokerInfo constructor
     *
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * Generate broker inputs/outputs of a broker configuration
     *
     * @param int $configId
     * @param array<int, list<array<string, mixed>>> $brokerInfoCache
     *
     * @throws \Exception
     * @return void
     */
    public function generateObject(int $configId, array $brokerInfoCache): void
    {
        foreach ($brokerInfoCache[$configId] as $value) {
            $this->generateObjectInFile($value);
        }
    }

    /**
     * Get the broker inputs/outputs of a broker configuration and write them in the export file
     *
     * @param int $configId
     *
     * @throws \Exception
     * @return array
     */
    public function getBrokerInfoByConfigId(int $configId)
    {
        // Get from the cache
        if (isset($this->brokerInfoCache[$configId])) {
            $this->generateObject($configId, $this->brokerInfoCache);

            return $this->brokerInfoCache[$configId];
        }
        if ($this->useCache === 1) {
            return [];
        }

        // We get unitary
        if (is_null($this->stmtBrokerInfo)) {
            $this->stmtBrokerInfo = $this->backendInstance->db->prepare(
                'SELECT config_id, tag, input_output_id, name, type_id, type, parameters
                FROM cfg_centreonbroker_input_output
                WHERE config_id = :config_id
                ORDER BY tag, input_output_id'
            );
        }

        $this->stmtBrokerInfo->bindParam(':config_id', $configId, PDO::PARAM_INT);
        $this->stmtBrokerInfo->execute();
        $brokerInfoCache = [$configId => $this->stmtBrokerInfo->fetchAll(PDO::FETCH_ASSOC)];

        $this->generateObject($configId, $brokerInfoCache);

        return $brokerInfoCache[$configId];
    }

    /**
     * Write a row in the infile.
     *
     * Unlike the generic writer, NULL and empty strings are kept apart (empty strings are valid names, types and
     * parameter values) and the escape character of the import (backslash) is escaped, so that the JSON text of
     * `parameters` (which contains backslashes as soon as a value holds a quote, a backslash or a control
     * character) is imported unchanged by LOAD DATA.
     *
     * @param array<string, mixed> $object
     * @return void
     */
    protected function writeObject(array $object): void
    {
        $fields = [];
        foreach ($this->attributesWrite as $attribute) {
            $fields[] = isset($object[$attribute])
                ? '"' . strtr((string) $object[$attribute], ['\\' => '\\\\', '"' => '\\"']) . '"'
                : '\N';
        }

        fwrite($this->fp, implode($this->fieldSeparatorInfile, $fields) . $this->lineSeparatorInfile);
    }

    /**
     * Build cache of broker inputs/outputs
     *
     * @return void
     */
    private function cacheBrokerInfo(): void
    {
        $stmt = $this->backendInstance->db->prepare(
            'SELECT config_id, tag, input_output_id, name, type_id, type, parameters
             FROM cfg_centreonbroker_input_output
             ORDER BY config_id, tag, input_output_id'
        );

        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($values as $value) {
            $this->brokerInfoCache[(int) $value['config_id']][] = $value;
        }
    }

    /**
     * Build cache
     *
     * @return void
     */
    private function buildCache(): void
    {
        if ($this->doneCache === 0) {
            $this->cacheBrokerInfo();
            $this->doneCache = 1;
        }
    }
}
