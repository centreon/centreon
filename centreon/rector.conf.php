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

$pathsLegacy = [
    // directories
    'api/',
    'config/',
    'cron/',
    'lib/',
    'libinstall/',
    'packaging/',
    'src/',
    'tests/php/',
    'tools/',
    'www/',
    // files
    'bootstrap.php',
    'container.php',
];

$skipPathsLegacy = [
    // directories
    'www/class/centreon-clapi/',
    'src/Adaptation',
    'src/App',
    'src/Core',
    'tests/php/Adaptation',
    'tests/php/App',
    'tests/php/Core',
];

$pathsCore = [
    // directories
    'src/Core/',
    'tests/php/Core/',
];

$pathsNew = [
    // directories
    'src/Adaptation/',
    'src/App/',
    'tests/php/Adaptation/',
    'tests/php/App/',
    // files
    '.env.local.php',
    '.php-cs-fixer.core.php',
    '.php-cs-fixer.legacy.php',
    '.php-cs-fixer.new.php',
    'rector.conf.php',
    'rector.core.php',
    'rector.diff.php',
    'rector.legacy.php',
    'rector.new.php',
];
