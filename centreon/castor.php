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

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Fix coding style')]
function cs(bool $dryRun = false): void
{
    $command = ['vendor/bin/php-cs-fixer', 'fix', '--config', '.php-cs-fixer.new.php'];
    if ($dryRun) {
        $command[] = '--dry-run';
    }

    run($command, context: context()->withEnvironment(['PHP_CS_FIXER_IGNORE_ENV' => true]));
}

#[AsTask(description: 'Analyze code')]
function stan(): void
{
    $command = ['vendor/bin/phpstan', 'analyze', '--configuration', 'phpstan.new.neon'];

    run($command, context: context()->withEnvironment(['XDEBUG_MODE' => 'off']));
}

#[AsTask(description: 'Run dependency analysis')]
function dep(): void
{
    run(['vendor/bin/deptrac', 'analyze', '--config-file', 'deptrac_bc.yaml', '--cache-file', '.deptrac_bc.cache', '--fail-on-uncovered', '--report-uncovered']);
    run(['vendor/bin/deptrac', 'analyze', '--config-file', 'deptrac_hexa.yaml', '--cache-file', '.deptrac_hexa.cache', '--fail-on-uncovered', '--report-uncovered']);
}

#[AsTask(description: 'Test the application')]
function test(?string $filter = null, ?string $group = null): void
{
    $command = ['vendor/bin/simple-phpunit', '--configuration', 'phpunit.new.xml'];

    if ($filter) {
        $command = [...$command, '--filter', $filter];
    }

    if ($group) {
        $command = [...$command, '--group', $group];
    }

    run($command);
}

#[AsTask(description: 'Run CI locally')]
function ci(): void
{
    cs(dryRun: true);
    stan();
    dep();
    test();
}

#[AsTask(description: 'Run a Symfony Console command')]
function console(#[AsRawTokens] array $args = []): void
{
    run(['bin/console.new', ...$args]);
}
