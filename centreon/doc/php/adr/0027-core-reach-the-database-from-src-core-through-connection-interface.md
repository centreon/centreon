# ADR-0027: Reach the database from `src/Core` only through `ConnectionInterface` and its builders

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

`src/Core` repositories reach the database in two ways. The older ones extend `AbstractRepositoryRDB` or `AbstractRepositoryDRB` and use the raw `prepare()`/`bindValue()`/`fetch*()` idiom of `\PDO`. The newer ones extend `Core\Common\Infrastructure\Repository\DatabaseRepository`, which came with the DBAL connection in [#5990](https://github.com/centreon/centreon/pull/5990) and injects an `Adaptation\Database\Connection\ConnectionInterface`. `CentreonDB` and `DatabaseConnection` implement that interface too, but they also extend `\PDO`, so they invite the idiom that `ConnectionInterface` was built to replace.

`ConnectionInterface` provides everything a query needs without `\PDO`:

- factories for the builders: `createQueryBuilder()` returns a `QueryBuilderInterface`, and `createExpressionBuilder()` returns an `ExpressionBuilderInterface`, also reachable from a query builder through `expr()`;
- typed parameters: a `QueryParameter` (`int()`, `string()`, `bool()`, `null()`, `largeObject()`), grouped in a `QueryParameters` collection, or `BatchInsertParameters` for `batchInsert()`;
- methods that take the query and its parameters: `fetchAssociative()`, `fetchAllAssociative()`, `fetchOne()`, `insert()`, `update()`, `delete()`, `executeStatement()`, …

Two alternatives were weighed:

- **Keep the `\PDO` methods, with prepared statements.** It lost: the statement idiom is verbose and untyped, and it leaves `\PDO` in new code with no path out.
- **Inject Doctrine DBAL's `Connection`, as `src/App` does.** It lost for `src/Core`: `ConnectionInterface` is the one connection type that `DatabaseRepository`, `CentreonDB` and `DatabaseConnection` already share, so new and existing code keep a single way to reach the database.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

In any new repository, method or class of `src/Core`:

- **A new repository file extends `DatabaseRepository`**, never `AbstractRepositoryRDB` or `AbstractRepositoryDRB`. A new method added to an existing repository keeps the class's `extends`.
- **Type the connection as `ConnectionInterface`.**
- **Build the query with its builders**: `createQueryBuilder()` for the statement, and the `ExpressionBuilderInterface` (`$queryBuilder->expr()` or `createExpressionBuilder()`) for its conditions.
- **Pass every value as a typed `QueryParameter`**, grouped in a `QueryParameters` collection given to the connection method. Bind a `TINYINT` or `BOOLEAN` flag with `QueryParameter::bool()`, and an `ENUM('0','1')` flag with `QueryParameter::string()` (`$flag ? '1' : '0'`): an integer bound to an `ENUM` is read as an index.
- **Never** call the `\PDO` methods or the deprecated `CentreonDB`/`DatabaseConnection` methods, **never** `getNativeConnection()` or `quoteString()`, and **never** concatenate a value into the SQL string.
- Where the builder cannot express the statement (`UNION`, `ON DUPLICATE KEY UPDATE`), and only there, write the SQL as a literal string whose values are named placeholders bound through `QueryParameters`.
- Take an identifier (column, sort order, table) from an allowlist, never from the input.
- Bind an `IN` list with `createMultipleBindParameters()` from `SqlMultipleBindTrait`, and return before the query when the list is empty: `IN ()` is invalid SQL.
- Escape `%`, `_` and `\` in a value bound to a `LIKE`: `\` is the default escape character of MySQL and MariaDB.

Examples are in [database.md](../database.md#1-core-repository).

## Consequences

- The `\PDO` surface stays out of new code, and every value reaches the database as a typed, bound parameter.
- A query written against `ConnectionInterface` is rewritten with Doctrine DBAL when its class moves to `src/App`.
- A new method of an existing `AbstractRepositoryRDB` or `AbstractRepositoryDRB` repository uses its connection, `$this->db` rather than `$this->connection`, through `ConnectionInterface`, which `CentreonDB` and `DatabaseConnection` implement.
- The literal-SQL case is the one exception this ADR makes to [ADR-0010](0010-apip-core-legacy-parameterize-repository-queries.md): the query builder has no `UNION` or upsert, so only those statements are written as SQL strings, their values still bound.
- `src/App` is out of scope: it uses Doctrine DBAL directly.
- The rule is blocking on new code, but does not force a rewrite of existing code. It is enforced in review: a search for `prepare(` or `AbstractRepository{RDB,DRB}` is only a heuristic, and a hit is confirmed by reading the code.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- The `Adaptation` layer's database interfaces are replaced.
