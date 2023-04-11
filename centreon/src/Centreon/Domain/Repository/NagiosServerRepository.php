<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

use Centreon\Domain\Entity\NagiosServer;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Domain\Repository\Traits\CheckListOfIdsTrait;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;

class NagiosServerRepository extends AbstractRepositoryRDB implements PaginationRepositoryInterface
{
    use CheckListOfIdsTrait;

    /** @var int $resultCountForPagination */
    private int $resultCountForPagination = 0;

    private const CONCORDANCE_ARRAY = [
        'id' => 'id',
        'name' => 'name',
        'localhost' => 'localhost',
        'isDefault' => 'is_default',
        'lastRestart' => 'last_restart',
        'nsIpress' => 'ns_ip_ress',
        'nsActivate' => 'ns_activate',
        'engineStartCommand' => 'engine_start_command',
        'engineStopCommand' => 'engine_stop_command',
        'engineRestartCommand' => 'engine_restart_command',
        'engineReloadCommand' => 'engine_reload_command',
        'nagiosBin' => 'nagios_bin',
        'nagiostatsBin' => 'nagiostats_bin',
        'nagiosPerfdata' => 'nagios_perfdata',
        'brokerReloadCommand' => 'broker_reload_command',
        'centreonbrokerCfgPath' => 'centreonbroker_cfg_path',
        'centreonbrokerModulePath' => 'centreonbroker_module_path',
        'centreonconnectorPath' => 'centreonconnector_path',
        'sshPort' => 'ssh_port',
        'gorgoneCommunicationType' => 'gorgone_communication_type',
        'gorgonePort' => 'gorgone_port',
        'initScriptCentreontrapd' => 'init_script_centreontrapd',
        'snmpTrapdPathConf' => 'snmp_trapd_path_conf',
        'engineName' => 'engine_name',
        'engineVersion' => 'engine_version',
        'centreonbrokerLogsPath' => 'centreonbroker_logs_path',
        'remoteId' => 'remote_id',
        'remoteServerUseAsProxy' => 'remote_server_use_as_proxy'
    ];

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

     /**
     * Check list of IDs
     *
     * @return bool
     */
    public function checkListOfIds(array $ids): bool
    {
        return $this->checkListOfIdsTrait($ids, NagiosServer::TABLE, NagiosServer::ENTITY_IDENTIFICATOR_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationList($filters = null, int $limit = null, int $offset = null, $ordering = []): array
    {
        $collector = new StatementCollector;

        $sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM `:db`.`nagios_server`';

        if ($filters !== null) {
            $isWhere = false;

            if ($filters['search'] ?? false) {
                $sql .= ' WHERE `name` LIKE :search';
                $collector->addValue(':search', "%{$filters['search']}%");
                $isWhere = true;
            }

            if (
                array_key_exists('ids', $filters)
                && is_array($filters['ids'])
                && [] !== $filters['ids']
            ) {
                $idsListKey = [];

                foreach ($filters['ids'] as $x => $id) {
                    $key = ":id{$x}";
                    $idsListKey[] = $key;
                    $collector->addValue($key, $id, \PDO::PARAM_INT);
                    unset($x, $id);
                }

                $sql .= $isWhere ? ' AND' : ' WHERE';
                $sql .= ' `' . self::CONCORDANCE_ARRAY['id']
                    . '` IN (' . implode(',', $idsListKey) . ')';
            }
        }

        if (!empty($ordering['field'])) {
            $sql .= ' ORDER BY `' . self::CONCORDANCE_ARRAY[$ordering['field']] . '` '
                . $ordering['order'];
        } else {
            $sql .= ' ORDER BY `name` ASC';
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $collector->addValue(':limit', $limit, \PDO::PARAM_INT);
        }

        if ($offset !== null) {
            $sql .= ' OFFSET :offset';
            $collector->addValue(':offset', $offset, \PDO::PARAM_INT);
        }

        $statement = $this->db->prepare($this->translateDbName($sql));
        $collector->bind($statement);
        $statement->execute();

        $foundRecords = $this->db->query('SELECT FOUND_ROWS()');

        if ($foundRecords !== false && ($total = $foundRecords->fetchColumn()) !== false) {
            $this->resultCountForPagination = $total;
        }

        $result = [];

        while ($data = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $this->createNagiosServerFromArray($data);
        }

        return $result;
    }

    private function createNagiosServerFromArray(array $data): NagiosServer
    {
        $nagiosServer = new NagiosServer();
        $nagiosServer->setId((int) $data['id']);
        $nagiosServer->setName($data['name']);
        $nagiosServer->setLocalhost($data['localhost']);
        $nagiosServer->setIsDefault((int) $data['is_default']);
        $nagiosServer->setLastRestart((int) $data['last_restart']);
        $nagiosServer->setNsIpAddress($data['ns_ip_address']);
        $nagiosServer->setNsActivate($data['ns_activate']);
        $nagiosServer->setEngineStartCommand($data['engine_start_command']);
        $nagiosServer->setEngineStopCommand($data['engine_stop_command']);
        $nagiosServer->setEngineRestartCommand($data['engine_restart_command']);
        $nagiosServer->setEngineReloadCommand($data['engine_reload_command']);
        $nagiosServer->setNagiosBin($data['nagios_bin']);
        $nagiosServer->setNagiostatsBin($data['nagiostats_bin']);
        $nagiosServer->setNagiosPerfdata($data['nagios_perfdata']);
        $nagiosServer->setBrokerReloadCommand($data['broker_reload_command']);
        $nagiosServer->setCentreonbrokerCfgPath($data['centreonbroker_cfg_path']);
        $nagiosServer->setCentreonbrokerModulePath($data['centreonbroker_module_path']);
        $nagiosServer->setCentreonconnectorPath($data['centreonconnector_path']);
        $nagiosServer->setSshPort((int) $data['ssh_port']);
        $nagiosServer->setGorgoneCommunicationType((int) $data['gorgone_communication_type']);
        $nagiosServer->setGorgonePort((int) $data['gorgone_port']);
        $nagiosServer->setInitScriptCentreontrapd($data['init_script_centreontrapd']);
        $nagiosServer->setSnmpTrapdPathConf($data['snmp_trapd_path_conf']);
        $nagiosServer->setEngineName($data['engine_name']);
        $nagiosServer->setEngineVersion($data['engine_version']);
        $nagiosServer->setCentreonbrokerLogsPath($data['centreonbroker_logs_path']);
        $nagiosServer->setRemoteId((int) $data['remote_id']);
        $nagiosServer->setRemoteServerUseAsProxy($data['remote_server_use_as_proxy']);

        return $nagiosServer;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationListTotal(): int
    {
        return $this->resultCountForPagination;
    }

    /**
     * Export poller's Nagios data
     *
     * @param int[] $pollerIds
     * @return array
     */
    public function export(array $pollerIds): array
    {
        // prevent SQL exception
        if (!$pollerIds) {
            return [];
        }

        $ids = join(',', $pollerIds);

        $sql = "SELECT * FROM nagios_server WHERE id IN ({$ids})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Truncate the data
     */
    public function truncate()
    {
        $sql = <<<SQL
TRUNCATE TABLE `nagios_server`;
TRUNCATE TABLE `cfg_nagios`;
TRUNCATE TABLE `cfg_nagios_broker_module`
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }

    /**
     * Sets poller as updated (shows that poller needs restarting)
     *
     * @param int $id id of poller
     */
    public function setUpdated(int $id): void
    {
        $sql = "UPDATE `nagios_server` SET `updated` = '1' WHERE `id` = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get Central Poller
     *
     * @return int|null
     */
    public function getCentral(): ?int
    {
        $query = "SELECT id FROM nagios_server WHERE localhost = '1' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            return null;
        }

        return (int)$stmt->fetch()['id'];
    }
}
