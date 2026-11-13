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

declare(strict_types=1);

namespace Centreon\Infrastructure\Broker;

use Centreon\Domain\Broker\BrokerConfiguration;
use Centreon\Domain\Broker\Interfaces\BrokerRepositoryInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Broker\Infrastructure\Repository\StoredBrokerInputOutput;

class BrokerRepositoryRDB implements BrokerRepositoryInterface
{
    /** @var DatabaseConnection */
    private $db;

    /**
     * BrokerRepositoryRDB constructor.
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Get Broker Configurations based on the configuration Key
     *
     * Every input/output of the poller is expanded into its parameter rows (former cfg_centreonbroker_info
     * encoding), so that the key matches the same values: "name", "type", "blockId", simple parameters,
     * filter values ("category", "event", plus the empty "filters" parent) and grouped fields ("<group>__<field>").
     *
     * @param int $monitoringServerId
     * @param string $configKey
     * @return BrokerConfiguration[]
     */
    public function findByMonitoringServerAndParameterName(int $monitoringServerId, string $configKey): array
    {
        $statement = $this->db->prepare('
            SELECT cfgbio.config_id AS id, cfgbio.tag, cfgbio.input_output_id, cfgbio.name,
                cfgbio.type_id, cfgbio.type, cfgbio.parameters
                FROM cfg_centreonbroker_input_output cfgbio
                INNER JOIN cfg_centreonbroker AS cfgb
                    ON cfgbio.config_id = cfgb.config_id
                INNER JOIN nagios_server AS ns
                    ON cfgb.ns_nagios_server = ns.id
                    AND ns.id = :monitoringServerId
                ORDER BY cfgbio.config_id, cfgbio.tag, cfgbio.input_output_id
        ');
        $statement->bindValue(':monitoringServerId', $monitoringServerId, \PDO::PARAM_INT);
        $statement->execute();

        $brokerConfigurations = [];
        while (($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            foreach (StoredBrokerInputOutput::fromRecord($result)->toLegacyRows() as $row) {
                if ($row['config_key'] !== $configKey) {
                    continue;
                }
                $brokerConfigurations[] = (new BrokerConfiguration())
                    ->setId((int) $result['id'])
                    ->setConfigurationKey($configKey)
                    ->setConfigurationValue($row['config_value']);
            }
        }

        return $brokerConfigurations;
    }
}
