# ADR-0048: Drop the driver exception when a `src/Core` repository write of a secret fails

- **Status:** Accepted
- **Date:** 2026-11-13
- **Scope:** Core

## Context

[ADR-0029](0029-core-make-src-core-repositories-throw-repository-exception.md) makes a `src/Core` repository wrap a caught database failure in a `RepositoryException`, keeping it as `previous`. That chain is logged unmasked by whoever logs the exception, and a database server message can quote the value it failed to write, as a duplicate-key error does. A failed write of a [secret](../glossary.md#secret) would then log the secret.

[ADR-0047](0047-apip-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md) settles the same case for `src/App`.

One alternative was weighed:

- **Keep the driver exception as `previous`, and mask it in the logging pipeline.** It lost: the pipeline cannot tell which part of a free-text driver message is a secret.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**A `src/Core` repository method that writes a [secret](../glossary.md#secret) catches the database failure of that write and throws a `RepositoryException` with a generic message, without the caught exception as `previous`.** The message and the exception's context name the operation and the identifier only, never the value.

Apply the rule to every new or touched repository method that writes a secret.

## Consequences

- The secret stays out of the repository's exception chain, at the cost of the driver message: a failure is diagnosed from the operation and the identifier, and by reproducing it.
- It is the one exception to [ADR-0029](0029-core-make-src-core-repositories-throw-repository-exception.md), which keeps the caught exception as `previous`.
- `CentreonDB`, `DatabaseConnection` and `DbalConnectionAdapter` log the driver message themselves before they throw, so a failed secret write through them still reaches the log. Dropping `previous` keeps the secret out of the repository's own exception only; the connection's log is a known gap.
- The rule is enforced in review.

## When to Amend

- MySQL and MariaDB stop quoting values in their error messages.
- The logging pipeline masks driver messages reliably.
- `src/Core` is fully migrated to `src/App`.
