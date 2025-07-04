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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonHost.class.php';

/**
 * Class
 *
 * @class CentreonResultsAcceptor
 */
class CentreonResultsAcceptor extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /** @var string */
    protected $centcore_file;

    /** @var */
    protected $pollers;

    /** @var int */
    protected $pipeOpened;

    /** @var resource */
    protected $fh;

    /** @var CentreonDB */
    protected $pearDBC;

    /** @var array */
    protected $pollerHosts;

    /** @var array */
    protected $hostServices;

    /**
     * CentreonResultsAcceptor constructor
     */
    public function __construct()
    {
        parent::__construct();
        if (is_dir(_CENTREON_VARLIB_ . '/centcore')) {
            $this->centcore_file = _CENTREON_VARLIB_ . '/centcore/' . microtime(true) . '-externalcommand.cmd';
        } else {
            $this->centcore_file = _CENTREON_VARLIB_ . '/centcore.cmd';
        }
        $this->pearDBC = new CentreonDB('centstorage');
        $this->getPollers();
        $this->pipeOpened = 0;
    }

    /**
     * Get poller Listing
     *
     * @throws PDOException
     * @return void
     */
    private function getPollers(): void
    {
        if (! isset($this->hostServices)) {
            $query = 'SELECT h.host_id, h.host_name, ns.nagios_server_id AS poller_id '
                . 'FROM host h, ns_host_relation ns '
                . 'WHERE host_host_id = host_id '
                . 'AND h.host_activate = "1" '
                . 'AND h.host_register = "1"';
            $dbResult = $this->pearDB->query($query);
            $this->pollerHosts = ['name' => [], 'id' => []];
            while ($row = $dbResult->fetch()) {
                $this->pollerHosts['id'][$row['host_id']] = $row['poller_id'];
                $this->pollerHosts['name'][$row['host_name']] = $row['poller_id'];
            }
            $dbResult->closeCursor();
        }
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function getHostServiceInfo(): void
    {
        if (! isset($this->hostServices)) {
            $query = 'SELECT host_name, service_description '
                . 'FROM host h, service s, host_service_relation hs '
                . 'WHERE h.host_id = hs.host_host_id '
                . 'AND s.service_id = hs.service_service_id '
                . 'AND s.service_activate = "1" '
                . 'AND s.service_activate = "1" '
                . 'AND h.host_activate = "1" '
                . 'AND h.host_register = "1" ';
            $dbResult = $this->pearDB->query($query);
            $this->hostServices = [];
            while ($row = $dbResult->fetch()) {
                if (! isset($this->hostServices[$row['host_name']])) {
                    $this->hostServices[$row['host_name']] = [];
                }
                $this->hostServices[$row['host_name']][$row['service_description']] = 1;
            }
            $dbResult->closeCursor();
        }
    }

    /**
     * @throws RestBadRequestException
     * @return void
     */
    private function openPipe(): void
    {
        if ($this->fh = @fopen($this->centcore_file, 'a+')) {
            $this->pipeOpened = 1;
        } else {
            throw new RestBadRequestException("Can't open centcore pipe");
        }
    }

    /**
     * @return void
     */
    private function closePipe(): void
    {
        fclose($this->fh);
        $this->pipeOpened = 0;
    }

    /**
     * @param $string
     *
     * @throws RestBadRequestException
     * @return void
     */
    private function writeInPipe($string): void
    {
        if ($this->pipeOpened == 0) {
            throw new RestBadRequestException("Can't write results because pipe is closed");
        }

        if ($string != '') {
            fwrite($this->fh, $string . "\n");
        }
    }

    /**
     * @param $data
     *
     * @throws RestBadRequestException
     * @return void
     */
    private function sendResults($data): void
    {
        if (! isset($this->pollerHosts['name'][$data['host']])) {
            throw new RestBadRequestException("Can't find poller_id for host: " . $data['host']);
        }
        if (isset($data['service']) && $data['service'] == '') {
            // Services update
            $command = $data['host'] . ';' . $data['service'] . ';' . $data['status'] . ';'
                . $data['output'] . '|' . $data['perfdata'];
            $this->writeInPipe('EXTERNALCMD:' . $this->pollerHosts['name'][$data['host']]
                . ':[' . $data['updatetime'] . '] PROCESS_HOST_CHECK_RESULT;' . $command);
        } else {
            // Host Update
            $command = $data['host'] . ';' . $data['status'] . ';' . $data['output'] . '|' . $data['perfdata'];
            $this->writeInPipe('EXTERNALCMD:' . $this->pollerHosts['name'][$data['host']]
                . ':[' . $data['updatetime'] . '] PROCESS_SERVICE_CHECK_RESULT;' . $command);
        }
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @throws RestException
     * @return array
     */
    public function postSubmit()
    {
        // print_r($this->arguments);

        $this->getHostServiceInfo();

        if (isset($this->arguments['results']) && is_array($this->arguments['results'])) {
            if ($this->arguments['results'] !== []) {
                if ($this->pipeOpened == 0) {
                    $this->openPipe();
                }
                foreach ($this->arguments['results'] as $data) {
                    if (! isset($this->hostServices[$data['host']])
                        || ! isset($this->hostServices[$data['host']][$data['service']])
                    ) {
                        if (! isset($this->pollerHosts['name'][$data['host']])) {
                            $host = new CentreonHost($this->pearDB);
                            $ret = ['host_name' => $data['host'], 'host_alias' => 'Passif host - ' . $data['host'], 'host_address' => $data['host'], 'host_active_checks_enabled' => ['host_active_checks_enabled', 0], 'host_passive_checks_enabled' => ['host_passive_checks_enabled' => 1], 'host_retry_check_interval' => 1, 'host_max_check_attempts' => 3, 'host_register' => 1, 'host_activate' => ['host_activate' => 1], 'host_comment' => 'Host imported by rest API at ' . date('Y/m/d') . ''];
                            $host_id = $host->insert($ret);
                            $host->insertExtendedInfos(['host_id' => $host_id]);
                            $host->setPollerInstance($host_id, 1);

                            // update reference table
                            $this->hostServices[$data['host']] = [];
                        }
                        if (! isset($this->hostServices[$data['host']][$data['service']])) {
                            if (! isset($host)) {
                                $host = new CentreonHost($this->pearDB);
                            }
                            $service = new CentreonService($this->pearDB);
                            $ret = ['service_description' => $data['service'], 'service_max_check_attempts' => 3, 'service_template_model_stm_id' => 1, 'service_normal_check_interval' => $data['interval'], 'service_retry_check_interval' => $data['interval'], 'service_active_checks_enabled' => ['service_active_checks_enabled' => 0], 'service_passive_checks_enabled' => ['service_passive_checks_enabled' => 1], 'service_register' => 1, 'service_activate' => ['service_activate' => 1], 'service_comment' => 'Service imported by Rest API at ' . date('Y/m/d') . ''];
                            $service_id = $service->insert($ret);
                            if (! isset($host_id)) {
                                $host_id = $host->getHostId($data['host']);
                            }
                            $service->insertExtendInfo(['service_service_id' => $service_id]);
                            $host->insertRelHostService($host_id, $service_id);
                        }
                    }
                    if (isset($this->pollerHosts['name'][$data['host']])) {
                        $this->sendResults($data);
                    } else {
                        throw new RestException(
                            "Can't find the pushed resource (" . $data['host'] . ' / ' . $data['service']
                            . ')... Try again later'
                        );
                    }
                }
                $this->closePipe();
            }

            return ['success' => true];
        }

        throw new RestBadRequestException('Bad arguments - Cannot find command list');
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return (bool) (
            parent::authorize($action, $user, $isInternal)
            || ($user && $user->hasAccessRestApiConfiguration())
        );
    }
}
