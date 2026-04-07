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

$projectDir = dirname(__DIR__, 4);

// Path constants required by App\Kernel (cacheDir, logDir) and centreon.config.php.
// Without these, the Kernel defaults to /var/cache/centreon/symfony which does not exist
// outside Docker, and centreon.config.php cannot locate centreon.conf.php.
$pathConstants = [
    '_CENTREON_PATH_' => $projectDir . '/',
    '_CENTREON_ETC_' => $projectDir . '/',
    '_CENTREON_LOG_' => sys_get_temp_dir() . '/centreon-test-logs/',
    '_CENTREON_CACHEDIR_' => sys_get_temp_dir() . '/centreon-test-cache/',
    '_CENTREON_VARLIB_' => sys_get_temp_dir() . '/centreon-test-varlib/',
];
foreach ($pathConstants as $name => $value) {
    if (! defined($name)) {
        define($name, $value);
    }
}

// Version constants expected by centreon.config.php.
$versionConstants = [
    '_CENTREON_PHP_VERSION_' => '8.2',
    '_CENTREON_MARIA_DB_MIN_VERSION_' => '10.5',
];
foreach ($versionConstants as $name => $value) {
    if (! defined($name)) {
        define($name, $value);
    }
}

require $projectDir . '/vendor/autoload.php';
require $projectDir . '/config/bootstrap.php';
