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

use PhpCsFixer\Finder;
use Tools\PhpCsFixer\PhpCsFixerRuleSet;

$config = require_once __DIR__ . '/../tools/php-cs-fixer/config/base.strict.php';

$finder = Finder::create()
    ->in([
        __DIR__ . '/config.new',
        __DIR__ . '/src/Adaptation',
        __DIR__ . '/src/App',
        //        __DIR__ . '/tests/php/Adaptation', // TODO add this folder when the code is ready
        __DIR__ . '/tests/php/App',
    ])
    ->append([
        __DIR__ . '/.php-cs-fixer.new.php',
        __DIR__ . '/.php-cs-fixer.core.php',
        __DIR__ . '/.php-cs-fixer.legacy.src.php',
        __DIR__ . '/castor.php',
        __DIR__ . '/rector.php',
    ]);

$rules = array_merge(
    [
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'concat_space' => ['spacing' => 'one'], // FIXME Why I need to do this? It should be erase by custom rules
    ],
    PhpCsFixerRuleSet::getRules()
);

return $config
    ->setRules($rules)
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.new.cache');
