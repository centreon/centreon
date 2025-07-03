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

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\ClassConstFetch\RenameClassConstFetchRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
    ->withParallel()
    ->withBootstrapFiles([
        __DIR__ . '/centreon.bootstrap.php',
    ])
    ->withPhpSets()
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withSkip([
        RenameClassConstFetchRector::class,
        CatchExceptionNameMatchingTypeRector::class,
        DisallowedEmptyRuleFixerRector::class,
        EncapsedStringsToSprintfRector::class,
        NewlineAfterStatementRector::class, // conflict with PHP CS Fixer rules
    ]);
