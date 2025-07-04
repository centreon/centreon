<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanAnd\SimplifyEmptyArrayCheckRector;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\For_\ForRepeatedCountToOwnVariableRector;
use Rector\CodeQuality\Rector\Foreach_\ForeachToInArrayRector;
use Rector\CodeQuality\Rector\If_\CompleteMissingIfElseBracketRector;
use Rector\CodeQuality\Rector\If_\ShortenElseIfRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodeQuality\Rector\Ternary\ArrayKeyExistsTernaryThenValueToCoalescingRector;
use Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector;
use Rector\CodingStyle\Rector\ClassConst\RemoveFinalFromConstRector;
use Rector\CodingStyle\Rector\ClassConst\SplitGroupedClassConstantsRector;
use Rector\CodingStyle\Rector\FuncCall\ConsistentImplodeRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\CodingStyle\Rector\Property\SplitGroupedPropertiesRector;
use Rector\CodingStyle\Rector\Ternary\TernaryConditionVariableAssignmentRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php52\Rector\Property\VarToPublicPropertyRector;
use Rector\Php52\Rector\Switch_\ContinueToBreakInSwitchRector;
use Rector\Php53\Rector\FuncCall\DirNameFileConstantToDirConstantRector;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Php53\Rector\Variable\ReplaceHttpServerVarsByServerRector;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php54\Rector\Break_\RemoveZeroBreakContinueRector;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php55\Rector\ClassConstFetch\StaticToSelfOnFinalClassRector;
use Rector\Php55\Rector\FuncCall\GetCalledClassToSelfClassRector;
use Rector\Php55\Rector\FuncCall\GetCalledClassToStaticClassRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php70\Rector\ClassMethod\Php4ConstructorRector;
use Rector\Php70\Rector\FuncCall\CallUserMethodRector;
use Rector\Php70\Rector\FuncCall\EregToPregMatchRector;
use Rector\Php70\Rector\FuncCall\MultiDirnameRector;
use Rector\Php70\Rector\FuncCall\RenameMktimeWithoutArgsToTimeRector;
use Rector\Php70\Rector\MethodCall\ThisCallOnStaticMethodToStaticCallRector;
use Rector\Php70\Rector\StaticCall\StaticCallOnNonStaticToInstanceCallRector;
use Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector;
use Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector;
use Rector\Php71\Rector\Assign\AssignArrayToStringRector;
use Rector\Php71\Rector\BooleanOr\IsIterableRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\Php71\Rector\TryCatch\MultiExceptionCatchRector;
use Rector\Php72\Rector\FuncCall\CreateFunctionToAnonymousFunctionRector;
use Rector\Php72\Rector\FuncCall\GetClassOnNullRector;
use Rector\Php72\Rector\FuncCall\ParseStrWithResultArgumentRector;
use Rector\Php72\Rector\FuncCall\StringifyDefineRector;
use Rector\Php72\Rector\Unset_\UnsetCastRector;
use Rector\Php72\Rector\While_\WhileEachToForeachRector;
use Rector\Php73\Rector\BooleanOr\IsCountableRector;
use Rector\Php73\Rector\FuncCall\ArrayKeyFirstLastRector;
use Rector\Php73\Rector\FuncCall\SetCookieRector;
use Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Php74\Rector\ArrayDimFetch\CurlyToSquareBracketArrayStringRector;
use Rector\Php74\Rector\Assign\NullCoalescingOperatorRector;
use Rector\Php74\Rector\Double\RealToFloatTypeCastRector;
use Rector\Php74\Rector\FuncCall\ArrayKeyExistsOnPropertyRector;
use Rector\Php74\Rector\FuncCall\FilterVarToAddSlashesRector;
use Rector\Php74\Rector\FuncCall\MbStrrposEncodingArgumentPositionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php74\Rector\StaticCall\ExportToReflectionFunctionRector;
use Rector\Php74\Rector\Ternary\ParenthesizeNestedTernaryRector;
use Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector;
use Rector\Php80\Rector\ClassMethod\AddParamBasedOnParentClassMethodRector;
use Rector\Php80\Rector\ClassMethod\FinalPrivateToPrivateVisibilityRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php80\Rector\Identical\StrEndsWithRector;
use Rector\Php80\Rector\Identical\StrStartsWithRector;
use Rector\Php80\Rector\NotIdentical\StrContainsRector;
use Rector\Php80\Rector\Property\NestedAnnotationToAttributeRector;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Php82\Rector\FuncCall\Utf8DecodeEncodeToMbConvertEncodingRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\Transform\Rector\FuncCall\FuncCallToConstFetchRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddParamTypeSplFixedArrayRector;
use Rector\Visibility\Rector\ClassMethod\ExplicitPublicClassMethodRector;

return [
    // ******************* performance (done) *******************
    // OK 70 files / Change count array comparison to empty array comparison to improve performance
    CountArrayToEmptyArrayComparisonRector::class,
    // OK 27 files / Change count() in for function to own variable
    ForRepeatedCountToOwnVariableRector::class,

    // ******************* coding quality (done) *******************
    // OK / Add missing dynamic properties
    CompleteDynamicPropertiesRector::class,
    // OK 215 files / Add return type void to function like without any return
    AddVoidReturnTypeWhereNoReturnRector::class,
    // OK 114 files / Add closure return type void if there is no return
    AddClosureVoidReturnTypeWhereNoReturnRector::class,
    // KO 0 files / Add exact fixed array type in known cases
    AddParamTypeSplFixedArrayRector::class,
    // KO 0 files  / Changed nested annotations to attributes
    NestedAnnotationToAttributeRector::class,
    // KO 0 files  / Replace key value on specific attribute to class constant
    AttributeKeyToClassConstFetchRector::class,
    // OK 1 file / Change property modifier from var to public
    VarToPublicPropertyRector::class,
    // KO class rules not exists / Remove unused parameter in public method on final class without extends and interface
    RemoveUnusedPublicMethodParameterRector::class,
    // OK 1 file / Simplify is_array and empty functions combination into a simple identical check for an empty array
    SimplifyEmptyArrayCheckRector::class,
    // OK 131 files / Simplify empty() functions calls on empty arrays
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    // OK 1 file // Change array_key_exists() ternary to coalescing
    ArrayKeyExistsTernaryThenValueToCoalescingRector::class,
    // OK 5 files / Inline just in time array dim fetch assigns to direct return
    InlineArrayReturnAssignRector::class,
    // OK 48 files / Move property default from constructor to property default
    InlineConstructorDefaultToPropertyRector::class,
    // Simplify foreach loops into in_array when possible
    ForeachToInArrayRector::class,
    // OK 154 files / Changes if/else for same value as assign to ternary
    SimplifyIfElseToTernaryRector::class,
    // OK 47 files / Shortens else/if to elseif
    ShortenElseIfRector::class,
    // KO / Complete missing if/else brackets
    CompleteMissingIfElseBracketRector::class,

    // ******************* coding style (done) *******************
    // OK 1 file / Add explicit public method visibility
    ExplicitPublicClassMethodRector::class,
    // KO 0 files / Changes use of function calls to use constants
    FuncCallToConstFetchRector::class,
    // KO 0 files / Remove final from constants in classes defined as final
    RemoveFinalFromConstRector::class,
    // KO 0 files / Separate grouped properties to own lines
    SplitGroupedPropertiesRector::class,
    // OK 63 files / Separate class constant to own lines
    SplitGroupedClassConstantsRector::class,
    // OK 34 files / Split multiple inline assigns to each own lines default value, to prevent undefined array issues
    SplitDoubleAssignRector::class,
    // OK 102 files / Assign outcome of ternary condition to variable, where applicable
    TernaryConditionVariableAssignmentRector::class,

    // ******************* PHP *******************
    // --------------------to do to migrate(done)--------------
    // OK 78 files / Convert dirname(__FILE__) to __DIR__ (5.3)
    DirNameFileConstantToDirConstantRector::class,
    // KO 0 files / Rename old $HTTP_* variable names to new replacements (5.3)
    ReplaceHttpServerVarsByServerRector::class,
    // OK 20 files / Use ?: instead of ?, where useful (5.3)
    TernaryToElvisRector::class,
    // KO 0 files / Remove 0 from break and continue (5.4)
    RemoveZeroBreakContinueRector::class,
    // OK 612 files / Long array to short array (5.4)
    LongArrayToShortArrayRector::class,
    // KO 0 files Change get_called_class() to self::class on final class (5.5)
    GetCalledClassToSelfClassRector::class,
    // OK 6 files / Change get_called_class() to static::class on non-final class (5.5)
    GetCalledClassToStaticClassRector::class,
    // KO 0 files / Change static::class to self::class on final class (5.5)
    StaticToSelfOnFinalClassRector::class,
    // KO 0 files / Replace string class names by ::class constant (5.5)
    StringClassNameToClassConstantRector::class,
    // 3 files / Change __CLASS__ to self::class (5.5)
    ClassConstantToSelfClassRector::class,
    // OK 2 files / Changes $this->call() to static method to static call (7.0)
    ThisCallOnStaticMethodToStaticCallRector::class,
    // KO 0 files / Changes ereg*() to preg*() calls (7.0)
    EregToPregMatchRector::class,
    // KO 0 files / Changes call_user_method()/call_user_method_array() to call_user_func()/call_user_func_array() (7.0)
    CallUserMethodRector::class,
    // KO 0 files /  Renames mktime() without arguments to time() (7.0)
    RenameMktimeWithoutArgsToTimeRector::class,
    // KO 0 files /  Changes multiple dirname() calls to one with nesting level (7.0)
    MultiDirnameRector::class,
    // OK 1 files /  Changes static call to instance call, where not useful (7.0)
    StaticCallOnNonStaticToInstanceCallRector::class,
    // OK 23 files / Change if with isset and return to coalesce (7.0)
    IfIssetToCoalescingRector::class,
    // OK 120 files / Changes unneeded null check to ?? operator (7.0)
    TernaryToNullCoalescingRector::class,
    // KO 0 files /  Changes PHP 4 style constructor to __construct (7.0)
    Php4ConstructorRector::class,
    // KO 0 files / String cannot be turned into array by assignment anymore (7.1)
    AssignArrayToStringRector::class,
    // OK 4 files / Changes multi catch of same exception to single one | separated. (7.1)
    MultiExceptionCatchRector::class,
    // KO 0 files / Changes is_array + Traversable check to is_iterable (7.1)
    IsIterableRector::class,
    // OK 41 files / Change list() to array destruct (7.1)
    ListToArrayDestructRector::class,
    // KO 0 files / each() function is deprecated, use foreach() instead. (7.2)
    WhileEachToForeachRector::class,
    // KO 0 files / Make first argument of define() string (7.2)
    StringifyDefineRector::class,
    // Use $result argument in parse_str() function (7.2)
    ParseStrWithResultArgumentRector::class,
    // KO 0 files / Use anonymous functions instead of deprecated create_function() (7.2)
    CreateFunctionToAnonymousFunctionRector::class,
    // KO 0 files / Null is no more allowed in get_class() (7.2)
    GetClassOnNullRector::class,
    // KO 0 files /  Removes (unset) cast (7.2)
    UnsetCastRector::class,
    // Makes needles explicit strings (7.3)
    StringifyStrNeedlesRector::class,
    // Convert setcookie argument to PHP7.3 option array (7.3)
    SetCookieRector::class,
    // Make use of array_key_first() and array_key_last() (7.3)
    ArrayKeyFirstLastRector::class,
    // Changes is_array + Countable check to is_countable (7.3)
    IsCountableRector::class,
    // Use break instead of continue in switch statements (7.3)
    ContinueToBreakInSwitchRector::class,
    // KO 0 files / Change filter_var() with slash escaping to addslashes() (7.4)
    FilterVarToAddSlashesRector::class,
    // KO 0 files / Change mb_strrpos() encoding argument position (7.4)
    MbStrrposEncodingArgumentPositionRector::class,
    // KO 0 files / Change array_key_exists() on property to property_exists() (7.4)
    ArrayKeyExistsOnPropertyRector::class,
    // KO 0 files / Change deprecated (real) to (float) (7.4)
    RealToFloatTypeCastRector::class,
    // KO 0 files / Change export() to ReflectionFunction alternatives (7.4)
    ExportToReflectionFunctionRector::class,
    // KO 26 files / Add null default to properties with PHP 7.4 property nullable type(7.4)
    RestoreDefaultNullToNullableTypePropertyRector::class,
    // OK 17 files / Use null coalescing operator ??= (7.4)
    NullCoalescingOperatorRector::class,
    // KO 0 files / Add parentheses to nested ternary (7.4)
    ParenthesizeNestedTernaryRector::class,
    // KO 0 files / Change curly based array and string to square bracket (7.4)
    CurlyToSquareBracketArrayStringRector::class,
    // OK 1 file Changes various implode forms to consistent one (8.0)
    ConsistentImplodeRector::class,
    // KO 1 file but not correct / Remove unused parent call with no parent class (8.0)
    RemoveParentCallWithoutParentRector::class,
    // OK 6 files / Change get_class($object) to faster $object::class (8.0)
    ClassOnObjectRector::class,
    // OK 0 files / Change $this::class to static::class or self::class depends on class modifier (8.0)
    ClassOnThisVariableObjectRector::class,
    // OK 12 files / Change helper functions to str_ends_with() (8.0)
    StrEndsWithRector::class,
    // OK 15 files / Change helper functions to str_starts_with() (8.0)
    StrStartsWithRector::class,
    // OK 33 files / Replace strpos() !== false and strstr() with str_contains() (8.0)
    StrContainsRector::class,
    // KO 0 file / Changes method visibility from final private to only private (8.0)
    FinalPrivateToPrivateVisibilityRector::class,
    // KO 0 files / Add missing parameter based on parent class method (8.0)
    AddParamBasedOnParentClassMethodRector::class,
    // OK 7 files / Change deprecated utf8_decode and utf8_encode to mb_convert_encoding (8.2)
    Utf8DecodeEncodeToMbConvertEncodingRector::class,
    // OK 0 files / Replace deprecated "${var}" to "{$var}" (8.2)
    VariableInStringInterpolationFixerRector::class,
];

// ================================= RULES TO DISCUSS OR NOT DONE =================================

// // --------------- to do later (not done) -----------------
// ClosureToArrowFunctionRector::class, // OK 111 files / Change closure to arrow function (7.4)
// ChangeSwitchToMatchRector::class, // Change switch() to match() (8.0)
// StringableForToStringRector::class, // Add Stringable interface to classes with __toString() method (8.0)
// StringableForToStringRector::class, // Add Stringable interface to classes with __toString() method (8.0)
// ClassPropertyAssignToConstructorPromotionRector::class, // Change simple property init and assign to constructor promotion (8.0)
// MyCLabsMethodCallToEnumConstRector::class, // Refactor MyCLabs enum fetch to Enum const (8.1)
// SpatieEnumMethodCallToEnumConstRector::class, // Refactor Spatie enum method calls (8.1)
// NullToStrictStringFuncCallArgRector::class, // Change null to strict string defined function call args (8.1)
// NullToStrictStringFuncCallArgRector::class, // Change null to strict string defined function call args (8.1)

// // ******************* NOT TO USE *******************
// TernaryFalseExpressionToIfRector::class, // OK 26 files /Change ternary with false to if and explicit call
// RemoveExtraParametersRector::class, // OK 48 files / Remove extra parameters (7.1)

// // ******************* TO DISCUSS (not done) *******************
// // ---------------- PHP not sure ----------------
// SensitiveHereNowDocRector::class, Changes heredoc/nowdoc that contains closing word to safe wrapper name (7.3)
// SensitiveConstantNameRector::class, Changes case insensitive constants to sensitive ones.(7.3)
// ReadOnlyPropertyRector::class, // Decorate read-only property with readonly attribute (8.1)
// NewInInitializerRector::class, // Replace property declaration of new state with direct new (8.1)
// ReadOnlyClassRector::class, // Decorate read-only class with readonly attribute (8.1)
// BoolReturnTypeFromBooleanConstReturnsRector::class, // Add return bool, based on direct true/false returns
// RemoveAlwaysElseRector::class, // Split if statement, when if condition always break execution flow
// RenameVariableToMatchNewTypeRector::class, // Rename variable to match new ClassType
// RenameParamToMatchTypeRector::class, // Rename param to match ClassType
// RenamePropertyToMatchTypeRector::class, // Rename property and method param to match its type
// RenameVariableToMatchMethodCallReturnTypeRector::class, // Rename variable to match method return type
// ExceptionHandlerTypehintRector::class, // Change typehint from Exception to Throwable. (7.0)
// // ---------------- strange ----------------
// NullableCompareToNullRector::class, // Changes negate of empty comparison of nullable value to explicit === or !== compare
// -        if ($user = $this->security->getUser()) {
// +        if (($user = $this->security->getUser()) !== null) {
// MakeInheritedMethodVisibilitySameAsParentRector::class, // Make method visibility same as parent one ==> only for test classes
// // ---------------- deprecated ?? ----------------
// WrapEncapsedVariableInCurlyBracesRector::class, // 152 files Wrap encapsed variables in curly braces
