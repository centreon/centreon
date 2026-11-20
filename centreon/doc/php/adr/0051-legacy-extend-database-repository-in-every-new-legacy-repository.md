# ADR-0051: Extend `DatabaseRepository` in every new legacy repository

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** Legacy

## Context

Legacy repositories extend `AbstractRepositoryRDB`, `AbstractRepositoryDRB` or `ServiceEntityRepository`, which hand over a connection that is also a `\PDO` (`CentreonDB` or `DatabaseConnection`). `AbstractRepositoryRDB` and `AbstractRepositoryDRB` are `@deprecated` in favour of `Core\Common\Infrastructure\Repository\DatabaseRepository`, which injects an `Adaptation\Database\Connection\ConnectionInterface` and which no legacy repository extends yet.

[ADR-0028](0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md) sets how a query reaches the database. Which class a new repository extends is a separate choice: the base class could change, for example when `DatabaseRepository` is replaced, without changing how a query is built and bound.

One alternative was weighed:

- **Let a new repository extend `AbstractRepositoryRDB`, `AbstractRepositoryDRB` or `ServiceEntityRepository`, like its neighbours.** It lost: they hand over a `\PDO`, which invites the idiom [ADR-0028](0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md) forbids.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#pullrequestreview-5875494780).

## Decision

**A new legacy repository file extends `Core\Common\Infrastructure\Repository\DatabaseRepository`**, never `AbstractRepositoryRDB`, `AbstractRepositoryDRB` or `ServiceEntityRepository`.

**A new method added to an existing repository keeps the class's `extends`.**

## Consequences

- A new repository gets `ConnectionInterface` without a `\PDO` method within reach.
- An existing repository keeps its base class: changing it is a change of its own.
- The rule is enforced in review: a search for `extends AbstractRepository` or `extends ServiceEntityRepository` in a new file is the check.

## When to Amend

- `DatabaseRepository` is replaced or removed.
- No legacy repository remains.
