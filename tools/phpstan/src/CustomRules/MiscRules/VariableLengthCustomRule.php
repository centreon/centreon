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

namespace Tools\PhpStan\CustomRules\MiscRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;

/**
 * This class implements custom rule for PHPStan to check if variable name contains more
 * than 3 characters.
 *
 * @implements Rule<Node>
 */
class VariableLengthCustomRule implements Rule
{
    /**
     * This constant contains an array of variable names to whitelist by custom rule.
     */
    private const EXEMPTION_LIST = [
        'db', // Database
        'ex', // Exception
        'id', // Identifier
        'e', // Exception
        'th', // Throwable
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $varName = $this->getVariableNameFromNode($node);

        // This rule does not apply.
        if (null === $varName || in_array($varName, self::EXEMPTION_LIST, true)) {
            return [];
        }

        // Check rule.
        if (mb_strlen($varName) < 3) {
            return [
                CentreonRuleErrorBuilder::message("(VariableLengthCustomRule) {$varName} must contain 3 or more characters.")->build(),
            ];
        }

        return [];
    }

    /**
     * This method returns variable name from a scanned node if the node refers to
     * variable/property/parameter.
     */
    private function getVariableNameFromNode(Node $node): ?string
    {
        // FIXME Although PHPStan\Node\ClassPropertyNode is covered by backward compatibility promise, this instanceof assumption might break because it's not guaranteed to always stay the same.
        // https://phpstan.org/developing-extensions/backward-compatibility-promise
        if ($node instanceof \PHPStan\Node\ClassPropertyNode) {
            return $node->getName();
        }

        return match (true) {
            $node instanceof Node\Expr\PropertyFetch => is_string($node->name->name ?? null) ? $node->name->name : null,
            $node instanceof Node\Expr\Variable => is_string($node->name) ? $node->name : null,
            $node instanceof Node\Param => is_string($node->var->name ?? null) ? $node->var->name : null,
            default => null
        };
    }
}
