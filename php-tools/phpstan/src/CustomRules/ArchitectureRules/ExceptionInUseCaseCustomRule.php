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
//
// namespace Tools\PhpStan\CustomRules\ArchitectureRules;
//
// use PhpParser\Node;
// use PhpParser\Node\Stmt\Class_;
// use PhpParser\Node\Stmt\ClassMethod;
// use PhpParser\Node\Stmt\Throw_;
// use PhpParser\Node\Stmt\TryCatch;
// use PHPStan\Analyser\Scope;
// use PHPStan\Rules\Rule;
// use PHPStan\Rules\RuleError;
// use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;
// use Tools\PhpStan\CustomRules\CentreonRuleTrait;
//
// /**
// * This class implements a custom rule for PHPStan to check if thrown Exception is in
// * try/catch block and if it is caught.
// *
// * @implements Rule<Node\Stmt\Throw_>
// */
// class ExceptionInUseCaseCustomRule implements Rule
// {
//    use CentreonRuleTrait;
//
//    public function getNodeType(): string
//    {
//        return Throw_::class;
//    }
//
//    public function processNode(Node $node, Scope $scope): array
//    {
//        // Check if file is UseCase
//        if (
//            ! $this->fileIsUseCase($scope->getFile())
//            || $this->getParentClassMethod($node)->name->name === '__construct'
//            || $this->getParentClassMethod($node)->isPrivate() === true
//        ) {
//            return [];
//        }
//
//        // check if Exception class is not null and get string representation of Exception class
//        $exceptionThrown = ($node->expr->class ?? null) ? $node->expr->class->toCodeString() : '';
//        $parentTryCatchNodes = $this->getAllParentTryCatchNodes($node);
//        $caughtExceptionTypes = $this->getCaughtExceptionTypes($parentTryCatchNodes);
//
//        if ($parentTryCatchNodes === []) {
//            return [
//                $this->getCentreonCustomExceptionError(),
//            ];
//        }
//
//        foreach ($caughtExceptionTypes as $caughtExceptionType) {
//            if (is_a($exceptionThrown, $caughtExceptionType, true)) {
//                return [];
//            }
//        }
//
//        return [
//            $this->getCentreonCustomExceptionError(),
//        ];
//    }
//
//    /**
//     * This method returns the parent ClassMethod node.
//     *
//     * @param Throw_ $node
//     *
//     * @return ClassMethod
//     */
//    private function getParentClassMethod(Throw_ $node): ClassMethod
//    {
//        while (! $node->getAttribute('parent') instanceof Class_) {
//            $node = $node->getAttribute('parent');
//        }
//
//        return $node;
//    }
//
//    /**
//     * This method gets all the parent TryCatch nodes of a give node and
//     * stores then in array.
//     *
//     * @param Node $node
//     *
//     * @return TryCatch[]
//     */
//    private function getAllParentTryCatchNodes(Node $node): array
//    {
//        $parentTryCatchNodes = [];
//        while (! $node->getAttribute('parent') instanceof ClassMethod) {
//            if ($node->getAttribute('parent') instanceof TryCatch) {
//                $parentTryCatchNodes[] = $node->getAttribute('parent');
//            }
//            $node = $node->getAttribute('parent');
//        }
//
//        return $parentTryCatchNodes;
//    }
//
//    /**
//     * This method return an array of Exception types caught in all TryCatch nodes.
//     *
//     * @param TryCatch[] $parentTryCatchNodes
//     *
//     * @return string[]
//     */
//    private function getCaughtExceptionTypes(array $parentTryCatchNodes): array
//    {
//        $caughtExceptionTypes = [];
//        foreach ($parentTryCatchNodes as $parentTryCatchNode) {
//            foreach ($parentTryCatchNode->catches as $catch) {
//                foreach ($catch->types as $type) {
//                    $caughtExceptionTypes[] = $type->toCodeString();
//                }
//            }
//        }
//
//        return $caughtExceptionTypes;
//    }
//
//    /**
//     * This method returns Centreon Custom error for Exception Custom Rule.
//     *
//     * @return RuleError
//     */
//    private function getCentreonCustomExceptionError(): RuleError
//    {
//        return CentreonRuleErrorBuilder::message(
//            '(ExceptionInUseCaseCustomRule) Exception thrown in UseCase should be in a try catch block, and must be caught.'
//        )->build();
//    }
// }
