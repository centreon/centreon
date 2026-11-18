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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigKey;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigurationId;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerFlowGroupEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerInputOutput;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\BrokerConfigurationRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalBrokerConfigurationRepository extends DbalRepository implements BrokerConfigurationRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(BrokerConfiguration $brokerConfiguration): void
    {
        $this->connection->transactional(function () use ($brokerConfiguration): void {
            $this->insertCfgCentreonBroker($brokerConfiguration);
            $this->insertCfgCentreonBrokerInfo($brokerConfiguration);
            $this->insertCfgCentreonBrokerLog($brokerConfiguration);
        });
    }

    public function getCentralBbdoServerAuthorizationToken(): string
    {
        $qb = $this->connection->createQueryBuilder();

        $token = $qb
            ->select('auth.config_value')
            ->from('cfg_centreonbroker', 'cb')
            ->innerJoin('cb', 'nagios_server', 'ns', 'cb.ns_nagios_server = ns.id')
            ->innerJoin('cb', 'cfg_centreonbroker_info', 'typ', (string) $qb->expr()->and(
                'typ.config_id = cb.config_id',
                'typ.config_group = :inputGroup',
                'typ.config_key = :typeKey',
                'typ.config_value = :bbdoServer',
            ))
            ->innerJoin('cb', 'cfg_centreonbroker_info', 'auth', (string) $qb->expr()->and(
                'auth.config_id = cb.config_id',
                'auth.config_group = :inputGroup',
                'auth.config_key = :authKey',
                'auth.config_group_id = typ.config_group_id',
            ))
            // `localhost` and `config_activate` are ENUM('0','1'); comparing an ENUM to an integer
            // matches by ordinal index (1 -> the first value '0'), not by value. Compare to the
            // string '1' so the central, activated broker actually matches.
            ->where("ns.localhost = '1'")
            ->andWhere("cb.config_activate = '1'")
            ->setParameter('inputGroup', BrokerFlowGroupEnum::Input->value)
            ->setParameter('typeKey', BrokerConfigKey::TYPE)
            ->setParameter('bbdoServer', BrokerStreamTypeEnum::BbdoServer->value)
            ->setParameter('authKey', BrokerConfigKey::AUTHORIZATION)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('No BBDO server authorization token found on the central broker');
        }

        return $token;
    }

    /**
     * INSERT the `cfg_centreonbroker` header row, then assign the generated id to the aggregate
     * (needed by the `cfg_centreonbroker_info` / `_log` inserts). `bbdo_version` is left to its
     * DB default (`3.1.0`), matching the wizard.
     */
    private function insertCfgCentreonBroker(BrokerConfiguration $brokerConfiguration): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert('cfg_centreonbroker')
            ->values([
                'config_name' => ':config_name',
                'config_filename' => ':config_filename',
                'config_write_timestamp' => ':config_write_timestamp',
                'config_write_thread_id' => ':config_write_thread_id',
                'config_activate' => ':config_activate',
                'ns_nagios_server' => ':ns_nagios_server',
                'event_queue_max_size' => ':event_queue_max_size',
                'command_file' => ':command_file',
                'cache_directory' => ':cache_directory',
                'log_directory' => ':log_directory',
                'stats_activate' => ':stats_activate',
                'daemon' => ':daemon',
            ])
            ->setParameter('config_name', $brokerConfiguration->name->value)
            ->setParameter('config_filename', $brokerConfiguration->fileName->value)
            ->setParameter('config_write_timestamp', $brokerConfiguration->configWriteTimestamp ? '1' : '0')
            ->setParameter('config_write_thread_id', $brokerConfiguration->configWriteThreadId ? '1' : '0')
            ->setParameter('config_activate', $brokerConfiguration->isActivated ? '1' : '0')
            ->setParameter('ns_nagios_server', $brokerConfiguration->pollerId->value)
            ->setParameter('event_queue_max_size', $brokerConfiguration->eventQueueMaxSize)
            ->setParameter('command_file', $brokerConfiguration->commandFile)
            ->setParameter('cache_directory', $brokerConfiguration->cacheDirectory)
            ->setParameter('log_directory', $brokerConfiguration->logDirectory)
            ->setParameter('stats_activate', $brokerConfiguration->statsActivate ? '1' : '0')
            ->setParameter('daemon', $brokerConfiguration->daemon ? '1' : '0')
            ->executeStatement();

        $configId = (int) $this->connection->lastInsertId();

        if ($configId === 0) {
            throw new \RuntimeException(sprintf(
                'Unable to retrieve last insert ID for "cfg_centreonbroker" (poller_id=%d).',
                $brokerConfiguration->pollerId->value,
            ));
        }

        $this->setId($brokerConfiguration, new BrokerConfigurationId($configId));
    }

    private function insertCfgCentreonBrokerInfo(BrokerConfiguration $brokerConfiguration): void
    {
        $configId = $brokerConfiguration->id()->value;

        /** @var array<string, int> $typeIds */
        $typeIds = $this->connection->fetchAllKeyValue('SELECT type_shortname, cb_type_id FROM cb_type');

        foreach ($brokerConfiguration->flows as $flow) {
            foreach ($flow->parameters as $parameter) {
                $this->insertCfgCentreonBrokerInfoRow(
                    $configId,
                    $flow,
                    $parameter->configKey,
                    $parameter->configValue,
                    $parameter->groupLevel,
                    $parameter->subGroupId,
                    $parameter->parentGroupId,
                    $parameter->fieldIndex,
                );
            }

            // The stream kind is modeled as BrokerStreamTypeEnum; the Domain never hand-writes the
            // `type`/`blockId` meta rows. Derive them here (name -> cb_type.id) exactly as the
            // logger rows are resolved below — Domain speaks in names, Infrastructure resolves ids.
            $typeName = $flow->type->value;
            if (! array_key_exists($typeName, $typeIds)) {
                throw new \RuntimeException(sprintf(
                    'Unknown broker stream type "%s": no matching row in "cb_type".',
                    $typeName,
                ));
            }

            $this->insertCfgCentreonBrokerInfoRow($configId, $flow, BrokerConfigKey::TYPE, $typeName);
            $this->insertCfgCentreonBrokerInfoRow(
                $configId,
                $flow,
                BrokerConfigKey::BLOCK_ID,
                $this->blockPrefix($flow->group) . '_' . $typeIds[$typeName],
            );
        }
    }

    private function insertCfgCentreonBrokerInfoRow(
        int $configId,
        BrokerInputOutput $flow,
        string $configKey,
        string $configValue,
        int $groupLevel = 0,
        ?int $subGroupId = null,
        ?int $parentGroupId = null,
        ?int $fieldIndex = null,
    ): void {
        $this->connection->createQueryBuilder()
            ->insert('cfg_centreonbroker_info')
            ->values([
                'config_id' => ':config_id',
                'config_key' => ':config_key',
                'config_value' => ':config_value',
                'config_group' => ':config_group',
                'config_group_id' => ':config_group_id',
                'grp_level' => ':grp_level',
                'subgrp_id' => ':subgrp_id',
                'parent_grp_id' => ':parent_grp_id',
                'fieldIndex' => ':field_index',
            ])
            ->setParameter('config_id', $configId)
            ->setParameter('config_key', $configKey)
            ->setParameter('config_value', $configValue)
            ->setParameter('config_group', $flow->group->value)
            ->setParameter('config_group_id', $flow->groupId)
            ->setParameter('grp_level', $groupLevel)
            ->setParameter('subgrp_id', $subGroupId)
            ->setParameter('parent_grp_id', $parentGroupId)
            ->setParameter('field_index', $fieldIndex)
            ->executeStatement();
    }

    /**
     * The `blockId` prefix, i.e. the `cb_tag` id the flow's group maps to (output = 1,
     * input = 2) — the legacy `{tagId}_{typeId}` block-identifier encoding.
     */
    private function blockPrefix(BrokerFlowGroupEnum $group): int
    {
        return match ($group) {
            BrokerFlowGroupEnum::Output => 1,
            BrokerFlowGroupEnum::Input => 2,
        };
    }

    private function insertCfgCentreonBrokerLog(BrokerConfiguration $brokerConfiguration): void
    {
        $configId = $brokerConfiguration->id()->value;

        /** @var array<string, int> $loggerIds */
        $loggerIds = $this->connection->fetchAllKeyValue('SELECT name, id FROM cb_log');
        /** @var array<string, int> $levelIds */
        $levelIds = $this->connection->fetchAllKeyValue('SELECT name, id FROM cb_log_level');

        foreach ($brokerConfiguration->logs as $log) {
            $loggerName = $log->logger->value;
            $levelName = $log->level->value;

            if (! array_key_exists($loggerName, $loggerIds)) {
                throw new \RuntimeException(sprintf(
                    'Unknown broker log category "%s": no matching row in "cb_log".',
                    $loggerName,
                ));
            }
            if (! array_key_exists($levelName, $levelIds)) {
                throw new \RuntimeException(sprintf(
                    'Unknown broker log level "%s": no matching row in "cb_log_level".',
                    $levelName,
                ));
            }

            $this->connection->createQueryBuilder()
                ->insert('cfg_centreonbroker_log')
                ->values([
                    'id_centreonbroker' => ':id_centreonbroker',
                    'id_log' => ':id_log',
                    'id_level' => ':id_level',
                ])
                ->setParameter('id_centreonbroker', $configId)
                ->setParameter('id_log', $loggerIds[$loggerName])
                ->setParameter('id_level', $levelIds[$levelName])
                ->executeStatement();
        }
    }
}
