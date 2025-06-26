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
use Tools\PhpCsFixer\PhpCsFixerRuleSet;

$config = require_once __DIR__ . '/../tools/php-cs-fixer/config/base.strict.php';

$finder = Finder::create()
    ->in([
        __DIR__ . '/php-cs-fixer/src/',
        __DIR__ . '/phpstan/src/',
    ])
    ->append([
        __DIR__ . '/.php-cs-fixer.tools.php',
    ]);

return $config
    ->setRules(array_merge(
        PhpCsFixerRuleSet::getRules(),
        [
            'psr_autoloading' => false, // This rule is not compatible with tools directory architecture
        ]
    ))
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.tools.cache');
