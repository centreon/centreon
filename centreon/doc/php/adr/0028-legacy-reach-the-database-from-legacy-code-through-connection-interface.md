# ADR-0028: Reach the database from legacy code only through `ConnectionInterface` and its builders

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Legacy code reaches the database through `CentreonDB` (`$pearDB`, or `new CentreonDB('centstorage')` for the real-time database), `DatabaseConnection`, or a `src/Centreon*` repository extending `AbstractRepositoryRDB` or `AbstractRepositoryDRB`, with the raw `prepare()`/`bindValue()`/`fetch*()` idiom of `\PDO`. `CentreonDB` and `DatabaseConnection` implement `Adaptation\Database\Connection\ConnectionInterface`, but they also extend `\PDO`, so they invite the idiom that `ConnectionInterface` was built to replace.

`ConnectionInterface` provides everything a query needs without `\PDO`:

- factories for the builders: `createQueryBuilder()` returns a `QueryBuilderInterface`, and `createExpressionBuilder()` returns an `ExpressionBuilderInterface`, also reachable from a query builder through `expr()`;
- typed parameters: a `QueryParameter` (`int()`, `string()`, `bool()`, `null()`, `largeObject()`), grouped in a `QueryParameters` collection, or `BatchInsertParameters` for `batchInsert()`;
- methods that take the query and its parameters: `fetchAssociative()`, `fetchAllAssociative()`, `fetchOne()`, `insert()`, `update()`, `delete()`, `executeStatement()`, …

Two alternatives were weighed:

- **Keep `CentreonDB` and its `\PDO` methods, with prepared statements.** It lost: the statement idiom is verbose and untyped, and it leaves `\PDO` in new code with no path out.
- **Inject Doctrine DBAL's `Connection`, as `src/App` does.** It lost for legacy: much legacy code is not built by the Symfony container, and `ConnectionInterface` is what the existing connections already provide, so legacy code gets it without a new dependency.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

In any new function, class or method in the [Legacy scope](../glossary.md#scope):

- **Type the connection as `ConnectionInterface`.** Pass an existing `CentreonDB`, such as `$pearDB` or a `centstorage` connection: it implements the interface.
- **Build the query with its builders**: `createQueryBuilder()` for the statement, and the `ExpressionBuilderInterface` (`$queryBuilder->expr()` or `createExpressionBuilder()`) for its conditions.
- **Pass every value as a typed `QueryParameter`**, grouped in a `QueryParameters` collection given to the connection method. Bind a `TINYINT` or `BOOLEAN` flag with `QueryParameter::bool()`, and an `ENUM('0','1')` flag with `QueryParameter::string()` (`$flag ? '1' : '0'`): an integer bound to an `ENUM` is read as an index.
- **Never** call the `\PDO` methods or the deprecated `CentreonDB`/`DatabaseConnection` methods, **never** `getNativeConnection()` or `quoteString()`, and **never** concatenate a value into the SQL string.
- Where the builder cannot express the statement (`UNION`, `ON DUPLICATE KEY UPDATE`), and only there, write the SQL as a literal string whose values are named placeholders bound through `QueryParameters`.
- Take an identifier (column, sort order, table) from an allowlist, never from the input.
- Bind an `IN` list with `createMultipleBindParameters()`: from `SqlMultipleBindTrait` in a `DatabaseRepository` subclass, otherwise the function of `www/include/common/sqlCommonFunction.php`, which is not autoloaded: `require_once` it first. Never use its `createMultipleBindQuery()`, which binds through `\PDO`, nor the private `createMultipleBindQuery()` copies of `CentreonDowntime` and `CentreonServicegroups`. Return before the query when the list is empty: `IN ()` is invalid SQL.
- Escape `%`, `_` and `\` in a value bound to a `LIKE`: `\` is the default escape character of MySQL and MariaDB.
- A new `src/Centreon*` repository **file** extends `Core\Common\Infrastructure\Repository\DatabaseRepository`, which injects `ConnectionInterface`, never `AbstractRepositoryRDB` or `AbstractRepositoryDRB`.

Examples are in [database.md](../database.md#2-legacy-code).

## Consequences

- The `\PDO` surface stays out of new code, and every value reaches the database as a typed, bound parameter.
- A new method added to an existing class keeps the class's structure: `CentreonDB` and `DatabaseConnection` implement `ConnectionInterface`, so its methods are already available on the existing connection, such as `$this->db` in an `AbstractRepositoryRDB` or `AbstractRepositoryDRB` repository.
- The literal-SQL case is the one exception this ADR makes to [ADR-0010](0010-apip-core-legacy-parameterize-repository-queries.md): the query builder has no `UNION` or upsert, so only those statements are written as SQL strings, their values still bound.
- `src/App` and `src/Core` are out of scope.
- The rule is blocking on new code, but does not force a rewrite of existing code. It is enforced in review: a search for `CentreonDB`, `prepare(` or `AbstractRepository{RDB,DRB}` is only a heuristic, and a hit is confirmed by reading the code.

## When to Amend

- No legacy code remains.
- The `Adaptation` layer's database interfaces are replaced.
