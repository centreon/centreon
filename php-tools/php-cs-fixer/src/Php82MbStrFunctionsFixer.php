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

namespace Tools\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Analyzer\ArgumentsAnalyzer;
use PhpCsFixer\Tokenizer\Analyzer\FunctionsAnalyzer;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Replacement for MbStrFunctionsFixer capped at the PHP 8.2 function set.
 *
 * The upstream fixer expands its replacement map at runtime based on PHP_VERSION_ID,
 * adding str_pad (8.3+) and trim/ltrim/rtrim (8.4+). Running it on PHP 8.4 would
 * rewrite those functions in code that targets PHP 8.2, breaking backport branches.
 * This fixer keeps exactly the functions available since PHP 8.2.
 */
final class Php82MbStrFunctionsFixer extends AbstractFixer
{
    /** @var array<string, array{alternativeName: string, argumentCount: list<int>}> */
    private const FUNCTIONS_MAP = [
        'str_split' => ['alternativeName' => 'mb_str_split', 'argumentCount' => [1, 2, 3]],
        'stripos' => ['alternativeName' => 'mb_stripos', 'argumentCount' => [2, 3]],
        'stristr' => ['alternativeName' => 'mb_stristr', 'argumentCount' => [2, 3]],
        'strlen' => ['alternativeName' => 'mb_strlen', 'argumentCount' => [1]],
        'strpos' => ['alternativeName' => 'mb_strpos', 'argumentCount' => [2, 3]],
        'strrchr' => ['alternativeName' => 'mb_strrchr', 'argumentCount' => [2]],
        'strripos' => ['alternativeName' => 'mb_strripos', 'argumentCount' => [2, 3]],
        'strrpos' => ['alternativeName' => 'mb_strrpos', 'argumentCount' => [2, 3]],
        'strstr' => ['alternativeName' => 'mb_strstr', 'argumentCount' => [2, 3]],
        'strtolower' => ['alternativeName' => 'mb_strtolower', 'argumentCount' => [1]],
        'strtoupper' => ['alternativeName' => 'mb_strtoupper', 'argumentCount' => [1]],
        'substr' => ['alternativeName' => 'mb_substr', 'argumentCount' => [2, 3]],
        'substr_count' => ['alternativeName' => 'mb_substr_count', 'argumentCount' => [2, 3, 4]],
    ];

    public function getName(): string
    {
        return 'Centreon/mb_str_functions_php82';
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function isRisky(): bool
    {
        return true;
    }

    /**
     * Must run before NativeFunctionInvocationFixer.
     */
    public function getPriority(): int
    {
        return 2;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Replace non multibyte-safe functions with corresponding mb function (PHP 8.2 baseline only).',
            [
                new CodeSample(
                    '<?php
$a = strlen($a);
$a = strpos($a, $b);
$a = strtolower($a);
'
                ),
            ],
            null,
            'Risky when any of the functions are overridden, or when relying on the string byte size rather than its length in characters.'
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        $argumentsAnalyzer = new ArgumentsAnalyzer();
        $functionsAnalyzer = new FunctionsAnalyzer();

        for ($index = $tokens->count() - 1; $index > 0; --$index) {
            if (! $tokens[$index]->isGivenKind(T_STRING)) {
                continue;
            }

            $lowercasedContent = mb_strtolower($tokens[$index]->getContent());
            if (! isset(self::FUNCTIONS_MAP[$lowercasedContent])) {
                continue;
            }

            if ($functionsAnalyzer->isGlobalFunctionCall($tokens, $index)) {
                $openParenthesis = $tokens->getNextMeaningfulToken($index);
                if ($openParenthesis === null) {
                    continue;
                }
                $closeParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesis);
                $numberOfArguments = $argumentsAnalyzer->countArguments($tokens, $openParenthesis, $closeParenthesis);
                if (! \in_array($numberOfArguments, self::FUNCTIONS_MAP[$lowercasedContent]['argumentCount'], true)) {
                    continue;
                }
                $tokens[$index] = new Token([T_STRING, self::FUNCTIONS_MAP[$lowercasedContent]['alternativeName']]);

                continue;
            }

            // global function import (use function strlen;)
            $functionIndex = $tokens->getPrevMeaningfulToken($index);
            if ($functionIndex === null) {
                continue;
            }
            if ($tokens[$functionIndex]->isGivenKind(T_NS_SEPARATOR)) {
                $functionIndex = $tokens->getPrevMeaningfulToken($functionIndex);
                if ($functionIndex === null) {
                    continue;
                }
            }
            if (! $tokens[$functionIndex]->isGivenKind(CT::T_FUNCTION_IMPORT)) {
                continue;
            }
            $useIndex = $tokens->getPrevMeaningfulToken($functionIndex);
            if ($useIndex === null) {
                continue;
            }
            if (! $tokens[$useIndex]->isGivenKind(T_USE)) {
                continue;
            }
            $tokens[$index] = new Token([T_STRING, self::FUNCTIONS_MAP[$lowercasedContent]['alternativeName']]);
        }
    }
}
