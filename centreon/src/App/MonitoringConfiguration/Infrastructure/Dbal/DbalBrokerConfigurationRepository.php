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
    private const FILTERS_PARENT_KEY = 'filters';
    private const FILTER_CATEGORY_KEY = 'category';
    private const FILTER_EVENT_KEY = 'event';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(BrokerConfiguration $brokerConfiguration): void
    {
        $this->connection->transactional(function () use ($brokerConfiguration): void {
            $this->insertCfgCentreonBroker($brokerConfiguration);
            $this->insertCfgCentreonBrokerInputOutputs($brokerConfiguration);
            $this->insertCfgCentreonBrokerLog($brokerConfiguration);
        });
    }

    public function getCentralBbdoServerAuthorizationToken(): string
    {
        $qb = $this->connection->createQueryBuilder();

        $token = $qb
            ->select('JSON_UNQUOTE(JSON_EXTRACT(io.parameters, :authPath))')
            ->from('cfg_centreonbroker', 'cb')
            ->innerJoin('cb', 'nagios_server', 'ns', 'cb.ns_nagios_server = ns.id')
            ->innerJoin('cb', 'cfg_centreonbroker_input_output', 'io', (string) $qb->expr()->and(
                'io.config_id = cb.config_id',
                'io.tag = :inputTag',
                'io.type = :bbdoServer',
            ))
            // `localhost` and `config_activate` are ENUM('0','1'); comparing an ENUM to an integer
            // matches by ordinal index (1 -> the first value '0'), not by value. Compare to the
            // string '1' so the central, activated broker actually matches.
            ->where("ns.localhost = '1'")
            ->andWhere("cb.config_activate = '1'")
            ->andWhere('JSON_EXTRACT(io.parameters, :authPathFilter) IS NOT NULL')
            ->setParameter('inputTag', BrokerFlowGroupEnum::Input->value)
            ->setParameter('bbdoServer', BrokerStreamTypeEnum::BbdoServer->value)
            ->setParameter('authPath', '$.' . BrokerConfigKey::AUTHORIZATION)
            ->setParameter('authPathFilter', '$.' . BrokerConfigKey::AUTHORIZATION)
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
     * (needed by the `cfg_centreonbroker_input_output` / `_log` inserts). `bbdo_version` is left to its
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

    /**
     * INSERT one `cfg_centreonbroker_input_output` record per flow. The `type`/`type_id` columns are
     * derived from the stream kind (name -> `cb_type.cb_type_id`): the Domain speaks in names, the
     * Infrastructure resolves ids.
     *
     * @throws \JsonException
     */
    private function insertCfgCentreonBrokerInputOutputs(BrokerConfiguration $brokerConfiguration): void
    {
        $configId = $brokerConfiguration->id()->value;

        /** @var array<string, int> $typeIds */
        $typeIds = $this->connection->fetchAllKeyValue('SELECT type_shortname, cb_type_id FROM cb_type');

        foreach ($brokerConfiguration->flows as $flow) {
            $typeName = $flow->type->value;
            if (! array_key_exists($typeName, $typeIds)) {
                throw new \RuntimeException(sprintf(
                    'Unknown broker stream type "%s": no matching row in "cb_type".',
                    $typeName,
                ));
            }

            [$name, $parameters] = $this->encodeFlowParameters($flow);

            $this->connection->createQueryBuilder()
                ->insert('cfg_centreonbroker_input_output')
                ->values([
                    'config_id' => ':config_id',
                    'tag' => ':tag',
                    'input_output_id' => ':input_output_id',
                    'name' => ':name',
                    'type_id' => ':type_id',
                    'type' => ':type',
                    'parameters' => ':parameters',
                ])
                ->setParameter('config_id', $configId)
                ->setParameter('tag', $flow->group->value)
                ->setParameter('input_output_id', $flow->groupId)
                ->setParameter('name', $name)
                ->setParameter('type_id', (int) $typeIds[$typeName])
                ->setParameter('type', $typeName)
                ->setParameter(
                    'parameters',
                    json_encode(
                        $parameters === [] ? new \stdClass() : $parameters,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ),
                )
                ->executeStatement();
        }
    }

    /**
     * Encodes the flow parameters into the `parameters` JSON shape of the broker input/output API,
     * following the same rules as the legacy row reader:
     * - the `name` parameter goes to its own column, `type`/`blockId` are derived from the stream kind;
     * - parameters with a field index are grouped fields (`<group>__<field>`), listed in index order
     *   of first appearance per group;
     * - `category`/`event` parameters are filter values (`filters_<key>` lists), the empty `filters`
     *   parent rows are dropped;
     * - any other parameter is a simple `key => value`, the last one of a repeated key wins.
     *
     * @return array{string, array<string, string|list<string>|list<array<string, string>>>}
     */
    private function encodeFlowParameters(BrokerInputOutput $flow): array
    {
        $name = '';
        /** @var array<string, string> $simple */
        $simple = [];
        /** @var array<string, list<string>> $filters */
        $filters = [];
        /** @var array<string, array<int, array<string, string>>> $groups */
        $groups = [];

        foreach ($flow->parameters as $parameter) {
            $key = $parameter->configKey;
            $value = $parameter->configValue;

            if ($parameter->fieldIndex !== null) {
                $keyParts = explode('__', $key, 3);
                $groups[$keyParts[0]][$parameter->fieldIndex][$keyParts[1] ?? ''] = $value;

                continue;
            }

            if ($key === BrokerConfigKey::NAME) {
                $name = $value;
            } elseif ($key === BrokerConfigKey::TYPE || $key === BrokerConfigKey::BLOCK_ID) {
                // Derived from the stream kind at persistence.
                continue;
            } elseif ($key === self::FILTER_CATEGORY_KEY || $key === self::FILTER_EVENT_KEY) {
                $filters[self::FILTERS_PARENT_KEY . '_' . $key][] = $value;
            } elseif (
                $parameter->subGroupId === null
                && ! ($key === self::FILTERS_PARENT_KEY && $value === '')
            ) {
                $simple[$key] = $value;
            }
        }

        return [$name, [...$simple, ...$filters, ...array_map(array_values(...), $groups)]];
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
