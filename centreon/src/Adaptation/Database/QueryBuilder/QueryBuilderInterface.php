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

namespace Adaptation\Database\QueryBuilder;

use Adaptation\Database\ExpressionBuilder\ExpressionBuilderInterface;

/**
 * Interface QueryBuilderInterface
 *
 * @class   QueryBuilderInterface
 * @package Adaptation\Database
 *
 * To dynamically create SQL queries.
 */
interface QueryBuilderInterface
{
    /**
     * To build where clauses easier
     *
     * @return ExpressionBuilderInterface
     */
    public function expr(): ExpressionBuilderInterface;

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
    public function getQuery(): string;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function select(string ...$expressions): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function distinct(): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function addSelect(string $expression, string ...$expressions): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function delete(string $table): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function update(string $table): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function insert(string $table): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function from(string $table, ?string $alias = null): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function join(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function innerJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function leftJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function rightJoin(
        string $fromAlias,
        string $join,
        string $joinAlias,
        string $condition
    ): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function set(string $column, string $value): self;

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * Use {@see ExpressionBuilderInterface} to build the where clause expression
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('c.value')
     *         ->from('counters', 'c')
     *         ->where('c.id = ?')
     *         ->getQuery();
     *
     *     // You can optionally programmatically build and/or expressions
     *     $qb = $db->createQueryBuilder();
     *
     *     $expr = $db->createExpressionBuilder();
     *     $eq1 = $expr->equal('c.id', 1);
     *     $eq2 = $expr->equal('c.id', 2);
     *     $or = $expr->or($eq1, $eq2);
     *
     *     $qb->update('counters c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where($or)
     *         ->getQuery();
     * </code>
     *
     * @param string $whereClauseExpression the WHERE clause predicate
     * @param string ...$whereClauseExpressions Additional WHERE clause predicates.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function where(string $whereClauseExpression, string ...$whereClauseExpressions): self;

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * Use {@see ExpressionBuilderInterface} to build the where clause expression
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
     * @return QueryBuilderInterface this QueryBuilder instance
     * @see where()
     */
    public function andWhere(string $whereClauseExpression, string ...$whereClauseExpressions): self;

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * Use {@see ExpressionBuilderInterface} to build the where clause expression
     *
     * @param string $whereClauseExpression the predicate to append
     * @param string ...$whereClauseExpressions Additional predicates to append.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     * @see where()
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = 1')
     *         ->orWhere('u.id = 2')
     *         ->getQuery();
     * </code>
     */
    public function orWhere(string $whereClauseExpression, string ...$whereClauseExpressions): self;

    /**
     * Specifies one or more grouping expressions over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * <code>
     *     $qb = $db->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id');
     * </code>
     *
     * @param string $expression The grouping expression
     * @param string ...$expressions Additional grouping expressions
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function groupBy(string $expression, string ...$expressions): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function addGroupBy(string $expression, string ...$expressions): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function values(array $values): self;

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
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function setValue(string $column, string $value): self;

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * Use {@see ExpressionBuilderInterface} to build the having clause expression
     *
     * @param string $havingClause the HAVING clause predicate
     * @param string ...$havingClauses Additional HAVING clause predicates.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function having(string $havingClause, string ...$havingClauses): self;

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * Use {@see ExpressionBuilderInterface} to build the having clause expression
     *
     * @param string $havingClause the predicate to append
     * @param string ...$havingClauses Additional predicates to append.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function andHaving(string $havingClause, string ...$havingClauses): self;

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * Use {@see ExpressionBuilderInterface} to build the having clause expression
     *
     * @param string $havingClause the predicate to append
     * @param string ...$havingClauses Additional predicates to append.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function orHaving(string $havingClause, string ...$havingClauses): self;

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort the ordering expression
     * @param string|null $order the ordering direction
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function orderBy(string $sort, ?string $order = null): self;

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort the ordering expression
     * @param string|null $order the ordering direction
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function addOrderBy(string $sort, ?string $order = null): self;

    /**
     * @param int $limit
     *
     * @return QueryBuilderInterface
     */
    public function limit(int $limit): self;

    /**
     * @param int $offset
     *
     * @return QueryBuilderInterface
     */
    public function offset(int $offset): self;

    /**
     * Resets the WHERE conditions for the query.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function resetWhere(): self;

    /**
     * Resets the grouping for the query.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function resetGroupBy(): self;

    /**
     * Resets the HAVING conditions for the query.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function resetHaving(): self;

    /**
     * Resets the ordering for the query.
     *
     * @return QueryBuilderInterface this QueryBuilder instance
     */
    public function resetOrderBy(): self;
}
