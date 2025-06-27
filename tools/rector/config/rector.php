<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

$rectorRules = require_once __DIR__ . '/rector.rules.php';

return RectorConfig::configure()
    ->withParallel()
    ->withRules($rectorRules)
    ->withBootstrapFiles([
        __DIR__ . '/rector.bootstrap.php',
    ]);
