# ADR-0029: Make `src/Core` repositories throw a `RepositoryException`, never log or swallow a failure

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

`src/Core` repositories handle a database failure in two ways. Many log it and then rethrow it, so the caller logs it again. Some log it and return a fallback value, such as an empty list or `null`, so the caller cannot tell an empty result from an error.

One alternative was weighed:

- **Let repositories log, as today.** It lost: the failure is logged twice, or hidden behind a fallback, instead of surfacing once at the layer that can decide what to do with it.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**A repository does not log at all**, not through the `Adaptation` layer either, and **does not swallow a failure.** It wraps the caught infrastructure exception in a `Core\Common\Domain\Exception\RepositoryException`, with its own business message, the business data as **`context`** and the caught exception as **`previous`**.

**A repository writing a [secret](../glossary.md#secret)** throws with a generic message and **without** `previous`: a driver message can quote the value, as a duplicate-key error does.

This applies to every new repository file and to every new method, including a new method in an existing repository whose neighbours log the failure. In an existing method that is not rewritten, a touched log line follows [ADR-0016](0016-core-log-src-core-through-adaptation-logger.md).

An example is in [database.md](../database.md#3-repository-failure).

## Consequences

- A repository failure surfaces once, as a typed `RepositoryException`, at the layer that can decide what to do with it: log it, translate it or let it propagate.
- The `previous` chain is logged unmasked by whoever logs the exception, so the business message and context carry identifiers, never a [secret](../glossary.md#secret).
- `CentreonDB`, `DatabaseConnection` and `DbalConnectionAdapter` log the driver message themselves before they throw, so a failed secret write through them still reaches the log. Dropping `previous` keeps the secret out of the repository's own exception only; the connection's log is a known gap.
- The rule is blocking on new code, but does not force a rewrite of existing methods.
- `src/App` is out of scope: its repositories let the failure propagate, as set by [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md).
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- `RepositoryException` is replaced by another typed failure.
