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

namespace Tools\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\CustomNode\FileWithoutNamespace;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Unlike Rector's JsonThrowOnErrorRector, also flags calls with existing flags or named arguments,
 * and adds a named flags: argument. Like it, skips a class or function that calls json_last_error(), closures included;
 * a check made in another class, a used trait or a parent class is not detected.
 */
final class JsonThrowOnErrorFlagRector extends AbstractRector
{
    private const THROW_FLAG = 'JSON_THROW_ON_ERROR';

    /** @var array<string, int> position of the flags argument per function */
    private const FLAGS_POSITION = [
        'json_encode' => 1,
        'json_decode' => 3,
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Adds JSON_THROW_ON_ERROR to every json_encode() and json_decode() call', [
            new CodeSample(
                <<<'CODE_SAMPLE'
                    json_encode($content);
                    json_encode($content, JSON_PRETTY_PRINT);
                    json_decode($json, true);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    json_encode($content, flags: JSON_THROW_ON_ERROR);
                    json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                    json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                    CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FileWithoutNamespace::class, Namespace_::class, ClassLike::class, Function_::class];
    }

    /**
     * @param FileWithoutNamespace|Namespace_|ClassLike|Function_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $funcCalls = $this->findScopeFuncCalls($node->stmts);

        // The flag leaves json_last_error() untouched on success, so a check relying on it would read a stale error.
        foreach ($funcCalls as $funcCall) {
            if ($this->isNames($funcCall, ['json_last_error', 'json_last_error_msg'])) {
                return null;
            }
        }

        $hasChanged = false;
        foreach ($funcCalls as $funcCall) {
            $hasChanged = $this->addThrowFlag($funcCall) || $hasChanged;
        }

        return $hasChanged ? $node : null;
    }

    /**
     * @param Node[] $stmts
     *
     * @return FuncCall[] calls of this scope, its methods and closures, nested classes and functions excluded
     */
    private function findScopeFuncCalls(array $stmts): array
    {
        $funcCalls = [];
        $this->traverseNodesWithCallable($stmts, static function (Node $node) use (&$funcCalls): ?int {
            if ($node instanceof ClassLike || $node instanceof Function_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($node instanceof FuncCall) {
                $funcCalls[] = $node;
            }

            return null;
        });

        return $funcCalls;
    }

    private function addThrowFlag(FuncCall $funcCall): bool
    {
        $functionName = $this->getName($funcCall);
        if ($functionName === null) {
            return false;
        }

        $functionName = mb_strtolower($functionName);
        if (! isset(self::FLAGS_POSITION[$functionName])) {
            return false;
        }

        if ($funcCall->isFirstClassCallable() || $funcCall->getArgs() === []) {
            return false;
        }

        foreach ($funcCall->getArgs() as $arg) {
            if ($arg->unpack) {
                return false;
            }
        }

        $flagsArg = $this->findFlagsArg($funcCall, self::FLAGS_POSITION[$functionName]);
        if (! $flagsArg instanceof Arg) {
            $funcCall->args[] = new Arg(
                value: $this->createThrowFlag(),
                name: new Identifier('flags'),
            );

            return true;
        }

        if ($this->containsThrowFlag($flagsArg->value)) {
            return false;
        }

        if ($flagsArg->value instanceof Int_ && $flagsArg->value->value === 0) {
            $flagsArg->value = $this->createThrowFlag();

            return true;
        }

        if ($this->needsParentheses($flagsArg->value)) {
            $flagsArg->value->setAttribute(AttributeKey::WRAPPED_IN_PARENTHESES, true);
        }

        $flagsArg->value = new BitwiseOr($flagsArg->value, $this->createThrowFlag());

        return true;
    }

    private function needsParentheses(Expr $expr): bool
    {
        return $expr instanceof Ternary
            || $expr instanceof Assign
            || $expr instanceof AssignOp
            || ($expr instanceof BinaryOp && ! $expr instanceof BitwiseOr && ! $expr instanceof Coalesce);
    }

    private function findFlagsArg(FuncCall $funcCall, int $position): ?Arg
    {
        foreach ($funcCall->getArgs() as $index => $arg) {
            if ($arg->name instanceof Identifier) {
                if ($arg->name->toString() === 'flags') {
                    return $arg;
                }

                continue;
            }

            if ($index === $position) {
                return $arg;
            }
        }

        return null;
    }

    private function containsThrowFlag(Expr $expr): bool
    {
        return (new NodeFinder())->findFirst(
            $expr,
            static fn (Node $node): bool => $node instanceof ConstFetch
                && mb_strtoupper($node->name->toString()) === self::THROW_FLAG
        ) instanceof Node;
    }

    private function createThrowFlag(): ConstFetch
    {
        return new ConstFetch(new Name(self::THROW_FLAG));
    }
}
