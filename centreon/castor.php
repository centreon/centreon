<?php

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
