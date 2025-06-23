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

namespace Adaptation\Database\QueryBuilder\Adapter\Dbal;

use Adaptation\Database\Connection\Adapter\Dbal\DbalConnectionAdapter;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\ExpressionBuilder\Adapter\Dbal\DbalExpressionBuilderAdapter;
use Adaptation\Database\ExpressionBuilder\Exception\ExpressionBuilderException;
use Adaptation\Database\ExpressionBuilder\ExpressionBuilderInterface;
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Domain\Log\Logger;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineDbalQueryBuilder;

/**
 * Class
 *
 * @class   DbalQueryBuilderAdapter
 * @package Adaptation\Database\Adapter\Dbal
 * @see     DoctrineDbalQueryBuilder
 *
 * To dynamically create SQL queries.
 */
final readonly class DbalQueryBuilderAdapter implements QueryBuilderInterface
{
    /**
     * DbalQueryBuilderAdapter constructor
     *
     * @param DoctrineDbalQueryBuilder $dbalQueryBuilder
     * @param ConnectionConfig $connectionConfig
     */
    public function __construct(
        private DoctrineDbalQueryBuilder $dbalQueryBuilder,
        private ConnectionConfig $connectionConfig
    ) {}

    /**
     * Factory
     *
     * Creates a new instance of a SQL query builder.
     *
     * We have to use a connection configuration to instantiate the query builder because the query builder needs a
     * connection to work.
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws QueryBuilderException
     * @return DbalQueryBuilderAdapter
     */
    public static function createFromConnectionConfig(ConnectionConfig $connectionConfig): QueryBuilderInterface
    {
        try {
            $connection = DbalConnectionAdapter::createFromConfig($connectionConfig);

            return new self(new DoctrineDbalQueryBuilder($connection->getDbalConnection()), $connectionConfig);
        } catch (ConnectionException $exception) {
            Logger::create()->error(
                'An error occurred while trying to create a new instance of query builder',
                ['exception' => $exception->getContext()]
            );

            throw QueryBuilderException::createFromConnectionConfigFailed($exception);
        }
    }

    /**
     * To build where clauses easier
     *
     * @throws QueryBuilderException
     * @return DbalExpressionBuilderAdapter
     */
    public function expr(): ExpressionBuilderInterface
    {
        try {
            return DbalExpressionBuilderAdapter::createFromConnectionConfig($this->connectionConfig);
        } catch (ExpressionBuilderException $exception) {
            Logger::create()->error(
                'An error occurred while trying to create a new instance of expression builder',
                ['exception' => $exception->getContext()]
            );

            throw QueryBuilderException::getExpressionBuilderFailed($exception);
        }
    }

    /**
     * Gets the complete SQL string formed by the current specifications of this QueryBuilder.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u')
     *         ->from('User', 'u')
     *     echo $qb->getQuery(); // SELECT u FROM User u
     * </code>
     *
     * @return string the SQL query string
     */
    public function getQuery(): string
    {
        return $this->dbalQueryBuilder->getSQL();
    }

    /**
     * Specifies an item that is to be returned in the query result.
     * Replaces any previously specified selections, if any.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.id', 'p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id')
     *         ->getQuery();
     * </code>
     *
     * @param string ...$expressions The selection expressions.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function select(string ...$expressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->select(...$expressions);

        return $this;
    }

    /**
     * Adds or removes DISTINCT to/from the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.id')
     *         ->distinct()
     *         ->from('users', 'u')
     *         ->getQuery();
     * </code>
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function distinct(): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->distinct();

        return $this;
    }

    /**
     * Adds an item that is to be returned in the query result.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.id')
     *         ->addSelect('p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'u.id = p.user_id')
     *         ->getQuery();
     * </code>
     *
     * @param string $expression the selection expression
     * @param string ...$expressions Additional selection expressions.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function addSelect(string $expression, string ...$expressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->addSelect($expression, ...$expressions);

        return $this;
    }

    /**
     * Turns the query being built into a bulk delete query that ranges over
     * a certain table.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->delete('users u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1)
     *         ->getQuery();
     * </code>
     *
     * @param string $table the table whose rows are subject to the deletion
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function delete(string $table): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->delete($table);

        return $this;
    }

    /**
     * Turns the query being built into a bulk update query that ranges over
     * a certain table
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->update('counters c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?')
     *         ->getQuery();
     * </code>
     *
     * @param string $table the table whose rows are subject to the update
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function update(string $table): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->update($table);

        return $this;
    }

    /**
     * Turns the query being built into an insert query that inserts into
     * a certain table
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         )
     *         ->getQuery();
     * </code>
     *
     * @param string $table the table into which the rows should be inserted
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function insert(string $table): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->insert($table);

        return $this;
    }

    /**
     * Creates and adds a query root corresponding to the table identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.id')
     *         ->from('users', 'u')
     *         ->getQuery();
     * </code>
     *
     * @param string $table the table
     * @param string|null $alias the alias of the table
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function from(string $table, ?string $alias = null): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->from($table, $alias);

        return $this;
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1')
     *         ->getQuery();
     * </code>
     *
     * @param string $fromAlias the alias that points to a from clause
     * @param string $join the table name to join
     * @param string $joinAlias the alias of the join table
     * @param string $condition the condition for the join
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function join(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): QueryBuilderInterface {
        $this->dbalQueryBuilder->join($fromAlias, $join, $joinAlias, $condition);

        return $this;
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1')
     *         ->getQuery();
     * </code>
     *
     * @param string $fromAlias the alias that points to a from clause
     * @param string $join the table name to join
     * @param string $joinAlias the alias of the join table
     * @param string $condition the condition for the join
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function innerJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): QueryBuilderInterface {
        $this->dbalQueryBuilder->innerJoin($fromAlias, $join, $joinAlias, $condition);

        return $this;
    }

    /**
     * Creates and adds a left join to the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1')
     *         ->getQuery();
     * </code>
     *
     * @param string $fromAlias the alias that points to a from clause
     * @param string $join the table name to join
     * @param string $joinAlias the alias of the join table
     * @param string $condition the condition for the join
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function leftJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): QueryBuilderInterface {
        $this->dbalQueryBuilder->leftJoin($fromAlias, $join, $joinAlias, $condition);

        return $this;
    }

    /**
     * Creates and adds a right join to the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->rightJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1')
     *         ->getQuery();
     * </code>
     *
     * @param string $fromAlias the alias that points to a from clause
     * @param string $join the table name to join
     * @param string $joinAlias the alias of the join table
     * @param string $condition the condition for the join
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function rightJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): QueryBuilderInterface {
        $this->dbalQueryBuilder->rightJoin($fromAlias, $join, $joinAlias, $condition);

        return $this;
    }

    /**
     * Sets a new value for a column in a bulk update query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->update('counters c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?')
     *         ->getQuery();
     * </code>
     *
     * @param string $column the column to set
     * @param string $value the value, expression, placeholder, etc
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function set(string $column, string $value): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->set($column, $value);

        return $this;
    }

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the where clause expression
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *          ->select('c.value')
     *          ->from('counters', 'c')
     *          ->where('c.id = ?')
     *          ->getQuery();
     *
     *      // You can optionally programmatically build and/or expressions
     *      $qb = $db->createQueryBuilder();
     *
     *      $expr = $db->createExpressionBuilder();
     *      $eq1 = $expr->equal('c.id', 1);
     *      $eq2 = $expr->equal('c.id', 2);
     *      $or = $expr->or($eq1, $eq2);
     *
     *      $qb->update('counters c')
     *          ->set('c.value', 'c.value + 1')
     *          ->where($or)
     *          ->getQuery();
     * </code>
     *
     * @param string $whereClauseExpression the WHERE clause predicate
     * @param string ...$whereClauseExpressions Additional WHERE clause predicates.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function where(string $whereClauseExpression, string ...$whereClauseExpressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->where($whereClauseExpression, ...$whereClauseExpressions);

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the where clause expression
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.username LIKE ?')
     *         ->andWhere('u.is_active = 1')
     *         ->getQuery();
     * </code>
     *
     * @param string $whereClauseExpression the predicate to append
     * @param string ...$whereClauseExpressions Additional predicates to append.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     * @see where()
     */
    public function andWhere(string $whereClauseExpression, string ...$whereClauseExpressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->andWhere($whereClauseExpression, ...$whereClauseExpressions);

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the where clause expression
     *
     * @param string $whereClauseExpression the predicate to append
     * @param string ...$whereClauseExpressions Additional predicates to append.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     * @see where()
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = 1')
     *         ->orWhere('u.id = 2')
     *         ->getQuery();
     * </code>
     */
    public function orWhere(string $whereClauseExpression, string ...$whereClauseExpressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->orWhere($whereClauseExpression, ...$whereClauseExpressions);

        return $this;
    }

    /**
     * Specifies one or more grouping expressions over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id')
     *         ->getQuery();
     * </code>
     *
     * @param string $expression The grouping expression
     * @param string ...$expressions Additional grouping expressions
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function groupBy(string $expression, string ...$expressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->groupBy($expression, ...$expressions);

        return $this;
    }

    /**
     * Adds one or more grouping expressions to the query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin')
     *         ->addGroupBy('u.createdAt')
     *         ->getQuery();
     * </code>
     *
     * @param string $expression The grouping expression
     * @param string ...$expressions Additional grouping expressions
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function addGroupBy(string $expression, string ...$expressions): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->addGroupBy($expression, ...$expressions);

        return $this;
    }

    /**
     * Specifies values for an insert query indexed by column names.
     * Replaces any previous values, if any.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         )
     *         ->getQuery();
     * </code>
     *
     * @param array<string, mixed> $values the values to specify for the insert query indexed by column names
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function values(array $values): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->values($values);

        return $this;
    }

    /**
     * Sets a value for a column in an insert query.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?'
     *             )
     *         )
     *         ->setValue('password', '?')
     *         ->getQuery();
     * </code>
     *
     * @param string $column the column into which the value should be inserted
     * @param string $value the value that should be inserted into the column
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function setValue(string $column, string $value): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->setValue($column, $value);

        return $this;
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the having clause expression
     *
     * @param string $havingClause the HAVING clause predicate
     * @param string ...$havingClauses Additional HAVING clause predicates.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function having(string $havingClause, string ...$havingClauses): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->having($havingClause, ...$havingClauses);

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the having clause expression
     *
     * @param string $havingClause the predicate to append
     * @param string ...$havingClauses Additional predicates to append.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function andHaving(string $havingClause, string ...$havingClauses): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->andHaving($havingClause, ...$havingClauses);

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * Use {@see DbalExpressionBuilderAdapter} to build the having clause expression
     *
     * @param string $havingClause the predicate to append
     * @param string ...$havingClauses Additional predicates to append.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function orHaving(string $havingClause, string ...$havingClauses): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->orHaving($havingClause, ...$havingClauses);

        return $this;
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort the ordering expression
     * @param string|null $order the ordering direction
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function orderBy(string $sort, ?string $order = null): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->orderBy($sort, $order);

        return $this;
    }

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort the ordering expression
     * @param string|null $order the ordering direction
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function addOrderBy(string $sort, ?string $order = null): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->addOrderBy($sort, $order);

        return $this;
    }

    /**
     * @param int $limit
     *
     * @return DbalQueryBuilderAdapter
     */
    public function limit(int $limit): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->setMaxResults($limit);

        return $this;
    }

    /**
     * @param int $offset
     *
     * @return DbalQueryBuilderAdapter
     */
    public function offset(int $offset): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->setFirstResult($offset);

        return $this;
    }

    /**
     * Resets the WHERE conditions for the query.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function resetWhere(): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->resetWhere();

        return $this;
    }

    /**
     * Resets the grouping for the query.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function resetGroupBy(): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->resetGroupBy();

        return $this;
    }

    /**
     * Resets the HAVING conditions for the query.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function resetHaving(): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->resetHaving();

        return $this;
    }

    /**
     * Resets the ordering for the query.
     *
     * @return DbalQueryBuilderAdapter this QueryBuilder instance
     */
    public function resetOrderBy(): QueryBuilderInterface
    {
        $this->dbalQueryBuilder->resetOrderBy();

        return $this;
    }
}
