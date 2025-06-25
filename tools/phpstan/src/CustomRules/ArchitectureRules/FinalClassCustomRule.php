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

namespace Tools\PhpStan\CustomRules\ArchitectureRules;

use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;
use Tools\PhpStan\CustomRules\CentreonRuleTrait;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * This class implements a custom rule for PHPStan to check if UseCase, Request, Response
 * or Controller classes are final.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
class FinalClassCustomRule implements Rule
{
    use CentreonRuleTrait;

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // This rule does not apply.
        if ($node->isFinal()) {
            return [];
        }

        $className = $node->name->name ?? '';

        if (
            $this->fileIsUseCase($scope->getFile())
            || str_ends_with($className, 'Request')
            || str_ends_with($className, 'Response')
            || str_ends_with($className, 'Controller')
        ) {
            return [
                CentreonRuleErrorBuilder::message("Class {$className} must be final.")->build(),
            ];
        }

        return [];
    }
}
