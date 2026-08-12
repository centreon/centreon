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
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = '26.07.1';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/**
 * Soft-delete stale legacy centreon_storage.instances rows (deleted = 0, frozen last_alive)
 * left after the 26.07 Snowflake UID migration, which make the poller "database updates"
 * indicator turn red. Only when the fresh uid row is strictly newer, so it is safe and
 * idempotent for platforms already upgraded.
 */
$softDeleteStaleLegacyInstances = function () use ($pearDB, $pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to fetch pollers for the stale instances cleanup';
    $pollers = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT `id`, `uid`
            FROM `nagios_server`
            WHERE `uid` IS NOT NULL AND `uid` <> `id`
            SQL
    );

    if ($pollers === []) {
        LoggerUpgrade::create()->info(
            $version,
            'No poller with a distinct Snowflake UID, skipping stale instances cleanup'
        );

        return;
    }

    $softDeleted = 0;
    foreach ($pollers as $poller) {
        $legacyId = (int) $poller['id'];
        $uid = (int) $poller['uid'];

        // Atomic soft-delete: remove the legacy row only when a live UID row is strictly
        // newer, evaluated at write time so a legacy heartbeat between check and update
        // cannot delete an active row. A downgrade (active legacy, stale UID) and a
        // poller with no fresh UID row both yield a zero-row update (MON-206900).
        $errorMessage = "Unable to soft-delete the stale legacy instances row for poller id={$legacyId}";
        $softDeleted += $pearDBO->update(
            <<<'SQL'
                UPDATE `instances` AS legacy
                INNER JOIN `instances` AS fresh
                    ON fresh.`instance_id` = :uid AND fresh.`deleted` = 0
                SET legacy.`deleted` = 1
                WHERE legacy.`instance_id` = :legacyId
                    AND legacy.`deleted` = 0
                    AND fresh.`last_alive` > legacy.`last_alive`
                SQL,
            QueryParameters::create([
                QueryParameter::int('legacyId', $legacyId),
                QueryParameter::int('uid', $uid),
            ])
        );
    }

    LoggerUpgrade::create()->info(
        $version,
        "Stale legacy instances cleanup completed, {$softDeleted} row(s) soft-deleted"
    );
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    $softDeleteStaleLegacyInstances();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    LoggerUpgrade::create()->stepFailure(
        $version,
        'php_script',
        "UPGRADE - {$version}: {$errorMessage}",
        $throwable
    );

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
