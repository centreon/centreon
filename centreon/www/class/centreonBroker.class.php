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
     * Reload centreon broker process.
     *
     * By default this runs the historical local command ("sudo -n -- systemctl reload cbd"). When the
     * "gorgone_command_transport" option is set to "gorgone", the reload is routed to the central's
     * Centreon Broker through Gorgone's legacycmd module (RELOADBROKER) instead, so the web tier needs
     * no local cbd. Any failure to resolve that transport falls back to the historical local reload.
     *
     * @throws PDOException
     * @return void
     */
    public function reload(): void
    {
        // Only the transport *resolution* falls back to the historical local reload; once we know the
        // Gorgone transport is enabled, a failure to send must surface (no silent fallback that would
        // shell_exec a cbd the web tier may not even have).
        try {
            $useGorgone = \App\Kernel::createForWeb()->getContainer()
                ->get(\Centreon\Domain\Gorgone\GorgoneTransport::class)
                ->useGorgone();
        } catch (\Throwable $e) {
            $useGorgone = false;
        }

        if ($useGorgone) {
            $centralId = $this->getCentralPollerId();
            if ($centralId === null) {
                throw new RuntimeException(_('Cannot reload Centreon Broker through Gorgone: no central monitoring server (localhost = 1) was found.'));
            }
            \App\Kernel::createForWeb()->getContainer()
                ->get(\Centreon\Domain\MonitoringServer\Interfaces\PollerCommandRepositoryInterface::class)
                ->reloadBroker($centralId);

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
     * Get the id of the central monitoring server (localhost), used as the Gorgone target.
     *
     * @throws PDOException
     * @return int|null
     */
    private function getCentralPollerId(): ?int
    {
        $result = $this->db->query(
            "SELECT id FROM nagios_server WHERE localhost = '1' ORDER BY id LIMIT 1"
        );

        if ($row = $result->fetch()) {
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
