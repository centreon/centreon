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

namespace Tools\PhpStan\CustomRules\RepositoryRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;
use Tools\PhpStan\CustomRules\CentreonRuleTrait;

/**
 * This class implements a custom rule for PHPStan to check Repository naming requirement
 * it must start with data storage prefix, followed by action and context mentions, and finish
 * by 'Repository' mention.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
class RepositoryNameCustomRule implements Rule
{
    use CentreonRuleTrait;

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // This rule does not apply.
        if (
            ! str_contains($node->name->name ?? '', 'Repository')
            || $this->extendsAnException($node->namespacedName?->toCodeString())
        ) {
            return [];
        }

        // Rule check.
        if (! is_null($this->getRepositoryName($node->name->name ?? ''))) {
            return [];
        }

        return [
            CentreonRuleErrorBuilder::message(
                "(RepositoryNameCustomRule) Repository name must start with data storage prefix (i.e. 'Db', 'Redis', etc.), "
                . "which may be followed by 'Read' or 'Write' and context mention."
            )->build(),
        ];
    }
}
