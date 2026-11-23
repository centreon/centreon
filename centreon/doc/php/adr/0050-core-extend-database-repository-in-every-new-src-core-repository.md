# ADR-0050: Extend `DatabaseRepository` in every new `src/Core` repository

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** Core

## Context

Most `src/Core` repositories extend `AbstractRepositoryRDB` or `AbstractRepositoryDRB`, which hand over a connection that is also a `\PDO` (`DatabaseConnection`) and are `@deprecated` in favour of `Core\Common\Infrastructure\Repository\DatabaseRepository`. `DatabaseRepository` injects an `Adaptation\Database\Connection\ConnectionInterface`, and few repositories extend it yet.

[ADR-0027](0027-core-reach-the-database-from-src-core-through-connection-interface.md) sets how a query reaches the database. Which class a new repository extends is a separate choice: the base class could change, for example when `DatabaseRepository` is replaced, without changing how a query is built and bound.

One alternative was weighed:

- **Let a new repository extend `AbstractRepositoryRDB` or `AbstractRepositoryDRB`, like its neighbours.** It lost: they hand over a `\PDO`, which invites the idiom [ADR-0027](0027-core-reach-the-database-from-src-core-through-connection-interface.md) forbids.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#pullrequestreview-5875494780).

## Decision

**A new `src/Core` repository file extends `DatabaseRepository`**, never `AbstractRepositoryRDB` or `AbstractRepositoryDRB`.

**A new method added to an existing repository keeps the class's `extends`.**

## Consequences

- A new repository gets `ConnectionInterface` without a `\PDO` method within reach.
- An existing repository keeps its base class: changing it is a change of its own.
- The rule is enforced in review: a search for `extends AbstractRepository` in a new file is the check.

## When to Amend

- `DatabaseRepository` is replaced or removed.
- No `src/Core` repository remains.
