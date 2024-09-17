<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveNullTagValueNodeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\EarlyReturn\Rector\If_\ChangeAndIfToEarlyReturnRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\Php71\Rector\BinaryOp\BinaryOpBetweenNumberAndStringRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php72\Rector\FuncCall\CreateFunctionToAnonymousFunctionRector;
use Rector\Php72\Rector\While_\WhileEachToForeachRector;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php73\Rector\String_\SensitiveHereNowDocRector;
use Rector\Php74\Rector\Double\RealToFloatTypeCastRector;
use Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php80\Rector\Property\NestedAnnotationToAttributeRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Php82\Rector\FuncCall\Utf8DecodeEncodeToMbConvertEncodingRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\Class_\MergeDateTimePropertyTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddParamTypeSplFixedArrayRector;
use Rector\Visibility\Rector\ClassMethod\ExplicitPublicClassMethodRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/api',
        __DIR__ . '/config',
        __DIR__ . '/cron',
        __DIR__ . '/lib',
        __DIR__ . '/libinstall',
        __DIR__ . '/packaging',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
        __DIR__ . '/www',
        __DIR__ . '/.env.local.php',
        __DIR__ . '/.php-cs-fixer.dist.php',
        __DIR__ . '/.php-cs-fixer.unstrict.php',
        __DIR__ . '/rector.php',
        __DIR__ . '/bootstrap.php',
        __DIR__ . '/container.php',
        // -------------- centreon-awie --------------
        __DIR__ . '/../centreon-awie/features',
        __DIR__ . '/../centreon-awie/www',
        // -------------- centreon-dsm --------------
        __DIR__ . '/../centreon-dsm/www',
        // -------------- centreon-open-tickets --------------
        __DIR__ . '/../centreon-open-tickets/config',
        __DIR__ . '/../centreon-open-tickets/src',
        __DIR__ . '/../centreon-open-tickets/tests',
        __DIR__ . '/../centreon-open-tickets/widgets',
        __DIR__ . '/../centreon-open-tickets/www',
        __DIR__ . '/../centreon-open-tickets/.php-cs-fixer.unstrict.php',
        __DIR__ . '/../centreon-open-tickets/.php-cs-fixer.dist.php',
    ])->withRules([
//        // ******************* performance (done) *******************
//        CountArrayToEmptyArrayComparisonRector::class, // OK 70 files / Change count array comparison to empty array comparison to improve performance
//        ForRepeatedCountToOwnVariableRector::class, // OK 27 files / Change count() in for function to own variable
//
//        //******************* to do to fix deprecated (done)  *******************
//        CompleteDynamicPropertiesRector::class, // OK / Add missing dynamic properties
//        CreateFunctionToAnonymousFunctionRector::class, // KO 0 files / Use anonymous functions instead of deprecated create_function() (7.2)
//        WhileEachToForeachRector::class, // KO 0 files / each() function is deprecated, use foreach() instead. (7.2)
//        RealToFloatTypeCastRector::class, // KO 0 files / Change deprecated (real) to (float) (7.4)
//        Utf8DecodeEncodeToMbConvertEncodingRector::class, // OK 7 files / Change deprecated utf8_decode and utf8_encode to mb_convert_encoding (8.2)
//        VariableInStringInterpolationFixerRector::class, // OK 0 files / Replace deprecated "${var}" to "{$var}" (8.2)
    ]);

// return RectorConfig::configure()
//    ->withPaths([
//         __DIR__ . '/api',
//         __DIR__ . '/config',
//         __DIR__ . '/cron',
//         __DIR__ . '/lib',
//         __DIR__ . '/libinstall',
//         __DIR__ . '/packaging',
//         __DIR__ . '/src',
//         __DIR__ . '/tests',
//         __DIR__ . '/tools',
//         __DIR__ . '/www',
//         __DIR__ . '/.env.local.php',
//         __DIR__ . '/.php-cs-fixer.dist.php',
//         __DIR__ . '/.php-cs-fixer.unstrict.php',
//         __DIR__ . '/rector.php',
//         __DIR__ . '/bootstrap.php',
//         __DIR__ . '/container.php',
//    ])
//    ->withPhpSets(php82: true)
//    ->withPreparedSets(earlyReturn: true)
//    ->withSkip([
//        RemoveNullTagValueNodeRector::class,
//        RemoveUselessVarTagRector::class,
//        RemoveUselessParamTagRector::class,
//        RemoveUselessReturnTagRector::class,
//        MixedTypeRector::class,
//        MergeDateTimePropertyTypeDeclarationRector::class,
//        SensitiveHereNowDocRector::class,
//        ReadOnlyClassRector::class,
//        ReadOnlyPropertyRector::class,
//        ChangeOrIfContinueToMultiContinueRector::class,
//        ChangeAndIfToEarlyReturnRector::class,
//        RemoveUnusedVariableInCatchRector::class,
//        SensitiveConstantNameRector::class,
//        RemoveExtraParametersRector::class,
//        BinaryOpBetweenNumberAndStringRector::class
//    ])
//    ->withRules([
//        AddVoidReturnTypeWhereNoReturnRector::class,
//        AddClosureVoidReturnTypeWhereNoReturnRector::class,
//        AddParamTypeSplFixedArrayRector::class,
//        NestedAnnotationToAttributeRector::class,
//        AttributeKeyToClassConstFetchRector::class,
//        ExplicitPublicClassMethodRector::class,
//        RemoveAlwaysElseRector::class,
//        CompleteDynamicPropertiesRector::class
//    ]);
//    ->withSets([
//        SymfonySetList::SYMFONY_64,
//        SymfonySetList::SYMFONY_CODE_QUALITY,
//        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
//    ]);

// @see : https://github.com/rectorphp/rector/blob/main/docs/rector_rules_overview.md
// @see : https://getrector.com/find-rule

// to see
// ClassPropertyAssignToConstructorPromotionRector
// ClassOnObjectRector
