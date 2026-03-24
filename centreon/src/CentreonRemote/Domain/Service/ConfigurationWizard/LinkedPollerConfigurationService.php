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

namespace CentreonRemote\Domain\Service\ConfigurationWizard;

require_once __DIR__ . '/../../../../../www/class/centreonContactgroup.class.php';
require_once __DIR__ . '/../../../../../www/class/config-generate/generate.class.php';
require_once __DIR__ . '/../../../../../www/class/centreonBroker.class.php';
require_once __DIR__ . '/../../../../../www/class/centreonConfigCentreonBroker.php';
require_once __DIR__ . '/../../../../../www/include/configuration/configGenerate/DB-Func.php';

use Centreon\Domain\Entity\Task;
use Centreon\Domain\Repository\Interfaces\CfgCentreonBrokerInterface;
use Centreon\Domain\Service\BrokerConfigurationService;
use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use CentreonRemote\Domain\Resources\RemoteConfig\BrokerInfo\OutputForwardMaster;
use CentreonRemote\Domain\Resources\RemoteConfig\InputFlowOnePeerRetention;
use CentreonRemote\Domain\Service\TaskService;
use CentreonRemote\Domain\Value\PollerServer;
use CentreonRemote\Infrastructure\Service\PollerInteractionService;

class LinkedPollerConfigurationService
{
    /** @var bool */
    protected $onePeerRetention = false;

    /** @var \CentreonDB */
    private $db;

    /** @var CfgCentreonBrokerInterface */
    private $brokerRepository;

    /** @var BrokerConfigurationService */
    private $brokerConfigurationService;

    /** @var TaskService */
    private $taskService;

    /** @var PollerInteractionService */
    private $pollerInteractionService;

    public function __construct(CentreonDBAdapter $dbAdapter)
    {
        $this->db = $dbAdapter->getCentreonDBInstance();
    }

    /**
     * Set broker repository to manage general broker configuration.
     *
     * @param CfgCentreonBrokerInterface $cfgCentreonBroker the centreon broker configuration repository
     */
    public function setBrokerRepository(CfgCentreonBrokerInterface $cfgCentreonBroker): void
    {
        $this->brokerRepository = $cfgCentreonBroker;
    }

    /**
     * Set broker configuration service to broker info configuration.
     *
     * @param BrokerConfigurationService $brokerConfigurationService the service to manage broker confiration
     */
    public function setBrokerConfigurationService(BrokerConfigurationService $brokerConfigurationService): void
    {
        $this->brokerConfigurationService = $brokerConfigurationService;
    }

    /**
     * Set poller interaction service.
     *
     * @param PollerInteractionService $pollerInteractionService the poller interaction service
     */
    public function setPollerInteractionService(PollerInteractionService $pollerInteractionService): void
    {
        $this->pollerInteractionService = $pollerInteractionService;
    }

    /**
     * Set task service to add export task.
     *
     * @param TaskService $taskService the task service
     */
    public function setTaskService(TaskService $taskService): void
    {
        $this->taskService = $taskService;
    }

    /**
     * Set one peer retention mode.
     *
     * @param bool $onePeerRetention if one peer retention mode is enabled
     */
    public function setOnePeerRetention(bool $onePeerRetention): void
    {
        $this->onePeerRetention = $onePeerRetention;
    }

    /**
     * Link a set of pollers to a parent poller by creating broker input/output.
     *
     * @param PollerServer[] $pollers
     * @param PollerServer $remote
     */
    public function linkPollersToParentPoller(array $pollers, PollerServer $remote): void
    {
        $pollerIds = array_map(function ($poller) {
            return $poller->getId();
        }, $pollers);

        // Before linking the pollers to the new remote, we have to tell the old remote they are no longer linked to it
        $this->triggerExportForOldRemotes($pollerIds);

        foreach ($pollers as $poller) {
            // If one peer retention is enabled, add input on remote server to get data from poller
            if ($this->onePeerRetention) {
                $this->setBrokerInputOfRemoteServer($remote->getId(), $poller);
            } else { // If one peer retention is disabled, we need to set the host output of the poller
                $this->setBrokerOutputOfPoller($poller->getId(), $remote);
            }

            $this->setPollerRelationToRemote($poller->getId(), $remote);
        }

        // Generate configuration for pollers and restart them
        $this->pollerInteractionService->generateAndExport($pollerIds);
    }

    /**
     * Link a poller to additional Remote Servers.
     *
     * @param PollerServer $poller
     * @param PollerServer[] $remotes
     */
    public function linkPollerToAdditionalRemoteServers(PollerServer $poller, array $remotes): void
    {
        $pollerIds = array_map(function ($poller) {
            return $poller->getId();
        }, $remotes);

        foreach ($remotes as $remote) {
            // If one peer retention is enabled, add input on remote server to get data from poller
            if ($this->onePeerRetention) {
                $this->setBrokerInputOfRemoteServer($remote->getId(), $poller);
            } else { // If one peer retention is disabled, we need to set the host output of the poller
                $this->setBrokerOutputOfPoller($poller->getId(), $remote, true);
            }
        }
        $this->insertAddtitionnalRemoteServersRelations($poller, $remotes);

        // Generate configuration for poller and restart it
        $this->pollerInteractionService->generateAndExport($pollerIds);
    }

    /**
     * Add broker input configuration on remote server to get data from poller.
     *
     * @param int $remoteId
     * @param PollerServer $poller
     */
    private function setBrokerInputOfRemoteServer($remoteId, PollerServer $poller): void
    {
        // get broker config id of linked remote server (cbd broker)
        $remoteBrokerConfigId = $this->brokerRepository->findBrokerConfigIdByPollerId($remoteId);

        // get template function to generate input flow in remote server broker configuration
        $config = InputFlowOnePeerRetention::getConfiguration($poller->getName(), $poller->getIp());
        $this->brokerConfigurationService->addFlow($remoteBrokerConfigId, $config);
    }

    /**
     * Add relation between poller and Remote Servers.
     *
     * @param PollerServer $poller
     * @param PollerServer[] $remotes
     */
    private function insertAddtitionnalRemoteServersRelations(PollerServer $poller, array $remotes): void
    {
        $query = 'INSERT INTO `rs_poller_relation` VALUES (:remoteId, :pollerId)';
        $this->db->beginTransaction();
        $statement = $this->db->prepare($query);
        try {
            $pollerId = $poller->getId();
            foreach ($remotes as $remote) {
                $remoteId = $remote->getId();
                $statement->bindParam(':remoteId', $remoteId, \PDO::PARAM_INT);
                $statement->bindParam(':pollerId', $pollerId, \PDO::PARAM_INT);
                $statement->execute();
            }
            $this->db->commit();
        } catch (\PDOException $Exception) {
            $this->db->rollBack();
        }
    }

    /**
     * Update host field of broker output on poller to link it the the remote server.
     *
     * @param int $pollerId
     * @param PollerServer $remote
     * @param bool $additional
     */
    private function setBrokerOutputOfPoller($pollerId, PollerServer $remote, $additional = false): void
    {
        $statement = $this->db->prepare('SELECT `config_id`
            FROM `cfg_centreonbroker`
            WHERE `ns_nagios_server` = :id
            AND `daemon` = 0');
        $statement->bindParam(':id', $pollerId, \PDO::PARAM_INT);
        $statement->execute();
        $configId = $statement->fetchColumn();

        $outputName = 'forward-to-' . str_replace(' ', '-', $remote->getName());

        if ($additional) { // insert new broker output relation
            $config = (new OutputForwardMaster())->getConfiguration();
            $config['name'] = $outputName;
            $config['parameters']['host'] = $remote->getIp();
            $parameters = json_encode($config['parameters'], JSON_THROW_ON_ERROR);

            $this->db->beginTransaction();
            try {
                $statement = $this->db->prepare(
                    'INSERT INTO `cfg_broker_input_output`
                        (config_id, tag, type_id, type_name, name, parameters)
                    VALUES
                        (:brokerId, :tag, :typeId, :typeName, :name, :parameters)'
                );
                $statement->bindValue(':brokerId', $configId, \PDO::PARAM_INT);
                $statement->bindValue(':tag', $config['tag'], \PDO::PARAM_STR);
                $statement->bindValue(':typeId', $config['type_id'], \PDO::PARAM_INT);
                $statement->bindValue(':typeName', $config['type_name'], \PDO::PARAM_STR);
                $statement->bindValue(':name', $config['name'], \PDO::PARAM_STR);
                $statement->bindValue(':parameters', $parameters, \PDO::PARAM_STR);
                $statement->execute();
                $this->db->commit();
            } catch (\PDOException $Exception) {
                $this->db->rollBack();
            }
        } else { // update host and name of the poller module output to link it to the remote server
            $statement = $this->db->prepare(
                "UPDATE `cfg_broker_input_output`
                SET `parameters` = JSON_SET(`parameters`, '$.host', :host),
                    `name` = :name
                WHERE `config_id` = :brokerId
                AND `tag` = 'output'
                AND `type_name` = 'ipv4'"
            );
            $statement->bindValue(':host', $remote->getIp(), \PDO::PARAM_STR);
            $statement->bindValue(':name', $outputName, \PDO::PARAM_STR);
            $statement->bindValue(':brokerId', $configId, \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * Link poller with remote server in database.
     *
     * @param int $pollerId
     * @param PollerServer $remote
     */
    private function setPollerRelationToRemote($pollerId, PollerServer $remote): void
    {
        $query = 'UPDATE `nagios_server` '
            . 'SET `remote_id` = :remote_id '
            . 'WHERE `id` = :id';
        $statement = $this->db->prepare($query);
        $statement->bindValue(':remote_id', $remote->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':id', $pollerId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Export to existing remote servers.
     *
     * @param int[] $pollerIDs the poller ids to export
     */
    private function triggerExportForOldRemotes(array $pollerIDs): void
    {
        // Get from the database only the pollers that are linked to a remote
        $idBindString = str_repeat('?,', count($pollerIDs));
        $idBindString = rtrim($idBindString, ',');
        $queryPollers = 'SELECT id, remote_id FROM nagios_server '
            . "WHERE id IN({$idBindString}) AND remote_id IS NOT NULL";
        $remotesStatement = $this->db->prepare($queryPollers);
        $remotesStatement->execute($pollerIDs);
        $pollersWithRemote = $remotesStatement->fetchAll(\PDO::FETCH_ASSOC);
        $alreadyExportedRemotes = [];

        // For each remote get the currently linked pollers, exclude the ones selected and trigger export
        foreach ($pollersWithRemote as $poller) {
            $remoteID = $poller['remote_id'];

            if (in_array($remoteID, $alreadyExportedRemotes)) {
                continue;
            }

            $alreadyExportedRemotes[] = $remoteID;

            // Get all linked pollers of the remote
            $linkedStatement = $this->db->prepare(
                'SELECT id
                FROM nagios_server
                WHERE remote_id = :remote_id'
            );
            $linkedStatement->bindValue(':remote_id', $remoteID, \PDO::PARAM_INT);
            $linkedStatement->execute();
            $linkedResults = $linkedStatement->fetchAll(\PDO::FETCH_ASSOC);
            $linkedPollersOfRemote = array_column($linkedResults, 'id');

            // Get information of remote
            $remoteDataStatement = $this->db->prepare(
                'SELECT ns.ns_ip_address as ip, rs.centreon_path,
                  rs.http_method, rs.http_port, rs.no_check_certificate, rs.no_proxy
                FROM nagios_server as ns
                JOIN remote_servers as rs ON rs.server_id = ns.id
                WHERE ns.id = :server_id'
            );
            $remoteDataStatement->bindValue(':server_id', $remoteID, \PDO::PARAM_INT);
            $remoteDataStatement->execute();
            $remoteDataResults = $remoteDataStatement->fetchAll(\PDO::FETCH_ASSOC);

            // Exclude the selected pollers which are going to another remote
            $pollerIDsToExport = array_diff($linkedPollersOfRemote, $pollerIDs);

            $exportParams = [
                'server' => $remoteID,
                'pollers' => $pollerIDsToExport,
                'remote_ip' => $remoteDataResults[0]['ip'],
                'centreon_path' => $remoteDataResults[0]['centreon_path'],
                'http_method' => $remoteDataResults[0]['http_method'],
                'http_port' => $remoteDataResults[0]['http_port'],
                'no_check_certificate' => $remoteDataResults[0]['no_check_certificate'],
                'no_proxy' => $remoteDataResults[0]['no_proxy'],
            ];
            $this->taskService->addTask(Task::TYPE_EXPORT, ['params' => $exportParams]);
        }
    }
}
