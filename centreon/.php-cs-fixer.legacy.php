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

use PhpCsFixer\Finder;

$csFixerConfig = require_once __DIR__ . '/../php-tools/php-cs-fixer/config/base.unstrict.php';
$pathsConfig = require_once __DIR__ . '/.php-cs-fixer.conf.php';

$finder = Finder::create()
    ->in($pathsConfig['legacy']['directories'])
    ->append($pathsConfig['legacy']['files'])
    ->notPath($pathsConfig['legacy']['skip']);

return $csFixerConfig
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.legacy.cache');
