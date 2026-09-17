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

declare(strict_types = 1);

/*
 * Shared guard for cron entry points. When Centreon has no configuration yet
 * (fresh install, or a cron job running before first-time setup), exit quietly
 * to avoid polluting cron logs. When run interactively, report the missing file.
 *
 * The including script must have defined _CENTREON_ETC_ (via centreon.config.php)
 * before requiring this file.
 */
if (!file_exists(_CENTREON_ETC_ . '/centreon.conf.php')) {
    // No TTY: assume unattended (cron, etc.) and exit quietly to avoid noise before first-time setup
    if (posix_isatty(STDIN)) {
        fwrite(STDERR, "Configuration file \"" . _CENTREON_ETC_ . "/centreon.conf.php\" does not exist." . PHP_EOL);
        exit(1);
    } else {
        exit(0);
    }
}
