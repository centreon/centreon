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

declare(strict_types=1);

use PhpCsFixer\Finder;

$config = require_once __DIR__ . '/../tools/php-cs-fixer/config/base.unstrict.php';

$finder = Finder::create()
    ->in([
//        __DIR__ . '/src/Centreon', // TODO add this folder when the code is ready
        __DIR__ . '/src/CentreonCommand',
        __DIR__ . '/src/CentreonLegacy',
        __DIR__ . '/src/CentreonModule',
        __DIR__ . '/src/CentreonNotification',
        __DIR__ . '/src/CentreonRemote',
        __DIR__ . '/src/CentreonUser',
        __DIR__ . '/src/EventSubscriber',
        __DIR__ . '/src/Security',
        __DIR__ . '/src/Utility',
//        __DIR__ . '/www', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/Centreon', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/CentreonLegacy', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/CentreonRemote', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/Security', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/Utility', // TODO add this folder when the code is ready
//        __DIR__ . '/tests/php/www', // TODO add this folder when the code is ready
    ]);

return $config
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.legacy.src.cache');
