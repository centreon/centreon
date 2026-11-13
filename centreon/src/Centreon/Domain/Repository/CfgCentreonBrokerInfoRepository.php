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

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\CfgCentreonBrokerInfo;
use Centreon\Domain\Repository\Interfaces\CfgCentreonBrokerInfoInterface;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Core\Broker\Infrastructure\Repository\BrokerInfoRowsConverter;
use Core\Broker\Infrastructure\Repository\StoredBrokerInputOutput;

/**
 * Repository to manage centreon broker flows configuration (input, output...)
 * stored in cfg_centreonbroker_input_output (one record per input/output).
 */
class CfgCentreonBrokerInfoRepository extends ServiceEntityRepository implements CfgCentreonBrokerInfoInterface
{
    /**
     * Get new config group id by config id for a specific flow
     * once the config group is got from this method, it is possible to insert a new flow in the broker configuration
     *
     * @param int $configId the broker configuration id
     * @param string $flow the flow type : input, output, log...
     * @return int the new config group id
     */
    public function getNewConfigGroupId(int $configId, string $flow): int
    {
        // if there is no config group for a specific flow, first one id is 0
        $configGroupId = 0;

        $sql = 'SELECT MAX(input_output_id) as max_config_group_id '
            . 'FROM cfg_centreonbroker_input_output cbio '
            . 'WHERE config_id = :config_id '
            . 'AND tag = :config_group';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':config_id', $configId, \PDO::PARAM_INT);
        $stmt->bindParam(':config_group', $flow, \PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row) && ! is_null($row['max_config_group_id'])) {
            // to get the new new config group id, we need to increment the max exsting one
            $configGroupId = (int) $row['max_config_group_id'] + 1;
        }

        return $configGroupId;
    }

    /**
     * Insert a broker configuration parameter in database (table cfg_centreonbroker_input_output).
     * The parameter is added to the input/output identified by its config id, config group and config group id,
     * which is created when it does not exist yet. A parameter already stored with the same key is replaced.
     *
     * @param CfgCentreonBrokerInfo $brokerInfoEntity the broker info entity
     */
    public function add(CfgCentreonBrokerInfo $brokerInfoEntity): void
    {
        $configId = $brokerInfoEntity->getConfigId();
        $tag = $brokerInfoEntity->getConfigGroup();
        $inputOutputId = $brokerInfoEntity->getConfigGroupId();

        $stmt = $this->db->prepare(
            <<<'SQL'
                SELECT tag, input_output_id, name, type_id, type, parameters
                FROM cfg_centreonbroker_input_output
                WHERE config_id = :config_id
                    AND tag = :tag
                    AND input_output_id = :input_output_id
                SQL
        );
        $stmt->bindValue(':config_id', $configId, \PDO::PARAM_INT);
        $stmt->bindValue(':tag', $tag, \PDO::PARAM_STR);
        $stmt->bindValue(':input_output_id', $inputOutputId, \PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        $rows = is_array($record) ? StoredBrokerInputOutput::fromRecord($record)->toLegacyRows() : [];
        $rows[] = [
            'config_group' => $tag,
            'config_group_id' => $inputOutputId,
            'config_key' => $brokerInfoEntity->getConfigKey(),
            'config_value' => $brokerInfoEntity->getConfigValue(),
        ];

        $stmt = $this->db->prepare(
            <<<'SQL'
                REPLACE INTO cfg_centreonbroker_input_output
                    (config_id, tag, input_output_id, name, type_id, type, parameters)
                VALUES (:config_id, :tag, :input_output_id, :name, :type_id, :type, :parameters)
                SQL
        );
        foreach (BrokerInfoRowsConverter::convert($rows) as $inputOutput) {
            $stmt->bindValue(':config_id', $configId, \PDO::PARAM_INT);
            $stmt->bindValue(':tag', $inputOutput->tag, \PDO::PARAM_STR);
            $stmt->bindValue(':input_output_id', $inputOutput->id, \PDO::PARAM_INT);
            $stmt->bindValue(':name', $inputOutput->name, \PDO::PARAM_STR);
            $stmt->bindValue(
                ':type_id',
                $inputOutput->typeId,
                $inputOutput->typeId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT
            );
            $stmt->bindValue(':type', $inputOutput->type, $inputOutput->type === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':parameters', $inputOutput->encodeParameters(), \PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * Export the broker inputs/outputs of the given pollers, as rows of the former cfg_centreonbroker_info table.
     *
     * @param int[] $pollerIds
     * @return array
     */
    public function export(array $pollerIds): array
    {
        // prevent SQL exception
        if (! $pollerIds) {
            return [];
        }

        $ids = join(',', array_map('intval', $pollerIds));

        $sql = <<<SQL
            SELECT t.config_id, t.tag, t.input_output_id, t.name, t.type_id, t.type, t.parameters
            FROM cfg_centreonbroker_input_output AS t
            INNER JOIN cfg_centreonbroker AS cci ON cci.config_id = t.config_id
            WHERE cci.ns_nagios_server IN ({$ids})
            ORDER BY t.config_id, t.tag, t.input_output_id
            SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($record = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            foreach (StoredBrokerInputOutput::fromRecord($record)->toLegacyRows() as $row) {
                $result[] = ['config_id' => (int) $record['config_id'], ...$row];
            }
        }

        return $result;
    }
}
