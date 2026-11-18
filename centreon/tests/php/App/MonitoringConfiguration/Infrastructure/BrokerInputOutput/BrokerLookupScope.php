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

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

/**
 * Scope of the BrokerLookup* characterization tests.
 *
 * Several lookups are global (they read every broker configuration of every "localhost" poller), so data created
 * concurrently by other tests sharing the database can be returned. Those tests own the pollers of
 * [FIRST_POLLER_ID, LAST_POLLER_ID] and use this class to tell their own data from foreign data, through the
 * nagios_server and cfg_centreonbroker tables only (never through the storage of broker inputs/outputs).
 */
final readonly class BrokerLookupScope
{
    public const FIRST_POLLER_ID = 9500;
    public const LAST_POLLER_ID = 9599;

    public function __construct(private BrokerTestDatabase $database)
    {
    }

    public static function isOwnPoller(int $pollerId): bool
    {
        return $pollerId >= self::FIRST_POLLER_ID && $pollerId <= self::LAST_POLLER_ID;
    }

    /**
     * Whether a "localhost" poller not owned by these tests currently exists (base data or concurrent tests).
     */
    public function foreignLocalhostPollerExists(): bool
    {
        $statement = $this->database->configuration->prepare(
            <<<'SQL'
                SELECT COUNT(*) FROM nagios_server
                WHERE localhost = '1' AND id NOT BETWEEN :first AND :last
                SQL
        );
        $statement->bindValue(':first', self::FIRST_POLLER_ID, \PDO::PARAM_INT);
        $statement->bindValue(':last', self::LAST_POLLER_ID, \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn() > 0;
    }

    public function pollerOfBrokerConfiguration(int $configId): ?int
    {
        $statement = $this->database->configuration->prepare(
            'SELECT ns_nagios_server FROM cfg_centreonbroker WHERE config_id = :configId'
        );
        $statement->bindValue(':configId', $configId, \PDO::PARAM_INT);
        $statement->execute();

        $pollerId = $statement->fetchColumn();

        return $pollerId === false || $pollerId === null ? null : (int) $pollerId;
    }

    public function setBrokerConfigurationActivation(int $configId, bool $activated): void
    {
        $statement = $this->database->configuration->prepare(
            'UPDATE cfg_centreonbroker SET config_activate = :activate WHERE config_id = :configId'
        );
        $statement->bindValue(':activate', $activated ? '1' : '0');
        $statement->bindValue(':configId', $configId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
