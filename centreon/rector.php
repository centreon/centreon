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

$rectorConfig = require_once __DIR__ . '/../tools/rector/config/rector.php';

return $rectorConfig
    ->withCache(__DIR__ . '/var/cache/rector')
    ->withPaths([
        // directories
        __DIR__ . '/api',
        __DIR__ . '/config',
        __DIR__ . '/cron',
        __DIR__ . '/lib',
        __DIR__ . '/libinstall',
        __DIR__ . '/packaging',
        __DIR__ . '/src',
        __DIR__ . '/tests/php',
        __DIR__ . '/tools',
        __DIR__ . '/www',
        // files
        __DIR__ . '/.env.local.php',
        __DIR__ . '/.php-cs-fixer.core.php',
        __DIR__ . '/.php-cs-fixer.legacy.src.php',
        __DIR__ . '/.php-cs-fixer.new.php',
        __DIR__ . '/rector.php',
        __DIR__ . '/bootstrap.php',
        __DIR__ . '/container.php',
    ])
    ->withSkip([
        // directories
        __DIR__ . '/www/class/centreon-clapi/',
    ]);
