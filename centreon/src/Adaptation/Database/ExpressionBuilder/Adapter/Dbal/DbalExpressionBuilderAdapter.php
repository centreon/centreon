<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Adaptation\Database\ExpressionBuilder\Adapter\Dbal;

use Adaptation\Database\Connection\Adapter\Dbal\DbalConnectionAdapter;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\ExpressionBuilder\Enum\ComparisonOperatorEnum;
use Adaptation\Database\ExpressionBuilder\Exception\ExpressionBuilderException;
use Adaptation\Database\ExpressionBuilder\ExpressionBuilderInterface;
use Centreon\Domain\Log\Logger;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder as DoctrineDbalExpressionBuilder;

/**
 * Class.
 *
 * @class   ExpressionBuilder
 *
 * @see     DoctrineDbalExpressionBuilder
 *
 * To dynamically create SQL query parts.
 */
final readonly class DbalExpressionBuilderAdapter implements ExpressionBuilderInterface
{
    /**
     * DbalExpressionBuilderAdapter constructor.
     *
     * @param DoctrineDbalExpressionBuilder $dbalExpressionBuilder
     */
    public function __construct(private DoctrineDbalExpressionBuilder $dbalExpressionBuilder) {}

    /**
     * Factory.
     *
     * Creates an expression builder for the connection.
     *
     * We have to use a connection configuration to instantiate the query builder because the query builder needs a
     * connection to work.
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ExpressionBuilderException
     *
     * @return DbalExpressionBuilderAdapter
     */
    public static function createFromConnectionConfig(ConnectionConfig $connectionConfig): ExpressionBuilderInterface
    {
        try {
            $connection = DbalConnectionAdapter::createFromConfig($connectionConfig);

            return new self(new DoctrineDbalExpressionBuilder($connection->getDbalConnection()));
        } catch (ConnectionException $exception) {
            Logger::create()->error(
                'An error occurred while trying to create an instance of expression builder',
                ['exception' => $exception->getContext()]
            );

            throw ExpressionBuilderException::createFromConnectionConfigFailed($exception);
        }

    }

    /**
     * Creates a conjunction of the given expressions.
     *
     * @param string $expression
     * @param string ...$expressions
     *
     * @return string
     *
     * @example
     *         method : and("field1 = :value1", ["field2 = :value2","field3 = :value3"])
     *         return : "(field1 = :value1) AND (field2 = :value2) AND (field3 = :value3)"
     */
    public function and(string $expression, string ...$expressions): string
    {
        return $this->dbalExpressionBuilder->and($expression, ...$expressions)->__toString();
    }

    /**
     * Creates a disjunction of the given expressions.
     *
     * @param string $expression
     * @param string ...$expressions
     *
     * @return string
     *
     * @example
     *         method : or("field1 = :value1", ["field2 = :value2","field3 = :value3"])
     *         return : "(field1 = :value1) OR (field2 = :value2) OR (field3 = :value3)"
     */
    public function or(string $expression, string ...$expressions): string
    {
        return $this->dbalExpressionBuilder->or($expression, ...$expressions)->__toString();
    }

    /**
     * Creates a comparison expression.
     *
     * @param string $leftExpression the left expression
     * @param ComparisonOperatorEnum $operator the comparison operator
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *          method : comparison('field1', '=', ':value1')
     *          return : "field1 = :value1"
     */
    public function comparison(
        string $leftExpression,
        ComparisonOperatorEnum $operator,
        string $rightExpression
    ): string {
        return $this->dbalExpressionBuilder->comparison($leftExpression, $operator->value, $rightExpression);
    }

    /**
     * Creates an equality comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> = <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : equal('field1', ':value1')
     *         return : "field1 = :value1"
     */
    public function equal(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->eq($leftExpression, $rightExpression);
    }

    /**
     * Creates a non equality comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <> <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : notEqual('field1', ':value1')
     *         return : "field1 <> :value1"
     */
    public function notEqual(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->neq($leftExpression, $rightExpression);
    }

    /**
     * Creates a lower-than comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> < <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : lowerThan('field1', ':value1')
     *         return : "field1 < :value1"
     */
    public function lowerThan(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->lt($leftExpression, $rightExpression);
    }

    /**
     * Creates a lower-than-equal comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <= <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : lowerThanEqual('field1', ':value1')
     *         return : "field1 <= :value1"
     */
    public function lowerThanEqual(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->lte($leftExpression, $rightExpression);
    }

    /**
     * Creates a greater-than comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> > <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : greaterThan('field1', ':value1')
     *         return : "field1 > :value1"
     */
    public function greaterThan(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->gt($leftExpression, $rightExpression);
    }

    /**
     * Creates a greater-than-equal comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> >= <right expr>.
     *
     * @param string $leftExpression the left expression
     * @param string $rightExpression the right expression
     *
     * @return string
     *
     * @example
     *         method : greaterThanEqual('field1', ':value1')
     *         return : "field1 >= :value1"
     */
    public function greaterThanEqual(string $leftExpression, string $rightExpression): string
    {
        return $this->dbalExpressionBuilder->gte($leftExpression, $rightExpression);
    }

    /**
     * Creates an IS NULL expression with the given arguments.
     *
     * @param string $expression the expression to be restricted by IS NULL
     *
     * @return string
     *
     * @example
     *         method : isNull('field1')
     *         return : "field1 IS NULL"
     */
    public function isNull(string $expression): string
    {
        return $this->dbalExpressionBuilder->isNull($expression);
    }

    /**
     * Creates an IS NOT NULL expression with the given arguments.
     *
     * @param string $expression the expression to be restricted by IS NOT NULL
     *
     * @return string
     *
     * @example
     *         method : isNotNull('field1')
     *         return : "field1 IS NOT NULL"
     */
    public function isNotNull(string $expression): string
    {
        return $this->dbalExpressionBuilder->isNotNull($expression);
    }

    /**
     * Creates a LIKE comparison expression.
     *
     * @param string $expression The expression to be inspected by the LIKE comparison
     * @param string $pattern The pattern to compare against
     * @param string|null $escapeChar To indicate the escape character, by default it's '\'  (optional)
     *
     * @return string
     *
     * @example
     *         method : like('field1', ':value1')
     *         return : "field1 LIKE :value1"
     *         method : like('field1', ':value1','$')
     *         return : "field1 LIKE :value1" ESCAPE '$'
     */
    public function like(string $expression, string $pattern, ?string $escapeChar = null): string
    {
        return $this->dbalExpressionBuilder->like($expression, $pattern, $escapeChar);
    }

    /**
     * Creates a NOT LIKE comparison expression.
     *
     * @param string $expression The expression to be inspected by the NOT LIKE comparison
     * @param string $pattern The pattern to compare against
     * @param string|null $escapeChar To indicate the escape character, by default it's '\' (optional)
     *
     * @return string
     *
     * @example
     *         method : notLike('field1', ':value1')
     *         return : "field1 NOT LIKE :value1"
     *         method : notLike('field1', ':value1','$')
     *         return : "field1 NOT LIKE :value1" ESCAPE '$'
     */
    public function notLike(string $expression, string $pattern, ?string $escapeChar = null): string
    {
        return $this->dbalExpressionBuilder->notLike($expression, $pattern, $escapeChar);
    }

    /**
     * Creates an IN () comparison expression with the given arguments.
     *
     * @param string $expressionToBeMatched the SQL expression to be matched against the set
     * @param string|string[] $set the SQL expression or an array of SQL expressions representing the set
     *
     * @return string
     *
     * @example
     *          method : in('field1', [:value1, :value2, :value3])
     *          return : "field1 IN (:value1, :value2, :value3)"
     */
    public function in(string $expressionToBeMatched, string|array $set): string
    {
        return $this->dbalExpressionBuilder->in($expressionToBeMatched, $set);
    }

    /**
     * Creates a NOT IN () comparison expression with the given arguments.
     *
     * @param string $expressionToBeMatched the SQL expression to be matched against the set
     * @param string|string[] $set the SQL expression or an array of SQL expressions representing the set
     *
     * @return string
     *
     * @example
     *          method : notIn('field1', [:value1, :value2, :value3])
     *          return : "field1 NOT IN (:value1, :value2, :value3)"
     */
    public function notIn(string $expressionToBeMatched, string|array $set): string
    {
        return $this->dbalExpressionBuilder->notIn($expressionToBeMatched, $set);
    }
}
