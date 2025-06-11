<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Fix coding standards')]
function cs(bool $dryRun = false): void
{
    $command = ['vendor/bin/php-cs-fixer', 'fix', '--config', '.php-cs-fixer.new.php'];
    if ($dryRun) {
        $command[] = '--dry-run';
    }

    run($command, context: context()->withEnvironment(['PHP_CS_FIXER_IGNORE_ENV' => true]));
}
