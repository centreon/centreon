<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;
use Tools\PhpStan\CustomRules\CentreonRuleTrait;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * This class implements a custom rule for PHPStan to check Repository naming requirement.
 * It must match the implemented Interface name except for data storage prefix
 * (in Repository name) and Interface mention (in Interface name).
 *
 * @implements Rule<Node\Stmt\Class_>
 */
class RepositoryNameValidationByInterfaceCustomRule implements Rule
{
    use CentreonRuleTrait;

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // This rule does not apply.
        if (
            ! str_contains($node->name->name ?? '', 'Repository')
            || empty($node->implements)
        ) {
            return [];
        }

        // Rule check.
        // If there's no implementation of RepositoryInterface,
        // it's RepositoryImplementsInterfaceCustomRule that will return an error.
        foreach ($node->implements as $implementation) {
            $repositoryName = $this->getRepositoryName($node->name->name ?? '');
            $interfaceName = $this->getRepositoryInterfaceName($implementation->toString());

            if ($repositoryName && $interfaceName && str_contains($repositoryName, $interfaceName)) {
                return [];
            }
        }

        return [
            CentreonRuleErrorBuilder::message(
                'Repository name should match the implemented Interface name with exception of data storage prefix '
                . "and 'Interface' mention."
            )->tip(
                "For example, Repository name: 'DbReadSessionRepository' and implemented Interface name: "
                . "'ReadSessionRepositoryInterface'."
            )->build(),
        ];
    }
}
