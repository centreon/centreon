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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

$addCentralAddressColumn = function () use ($pearDB, &$errorMessage, $version): void {
    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'platform_topology',
        'central_address'
    )) {
        LoggerUpgrade::create()->info($version, 'central_address column already exists, skipping');

        return;
    }

    $errorMessage = 'Unable to add central_address column to platform_topology';
    LoggerUpgrade::create()->info($version, 'Adding central_address column to platform_topology');

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `platform_topology`
            ADD COLUMN `central_address` varchar(255) NULL AFTER `address`
            SQL
    );

    LoggerUpgrade::create()->info($version, 'Successfully added central_address column');
};

/**
 * Resolve central address from /etc/centreon/poller_installation (cloud only).
 * Builds address as: {orga}.{region}.{domain}/{site}
 */
$resolveCloudCentralAddress = function () use ($version): ?string {
    $filePath = _CENTREON_ETC_ . '/poller_installation';

    if (! is_readable($filePath)) {
        LoggerUpgrade::create()->warning(
            $version,
            "Cloud platform: {$filePath} not found or not readable"
        );

        return null;
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        LoggerUpgrade::create()->warning(
            $version,
            "Cloud platform: could not read {$filePath}"
        );

        return null;
    }

    $hasAllFields = preg_match('/CLOUD_REGION="([^"]+)"/', $content, $regionMatch)
        && preg_match('/CLOUD_DOMAIN="([^"]+)"/', $content, $domainMatch)
        && preg_match('/\binstall\b.*-o\s+([^\s;]+)/s', $content, $orgaMatch)
        && preg_match('/\binstall\b.*-s\s+([^\s;]+)/s', $content, $siteMatch);

    if (! $hasAllFields) {
        LoggerUpgrade::create()->warning(
            $version,
            "Cloud platform: could not extract CLOUD_REGION, CLOUD_DOMAIN, -o or -s from {$filePath}"
        );

        return null;
    }

    return "{$orgaMatch[1]}.{$regionMatch[1]}.{$domainMatch[1]}/{$siteMatch[1]}";
};

/**
 * Resolve central address from broker output configs (on-prem).
 * Looks for IPv4 or BBDO Client outputs with a non-empty host.
 * When multiple outputs exist, takes the most recent one (highest id).
 *
 * @param array{server_id: ?int} $platform
 */
$resolveOnPremCentralAddress = function (array $platform) use ($pearDB): ?string {
    if ($platform['server_id'] === null) {
        return null;
    }

    $host = $pearDB->fetchOne(
        <<<'SQL'
            SELECT cbi_host.config_value
            FROM cfg_centreonbroker_info cbi_host
            JOIN cfg_centreonbroker_info cbi_type
                ON cbi_type.config_id = cbi_host.config_id
                AND cbi_type.config_group = cbi_host.config_group
                AND cbi_type.config_group_id = cbi_host.config_group_id
                AND cbi_type.config_key = 'type'
                AND cbi_type.config_value IN ('ipv4', 'bbdo_client')
            JOIN cfg_centreonbroker cb
                ON cb.config_id = cbi_host.config_id
            WHERE cb.ns_nagios_server = :serverId
                AND cbi_host.config_group = 'output'
                AND cbi_host.config_key = 'host'
                AND TRIM(cbi_host.config_value) != ''
            ORDER BY cbi_host.id DESC
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::int('serverId', (int) $platform['server_id']),
        ])
    );

    return is_string($host) && trim($host) !== '' ? trim($host) : null;
};

$populateCentralAddress = function () use ($pearDB, &$errorMessage, $version, $resolveCloudCentralAddress, $resolveOnPremCentralAddress): void {
    $isCloudPlatform = filter_var(
        $_ENV['IS_CLOUD_PLATFORM'] ?? null,
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) === true;

    $errorMessage = 'Unable to populate central_address for central servers';
    LoggerUpgrade::create()->info($version, 'Setting central_address = address for central servers');

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `platform_topology`
            SET `central_address` = `address`
            WHERE `type` = 'central'
            SQL
    );

    $errorMessage = 'Unable to fetch non-central platforms';
    $platforms = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT `id`, `server_id`, `address`, `type`
            FROM `platform_topology`
            WHERE `type` != 'central'
            SQL
    );

    $cloudCentralAddress = $isCloudPlatform ? $resolveCloudCentralAddress() : null;

    foreach ($platforms as $platform) {
        if ($isCloudPlatform) {
            $centralAddress = $cloudCentralAddress ?? $platform['address'];
        } else {
            $centralAddress = $resolveOnPremCentralAddress($platform) ?? $platform['address'];
        }

        if ($centralAddress === $platform['address']) {
            $reason = $isCloudPlatform
                ? 'Could not resolve central address from /etc/centreon/poller_installation'
                : "No broker output host found (server_id={$platform['server_id']})";

            LoggerUpgrade::create()->warning(
                $version,
                "{$reason} for platform id={$platform['id']}, "
                . "falling back to address '{$centralAddress}'. "
                . 'Please verify this value is correct.'
            );
        }

        $errorMessage = "Unable to update central_address for platform id={$platform['id']}";
        $pearDB->executeStatement(
            <<<'SQL'
                UPDATE `platform_topology`
                SET `central_address` = :centralAddress
                WHERE `id` = :platformId
                SQL,
            QueryParameters::create([
                QueryParameter::string('centralAddress', $centralAddress),
                QueryParameter::int('platformId', (int) $platform['id']),
            ])
        );
    }

    LoggerUpgrade::create()->info($version, 'Successfully populated central_address for all platforms');
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    $addCentralAddressColumn();

    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $populateCentralAddress();

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
