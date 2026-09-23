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

use App\Kernel;
use Centreon;
use Centreon\Infrastructure\Service\VmwareConfigurationService;
use Centreon\ServiceProvider;
use CentreonBroker;
use CentreonContactgroup;
use CentreonDB;
use Core\MonitoringServer\Model\MonitoringServer;
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

        $vmwareConfigurationService = Kernel::createForWeb()
            ->getContainer()
            ->get(VmwareConfigurationService::class);

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

        foreach ($tabServer as $host) {
            if (in_array($host['id'], $pollerIDs)) {
                $written = file_put_contents(
                    $centCorePipe,
                    'SENDCFGFILE:' . (int) $host['id'] . "\n",
                    FILE_APPEND | LOCK_EX
                );

                if ($written === false) {
                    throw new Exception(_('Could not write into centcore.cmd. Please check file permissions.'));
                }

                $vmwareConfigurationService->restartIfConfigurationChanged(
                    (int) $host['id'],
                    isset($host['localhost']) && $host['localhost'] == 1
                );
            }
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

        $centCorePipe = defined('_CENTREON_VARLIB_')
            ? _CENTREON_VARLIB_ . '/centcore.cmd'
            : '/var/lib/centreon/centcore.cmd';

        $tabs = $this->centreon->user->access->getPollerAclConf([
            'fields' => ['name', 'id', 'localhost', 'engine_restart_command'],
            'order' => ['name'],
            'conditions' => ['ns_activate' => '1'],
            'keys' => ['id'],
        ]);

        $broker = new CentreonBroker($this->db);
        $broker->reload();

        foreach ($tabs as $tab) {
            if (in_array($tab['id'], $pollerIDs)) {
                $tabServers[$tab['id']] = [
                    'id' => $tab['id'],
                    'name' => $tab['name'],
                    'localhost' => $tab['localhost'],
                    'engine_restart_command' => $tab['engine_restart_command'],
                ];
            }
        }

        foreach ($tabServers as $poller) {
            if (isset($poller['localhost']) && (int) $poller['localhost'] === 1) {
                $this->restartEngine($poller['engine_restart_command']);
            } else {
                $written = file_put_contents(
                    $centCorePipe,
                    'RESTART:' . (int) $poller['id'] . "\n",
                    FILE_APPEND | LOCK_EX
                );

                if ($written === false) {
                    throw new Exception(_('Could not write into centcore.cmd. Please check file permissions.'));
                }
            }

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

    /**
     * @param string|null $engineRestartCommand
     *
     * @throws Exception
     * @return void
     */
    private function restartEngine(?string $engineRestartCommand): void
    {
        if (! empty($engineRestartCommand)) {
            if (preg_match(MonitoringServer::VALID_COMMAND_RESTART_REGEX, $engineRestartCommand) !== 1) {
                throw new Exception(_(
                    'Engine restart command does not match the expected format.'
                    . ' Please check the monitoring server configuration.'
                ));
            }
            shell_exec(escapeshellcmd('sudo -n -- ' . $engineRestartCommand));
        }
    }
}
