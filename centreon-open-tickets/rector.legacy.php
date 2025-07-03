<?php

declare(strict_types=1);

$rectorConfig = require_once __DIR__ . '/../tools/rector/config/base.unstrict.php';

return $rectorConfig
    ->withCache(__DIR__ . '/var/cache/rector.legacy')
    ->withPaths([
        // directories
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/widgets',
        __DIR__ . '/www',
        // files
        __DIR__ . '/.php-cs-fixer.legacy.src.php',
        __DIR__ . '/.php-cs-fixer.legacy.www.php',
        __DIR__ . '/rector.legacy.php',
    ]);
