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

namespace Centreon\Domain\Service;

/**
 * Service to manage broker flows configuration
 */
class BrokerConfigurationService
{
    /** @var \CentreonDB */
    private $db;

    /**
     * Set database connection.
     *
     * @param \CentreonDB $db
     */
    public function setDb(\CentreonDB $db): void
    {
        $this->db = $db;
    }

    /**
     * Add flow (input, output, log...) to cfg_broker_input_output.
     *
     * @param int $configId the broker config id to update
     * @param array<string,mixed> $config the flow configuration with keys:
     *                                    tag, type_id, type_name, name, parameters (array)
     */
    public function addFlow(int $configId, array $config): void
    {
        $parameters = json_encode($config['parameters'], JSON_THROW_ON_ERROR);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO `cfg_broker_input_output`
                    (config_id, tag, type_id, type_name, name, parameters)
                VALUES
                    (:config_id, :tag, :type_id, :type_name, :name, :parameters)'
            );
            $statement->bindValue(':config_id', $configId, \PDO::PARAM_INT);
            $statement->bindValue(':tag', $config['tag'], \PDO::PARAM_STR);
            $statement->bindValue(':type_id', $config['type_id'], \PDO::PARAM_INT);
            $statement->bindValue(':type_name', $config['type_name'], \PDO::PARAM_STR);
            $statement->bindValue(':name', $config['name'], \PDO::PARAM_STR);
            $statement->bindValue(':parameters', $parameters, \PDO::PARAM_STR);
            $statement->execute();
            $this->db->commit();
        } catch (\PDOException $e) {
            $this->db->rollBack();

            throw $e;
        }
    }
}
