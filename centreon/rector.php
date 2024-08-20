<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/api',
//        __DIR__ . '/config',
//        __DIR__ . '/src',
//        __DIR__ . '/www',
    ]);
    $rectorConfig->sets([LevelSetList::UP_TO_PHP_82]);
};

//return RectorConfig::configure()
//    ->withPaths([
//        __DIR__ . '/api',
//        __DIR__ . '/config',
//        __DIR__ . '/src',
//    ])
//    // uncomment to reach your current PHP version
//    //->withPhpSets(php81: true)
//    ->withRules([
//        AddVoidReturnTypeWhereNoReturnRector::class,
//    ])
//    ->withSets([
//        SymfonySetList::SYMFONY_64,
//        SymfonySetList::SYMFONY_CODE_QUALITY,
//        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
//    ]);