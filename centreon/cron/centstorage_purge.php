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

require_once realpath(__DIR__ . '/../www/class/centreonPurgeEngine.class.php');

echo '[' . date(DATE_RFC822) . "] PURGE STARTED\n";

try {
    $engine = new CentreonPurgeEngine();
    $engine->purge();
} catch (Exception $e) {
    echo '[' . date(DATE_RFC822) . '] ' . $e->getMessage();

    exit(1);
}

echo '[' . date(DATE_RFC822) . "] PURGE COMPLETED\n";

exit(0);
