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

use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

$rectorConfig = require_once __DIR__ . '/../php-tools/rector/config/base.strict.php';

return $rectorConfig
    ->withCache(__DIR__ . '/var/cache/rector.new')
    ->withPaths([
        // directories
        __DIR__ . '/src/Adaptation',
        __DIR__ . '/src/App',
        __DIR__ . '/tests/php/Adaptation',
        __DIR__ . '/tests/php/App',
        // files
        __DIR__ . '/.env.local.php',
        __DIR__ . '/.php-cs-fixer.core.php',
        __DIR__ . '/.php-cs-fixer.legacy.php',
        __DIR__ . '/.php-cs-fixer.new.php',
        __DIR__ . '/rector.core.php',
        __DIR__ . '/rector.legacy.php',
        __DIR__ . '/rector.new.php',
    ])
    ->withSkip([
        // because id are only able to be set by object construction, rector
        // tries to set it as readonly. But in repositories, we set the id using
        // reflection (to protect the domain). Therefore the id property cannot be
        // readonly.
        ReadOnlyPropertyRector::class => __DIR__ . '/src/App/*/Domain/Aggregate/*',
    ])
;
