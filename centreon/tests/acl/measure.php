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

/*
 * Prepended to cron/centAcl.php by "qualify.php scenarios" (auto_prepend_file), so that the engine is measured
 * without being modified: at shutdown, writes its PHP peak memory, its max resident size and what it did to
 * centreon_acl ($aclRunStats, when the engine provides it) to $ACL_MEASURE_FILE.
 */
$aclMeasureFile = getenv('ACL_MEASURE_FILE');
if (is_string($aclMeasureFile) && $aclMeasureFile !== '') {
    register_shutdown_function(static function () use ($aclMeasureFile): void {
        file_put_contents($aclMeasureFile, json_encode([
            // Memory really used by PHP, not the 2 MB chunks reserved by its allocator: it does not depend on the
            // machine, so that two engines can be compared on it.
            'peak_bytes' => memory_get_peak_usage(),
            // Kilobytes on Linux, the CI system.
            'max_rss_kb' => getrusage()['ru_maxrss'] ?? 0,
            'stats' => $GLOBALS['aclRunStats'] ?? null,
        ], JSON_THROW_ON_ERROR));
    });
}
unset($aclMeasureFile);
