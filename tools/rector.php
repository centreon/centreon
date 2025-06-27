<?php

declare(strict_types=1);

$rectorConfig = require_once __DIR__ . '/rector/config/rector.php';

return $rectorConfig
    ->withCache(__DIR__ . '/var/cache/rector')
    ->withPaths([
        // directories
        __DIR__ . '/php-cs-fixer',
        __DIR__ . '/phpstan',
        __DIR__ . '/rector',
        // files
        __DIR__ . '/.php-cs-fixer.tools.php',
        __DIR__ . '/rector.php',
    ]);

