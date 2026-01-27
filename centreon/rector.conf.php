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

declare(strict_types=1);

use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return [
    'legacy' => [
        'paths' => [
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
        ],
        'skip' => [
            // directories
            'www/class/centreon-clapi/',
            'src/Adaptation',
            'src/App',
            'src/Core',
            'tests/php/Adaptation',
            'tests/php/App',
            'tests/php/Core',
        ],
    ],
    'core' => [
        'paths' => [
            // directories
            'src/Core/',
            'tests/php/Core/',
        ],
        'skip' => [],
    ],
    'new' => [
        'paths' => [
            // directories
            'src/Adaptation/',
            'src/App/',
            'tests/php/Adaptation/',
            'tests/php/App/',
            // files
            '.env.local.php',
            '.php-cs-fixer.conf.php',
            '.php-cs-fixer.core.php',
            '.php-cs-fixer.diff.php',
            '.php-cs-fixer.legacy.php',
            '.php-cs-fixer.new.php',
            'rector.conf.php',
            'rector.core.php',
            'rector.diff.php',
            'rector.legacy.php',
            'rector.new.php',
        ],
        'skip' => [
            // because id are only able to be set by object construction, rector
            // tries to set it as readonly. But in repositories, we set the id using
            // reflection (to protect the domain). Therefore the id property cannot be
            // readonly.
            ReadOnlyPropertyRector::class => __DIR__ . '/src/App/*/Domain/Aggregate/*',
        ],
    ],
];
