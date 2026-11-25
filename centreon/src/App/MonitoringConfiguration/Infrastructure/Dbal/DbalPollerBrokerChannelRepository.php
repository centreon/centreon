<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Model\BrokerChannelEndpoint;
use App\MonitoringConfiguration\Domain\Repository\PollerBrokerChannelRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The dial address lives in the poller's engine module (cfg_centreonbroker daemon = '0'): its first output
 * that dials out (ipv4 or bbdo_client), the one the generator turns into the TLS bbdo_client.
 */
final readonly class DbalPollerBrokerChannelRepository implements PollerBrokerChannelRepository
{
    private const DIALING_OUTPUT_TYPES = ['ipv4', 'bbdo_client'];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function useBrokerChannel(PollerId $pollerId, BrokerChannelEndpoint $endpoint): void
    {
        $this->connection->transactional(function () use ($pollerId, $endpoint): void {
            $this->connection->executeStatement(
                "UPDATE nagios_server SET remote_channel = 'broker' WHERE id = :id",
                ['id' => $pollerId->value],
            );

            $output = $this->findDialingOutput($pollerId);
            if ($output === null) {
                throw new \RuntimeException(sprintf(
                    'Poller #%d has no engine module output to the central broker.',
                    $pollerId->value,
                ));
            }

            foreach (['host' => $endpoint->host, 'port' => (string) $endpoint->port] as $key => $value) {
                $this->connection->executeStatement(
                    'UPDATE cfg_centreonbroker_info SET config_value = :value
                      WHERE config_id = :configId AND config_group = :group
                        AND config_group_id = :groupId AND config_key = :key',
                    [
                        'value' => $value,
                        'configId' => $output['config_id'],
                        'group' => 'output',
                        'groupId' => $output['config_group_id'],
                        'key' => $key,
                    ],
                );
            }
        });
    }

    public function findBrokerChannelEndpoint(PollerId $pollerId): ?BrokerChannelEndpoint
    {
        $channel = $this->connection->fetchOne(
            'SELECT remote_channel FROM nagios_server WHERE id = :id',
            ['id' => $pollerId->value],
        );
        if ($channel !== 'broker') {
            return null;
        }

        $output = $this->findDialingOutput($pollerId);
        if ($output === null) {
            return null;
        }

        $values = $this->connection->fetchAllKeyValue(
            'SELECT config_key, config_value FROM cfg_centreonbroker_info
              WHERE config_id = :configId AND config_group = :group AND config_group_id = :groupId
                AND config_key IN (:keys)',
            [
                'configId' => $output['config_id'],
                'group' => 'output',
                'groupId' => $output['config_group_id'],
                'keys' => ['host', 'port'],
            ],
            ['keys' => ArrayParameterType::STRING],
        );
        $host = $values['host'] ?? null;
        $port = $values['port'] ?? null;
        if (! is_string($host) || trim($host) === '' || ! is_numeric($port) || (int) $port <= 0) {
            return null;
        }

        try {
            return new BrokerChannelEndpoint(trim($host), (int) $port);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function findPollerIdByUid(PollerUid $uid): ?PollerId
    {
        $pollerId = $this->connection->fetchOne(
            'SELECT id FROM nagios_server WHERE uid = :uid',
            ['uid' => $uid->value],
        );

        return is_numeric($pollerId) ? new PollerId((int) $pollerId) : null;
    }

    /**
     * @return array{config_id: int, config_group_id: int}|null
     */
    private function findDialingOutput(PollerId $pollerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT info.config_id, info.config_group_id
               FROM cfg_centreonbroker cb
               INNER JOIN cfg_centreonbroker_info info
                       ON info.config_id = cb.config_id
                      AND info.config_group = 'output'
                      AND info.config_key = 'type'
              WHERE cb.ns_nagios_server = :id
                AND cb.daemon = '0'
                AND info.config_value IN (:types)
              ORDER BY info.config_id, info.config_group_id
              LIMIT 1",
            ['id' => $pollerId->value, 'types' => self::DIALING_OUTPUT_TYPES],
            ['types' => ArrayParameterType::STRING],
        );

        if ($row === false || ! is_numeric($row['config_id'] ?? null) || ! is_numeric($row['config_group_id'] ?? null)) {
            return null;
        }

        return ['config_id' => (int) $row['config_id'], 'config_group_id' => (int) $row['config_group_id']];
    }
}
