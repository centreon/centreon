<?php

declare(strict_types=1);

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
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php73\Rector\String_\SensitiveHereNowDocRector;
use Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php80\Rector\Property\NestedAnnotationToAttributeRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\Class_\MergeDateTimePropertyTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddParamTypeSplFixedArrayRector;
use Rector\Visibility\Rector\ClassMethod\ExplicitPublicClassMethodRector;

return RectorConfig::configure()
    ->withPaths([
         __DIR__ . '/config',
         __DIR__ . '/src',
         __DIR__ . '/tests',
         __DIR__ . '/widgets',
         __DIR__ . '/www',
         __DIR__ . '/.php-cs-fixer.dist.php',
         __DIR__ . '/.php-cs-fixer.unstrict.php',
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(earlyReturn: true)
    ->withSkip([
        RemoveNullTagValueNodeRector::class,
        RemoveUselessVarTagRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        MixedTypeRector::class,
        MergeDateTimePropertyTypeDeclarationRector::class,
        SensitiveHereNowDocRector::class,
        ReadOnlyClassRector::class,
        ReadOnlyPropertyRector::class,
        ChangeOrIfContinueToMultiContinueRector::class,
        ChangeAndIfToEarlyReturnRector::class,
        RemoveUnusedVariableInCatchRector::class,
        SensitiveConstantNameRector::class,
        RemoveExtraParametersRector::class,
        BinaryOpBetweenNumberAndStringRector::class
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        AddParamTypeSplFixedArrayRector::class,
        NestedAnnotationToAttributeRector::class,
        AttributeKeyToClassConstFetchRector::class,
        ExplicitPublicClassMethodRector::class,
        RemoveAlwaysElseRector::class,
    ]);
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
