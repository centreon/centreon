#!@PHP_BIN@
<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

require_once realpath(__DIR__ . "/../config/centreon.config.php");
include_once _CENTREON_PATH_ . "/www/class/centreonDB.class.php";
include_once _CENTREON_PATH_ . "/www/class/centreonLog.class.php";

$centreonDbName = $conf_centreon['db'];
$centreonLog = new CentreonLog();

/*
* Init DB connections
*/
$pearDB = new CentreonDB();

$pearDB->beginTransaction();
 try {
    deleteExpiredProviderRefreshTokens($centreonLog, $pearDB);
    deleteExpiredProviderTokens($centreonLog, $pearDB);
    deleteExpiredSessions($centreonLog, $pearDB);

    $pearDB->commit();
} catch (\Throwable) {
    $pearDB->rollBack();
    $centreonLog->insertLog(
        2,
        "TokenRemoval CRON: failed to delete old tokens"
    );
}


/**
 * Delete expired provider refresh tokens.
 */
function deleteExpiredProviderRefreshTokens(CentreonLog $logger, CentreonDB $pearDB): void
{
    $logger->insertLog(2, 'Deleting expired refresh tokens');

    $pearDB->query(
        <<<'SQL'
            DELETE st FROM security_token st
            WHERE st.expiration_date < UNIX_TIMESTAMP(NOW())
            AND EXISTS (
                SELECT 1
                FROM security_authentication_tokens sat
                WHERE sat.provider_token_refresh_id = st.id
                AND sat.token_type = 'auto'
                LIMIT 1
            )
            SQL
    );
}

/**
 * Delete provider refresh tokens which are not linked to a refresh token.
 */
function deleteExpiredProviderTokens(CentreonLog $logger, CentreonDB $pearDB): void
{
    $logger->insertLog(2, 'Deleting expired tokens which are not linked to a refresh token');

    $pearDB->query(
        <<<'SQL'
            DELETE st FROM security_token st
            WHERE st.expiration_date < UNIX_TIMESTAMP(NOW())
            AND NOT EXISTS (
                SELECT 1
                FROM security_authentication_tokens sat
                WHERE sat.provider_token_id = st.id
                AND (sat.provider_token_refresh_id IS NOT NULL OR sat.token_type = 'manual')
                LIMIT 1
            )
            SQL
    );

}

/**
 * Delete expired sessions.
 */
function deleteExpiredSessions(CentreonLog $logger, CentreonDB $pearDB): void
{
    $logger->insertLog(2, 'Deleting expired sessions');

    $pearDB->query(
        <<<'SQL'
            DELETE s FROM session s
            WHERE s.last_reload < (
                SELECT UNIX_TIMESTAMP(NOW() - INTERVAL (`value` * 60) SECOND)
                FROM options
                WHERE `key` = 'session_expire'
            )
            OR s.last_reload IS NULL
            OR s.session_id NOT IN (
                SELECT token FROM security_authentication_tokens
            )
            SQL
    );
}
