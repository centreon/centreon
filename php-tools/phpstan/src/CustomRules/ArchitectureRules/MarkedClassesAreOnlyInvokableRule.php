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

namespace Tools\PhpStan\CustomRules\ArchitectureRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class MarkedClassesAreOnlyInvokableRule implements Rule
{
    /**
     * @param list<class-string> $attributes
     * @param list<class-string> $classes
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $attributes = [],
        private array $classes = [],
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $className = (string) $node->namespacedName;
        $reflection = $this->reflectionProvider->getClass($className);

        if (! $this->isEligible($reflection)) {
            return [];
        }

        $errors = [];

        $hasInvokableMethod = (bool) array_filter(
            $node->getMethods(),
            static fn (ClassMethod $method): bool => $method->isPublic() && (string) $method->name !== '__invoke',
        );

        if (! $hasInvokableMethod) {
            $errors[] = RuleErrorBuilder::message(\sprintf('The class %s must have "__invoke" method', $className))
                ->identifier('invokable.missing')
                ->build();
        }

        $extraPublicMethodsCount = \count(
            array_values(
                array_filter(
                    $node->getMethods(),
                    static fn (ClassMethod $method): bool => $method->isPublic() && ((string) $method->name !== '__construct' && (string) $method->name !== '__invoke'),
                ),
            ),
        );

        if ($extraPublicMethodsCount !== 0) {
            $errors[] = RuleErrorBuilder::message(\sprintf('The class %s has public methods other than "__construct" and "__invoke"', $className))
                ->identifier('invokable.extra')
                ->build();
        }

        return $errors;
    }

    private function isEligible(ClassReflection $reflection): bool
    {
        foreach ($this->attributes as $attribute) {
            if ($reflection->getNativeReflection()->getAttributes($attribute)) {
                return true;
            }
        }

        foreach ($this->classes as $class) {
            if (! $reflection->isAbstract() && ($reflection->getName() === $class || $reflection->isSubclassOfClass($this->reflectionProvider->getClass($class)))) {
                return true;
            }
        }

        return false;
    }
}
