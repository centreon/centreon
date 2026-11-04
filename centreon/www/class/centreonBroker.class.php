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
     * Reload centreon broker process, through Gorgone (RELOADBROKER) when the central is containerized
     *
     * @throws PDOException
     * @throws Centreon\Infrastructure\Gorgone\LegacyCommandException
     * @return void
     */
    public function reload(): void
    {
        $containerizedCentralId = $this->getContainerizedCentralId();
        if ($containerizedCentralId !== null) {
            Centreon\Infrastructure\Gorgone\LegacyCommandSender::create()
                ->sendCommand('RELOADBROKER', $containerizedCentralId);

            return;
        }

        $command = $this->getReloadCommand();
        if (! empty($command)) {
            if (preg_match(Core\MonitoringServer\Model\MonitoringServer::VALID_COMMAND_RELOAD_REGEX, $command) !== 1) {
                throw new RuntimeException(_('Broker reload command does not match the expected format. Please check the monitoring server configuration.'));
            }
            shell_exec(escapeshellcmd("sudo -n -- {$command}"));
        }
    }

    /**
     * Get the id of the monitoring server hosting the central broker when it is containerized.
     * The central registered in the platform topology wins over the localhost flag, which several
     * servers may carry. Servers without broker reload command are skipped, as in getReloadCommand().
     *
     * @throws PDOException
     * @return int|null null when the central is not containerized
     */
    private function getContainerizedCentralId(): ?int
    {
        $result = $this->db->query(
            "SELECT ns.id, ns.poller_type
            FROM nagios_server ns
            LEFT JOIN platform_topology pt ON pt.server_id = ns.id AND pt.type = 'central'
            WHERE ns.broker_reload_command IS NOT NULL
            AND ns.broker_reload_command != ''
            ORDER BY pt.id IS NULL, ns.localhost DESC, ns.id ASC
            LIMIT 1"
        );

        if (($row = $result->fetch()) && $row['poller_type'] === 'docker') {
            return (int) $row['id'];
        }

        return null;
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
