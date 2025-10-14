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
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class MarkedClassesHaveTestRule implements Rule
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
        $reflection = $this->reflectionProvider->getClass((string) $node->namespacedName);

        if (! $this->isEligible($reflection)) {
            return [];
        }

        $parts = explode('src', $scope->getFile());

        $projectPath = trim($parts[0], '/');
        $pathFromProject = trim($parts[1], '/');

        $classUnderTestPath = \sprintf('/%s/tests/php/%s', $projectPath, $pathFromProject);
        $classUnderTestPath = str_replace(
            ['.php'],
            ['Test.php'],
            $classUnderTestPath,
        );

        $pathFromTest = \sprintf(
            'tests/%s',
            ltrim(explode('tests', $classUnderTestPath)[1], '/'),
        );

        if (file_exists($classUnderTestPath)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('The test class %s is missing', $pathFromTest))
                ->identifier('test.class')
                ->build(),
        ];
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
