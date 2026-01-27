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

namespace Tools\PhpStan\CustomRules\MiscRules;

use App\Shared\Application\Command\AsCommandHandler;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class CommandHandlerCannotUseCommandBus implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
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
        $reflection = $this->reflectionProvider->getClass((string) $node->namespacedName)->getNativeReflection();

        if (! $reflection->getAttributes(AsCommandHandler::class)) {
            return [];
        }

        if (! $constructor = $node->getMethod('__construct')) {
            return [];
        }

        foreach ($constructor->getParams() as $param) {
            if (($param->type instanceof Identifier || $param->type instanceof Name) && $param->type->toString() === 'App\Shared\Application\Command\CommandBus') {
                return [
                    RuleErrorBuilder::message('A command handler class cannot use command bus.')
                        ->identifier('command.handler.bus')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
