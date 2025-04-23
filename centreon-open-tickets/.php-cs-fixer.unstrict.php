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

use Centreon\PhpCsFixer\PhpCsFixerRuleSet;
use PhpCsFixer\{Config, Finder};

$finder = Finder::create()
    ->in([
        __DIR__ . '/src/CentreonOpenTickets',
    ]);

/**
 * These rules have various risky rune like 'declare_strict_types' which may be dangerous on legacy code.
 * 👉️ We use the other php-cs-fixer config file for this legacy code.
 *
 * @see .php-cs-fixer.dist.php
 */
return (new Config())
    ->setFinder($finder)
    ->setRiskyAllowed(false) // 👈 risky NOT allowed
    ->setUsingCache(false)
    ->setRules(PhpCsFixerRuleSet::getRulesSafe());
