<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Configuration\Option;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/api',
        __DIR__ . '/config',
        __DIR__ . '/src',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withSets([
        SymfonySetList::SYMFONY_54,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ]);
