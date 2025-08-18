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

/**
 * Class
 *
 * @class CentreonBroker
 */
class CentreonBroker
{
    /** @var CentreonDB */
    private $db;

    /**
     * CentreonBroker constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Reload centreon broker process
     *
     * @throws PDOException
     * @return void
     */
    public function reload(): void
    {
        if ($command = $this->getReloadCommand()) {
            shell_exec("sudo {$command}");
        }
    }

    /**
     * Get command to reload centreon broker
     *
     * @throws PDOException
     * @return string|null the command
     */
    private function getReloadCommand(): ?string
    {
        $command = null;

        $result = $this->db->query(
            'SELECT broker_reload_command
            FROM nagios_server
            ORDER BY localhost DESC'
        );

        if ($row = $result->fetch()) {
            $command = $row['broker_reload_command'];
        }

        return $command;
    }
}
