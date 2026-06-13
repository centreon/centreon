#!@PHP_BIN@
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

require_once realpath(__DIR__ . '/../config/centreon.config.php');
include_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';

$pearDB = new CentreonDB();

try {
    // keep about 13 months so a year-over-year view stays available
    $pearDB->executeStatement(
        <<<'SQL'
            DELETE FROM ai_provider_usage_log
            WHERE requested_at < UNIX_TIMESTAMP(NOW() - INTERVAL 13 MONTH)
            SQL
    );

    echo '[' . date(DATE_RFC822) . "] AI provider usage purge completed\n";
} catch (Throwable $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_BUSINESS_LOG,
        'AiProviderUsagePurge CRON: failed to purge old usage rows'
    );

    echo '[' . date(DATE_RFC822) . '] ' . $e->getMessage() . "\n";

    exit(1);
}

exit(0);
