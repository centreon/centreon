<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Enum_>
 */
final readonly class EnumAreSuffixedRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Enum_::class;
    }

    /**
     * @param Enum_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $className = (string) $node->namespacedName;
        $reflection = $this->reflectionProvider->getClass($className)->getNativeReflection();

        if (str_ends_with($reflection->getName(), 'Enum')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('The enum %s name must end with "Enum"', $className))
                ->identifier('enum.suffix')
                ->build(),
        ];
    }
}
