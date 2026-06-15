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

use PhpCsFixer\Config;
use Tools\PhpCsFixer\Php82MbStrFunctionsFixer;
use Tools\PhpCsFixer\PhpCsFixerRuleSet;

// CS Fixer 3.76 does not declare PHP 8.4 support. This flag suppresses the version-check abort.
// Pinned at 3.76 to avoid cosmetic side effects introduced in 3.77+ (phpdoc_order phpstan/psalm
// annotation reordering) and to stay backport-compatible with dev-25.10.x.
// setUnsupportedPhpVersionAllowed() exists only on Config (not ConfigInterface), so it must be
// called before any fluent method that narrows the return type to ConfigInterface.
$config = new Config();
$config->setUnsupportedPhpVersionAllowed(true);

return $config
    // @see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/pull/7777
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    // custom rule for backward compatibility with php 8.2
    ->registerCustomFixers([new Php82MbStrFunctionsFixer()])
    ->setRiskyAllowed(true)
    ->setRules(PhpCsFixerRuleSet::getRules());
