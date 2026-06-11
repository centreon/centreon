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

namespace CentreonRemote\Infrastructure\Service;

use Centreon;
use Centreon\Domain\MonitoringServer\Interfaces\PollerCommandRepositoryInterface;
use Centreon\ServiceProvider;
use CentreonBroker;
use CentreonContactgroup;
use CentreonDB;
use Exception;
use Generate;
use Pimple\Container;

/**
 * Class
 *
 * @class PollerInteractionService
 * @package CentreonRemote\Infrastructure\Service
 */
class PollerInteractionService
{
    /** @var Container */
    private $di;

    /** @var CentreonDB */
    private $db;

    /** @var Centreon */
    private $centreon;

    /**
     * PollerInteractionService constructor
     *
     * @param Container $di
     */
    public function __construct(Container $di)
    {
        global $centreon;

        $this->di = $di;
        $this->db = $di[ServiceProvider::CENTREON_DB_MANAGER]
            ->getAdapter('configuration_db')
            ->getCentreonDBInstance();

        $this->centreon = $centreon;
    }

    /**
     * Fetches the poller command repository from the Symfony container so this Pimple-built legacy
     * service can route deploy/reload/restart through the same transport (centcore pipe by default,
     * Gorgone REST when the "gorgone_command_transport" option is enabled) as the modern stack.
     *
     * @return PollerCommandRepositoryInterface
     */
    private function pollerCommandRepository(): PollerCommandRepositoryInterface
    {
        return Kernel::createForWeb()
            ->getContainer()
            ->get(PollerCommandRepositoryInterface::class);
    }

    /**
     * @param int[] $pollers
     *
     * @throws Exception
     */
    public function generateAndExport($pollers): void
    {
        $pollers = (array) $pollers;

        $this->generateConfiguration($pollers);
        $this->moveConfigurationFiles($pollers);
        $this->restartPoller($pollers);
    }

    /**
     * @param int[] $pollerIDs
     *
     * @throws Exception
     */
    private function generateConfiguration(array $pollerIDs): void
    {
        $username = 'unknown';

        if (isset($this->centreon->user->name)) {
            $username = $this->centreon->user->name;
        }

        try {
            // Sync contact groups with ldap
            $contactGroupObject = new CentreonContactgroup($this->db);
            $contactGroupObject->syncWithLdap();

            // Generate configuration
            $configGenerateObject = new Generate($this->di);

            foreach ($pollerIDs as $pollerID) {
                $configGenerateObject->reset();
                $configGenerateObject->configPollerFromId($pollerID, $username);
            }
        } catch (Exception $e) {
            throw new Exception('There was an error generating the configuration for a poller.');
        }
    }

    /**
     * @param int[] $pollerIDs
     *
     * @throws Exception
     */
    private function moveConfigurationFiles(array $pollerIDs): void
    {
        $centreonBrokerPath = _CENTREON_CACHEDIR_ . '/config/broker/';

        $centCorePipe = defined('_CENTREON_VARLIB_')
            ? _CENTREON_VARLIB_ . '/centcore.cmd'
            : '/var/lib/centreon/centcore.cmd';

        $tabServer = [];
        $tabs = $this->centreon->user->access->getPollerAclConf([
            'fields' => ['name', 'id', 'localhost'],
            'order' => ['name'],
            'conditions' => ['ns_activate' => '1'],
            'keys' => ['id'],
        ]);

        foreach ($tabs as $tab) {
            if (in_array($tab['id'], $pollerIDs)) {
                $tabServer[$tab['id']] = [
                    'id' => $tab['id'],
                    'name' => $tab['name'],
                    'localhost' => $tab['localhost'],
                ];
            }
        }

        // Push the generated configuration files to each poller through the poller command repository
        // (local centcore pipe by default, Gorgone legacycmd REST when the "gorgone_command_transport"
        // option is enabled) instead of writing the local centcore pipe directly.
        $pollerCommandRepository = $this->pollerCommandRepository();
        foreach ($tabServer as $host) {
            $pollerCommandRepository->sendConfigFiles((int) $host['id']);
        }
    }

    /**
     * @param int[] $pollerIDs
     *
     * @throws Exception
     */
    private function restartPoller(array $pollerIDs): void
    {
        $tabServers = [];

        $tabs = $this->centreon->user->access->getPollerAclConf([
            'fields' => ['name', 'id', 'localhost'],
            'order' => ['name'],
            'conditions' => ['ns_activate' => '1'],
            'keys' => ['id'],
        ]);

        $pollerCommandRepository = $this->pollerCommandRepository();

        // Reload Centreon Broker. CentreonBroker::reload() keeps the historical local reload by
        // default and only routes through Gorgone (RELOADBROKER) when the toggle is enabled.
        (new CentreonBroker($this->db))->reload();

        foreach ($tabs as $tab) {
            if (in_array($tab['id'], $pollerIDs)) {
                $tabServers[$tab['id']] = [
                    'id' => $tab['id'],
                    'name' => $tab['name'],
                    'localhost' => $tab['localhost'],
                ];
            }
        }

        foreach ($tabServers as $poller) {
            // Restart the poller's engine through the repository (centcore pipe by default, Gorgone
            // REST when enabled) instead of a local "sudo systemctl"/centcore write, so the web tier
            // needs neither a local engine nor the centcore pipe.
            $pollerCommandRepository->restartEngine((int) $poller['id']);

            $restartTimeQuery = "UPDATE `nagios_server`
                SET `last_restart` = '" . time() . "'
                WHERE `id` = '{$poller['id']}'";
            $this->db->query($restartTimeQuery);
        }

        // Find restart actions in modules
        foreach ($this->centreon->modules as $key => $value) {
            $moduleFiles = glob(_CENTREON_PATH_ . 'www/modules/' . $key . '/restart_pollers/*.php');

            if ($value['restart'] && $moduleFiles) {
                foreach ($moduleFiles as $fileName) {
                    include $fileName;
                }
            }
        }
    }
}
