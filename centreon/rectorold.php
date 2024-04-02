<?php

return \Rector\Config\RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src'
    ])
    ->withPreparedSets(deadCode: true);
