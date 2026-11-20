# ADR-0030: Make legacy repositories throw a `RepositoryException`, never log or swallow a failure

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Legacy repositories, in `src/Centreon*` and `src/Security`, handle a database failure in two ways. Many log it and then rethrow it, so the caller logs it again. Some log it and return a fallback value, such as an empty list or `null`, so the caller cannot tell an empty result from an error.

One alternative was weighed:

- **Let repositories log, as today.** It lost: the failure is logged twice, or hidden behind a fallback, instead of surfacing once at the layer that can decide what to do with it.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**A repository does not log at all**, not through the `Adaptation` layer either, and **does not swallow a failure.** It wraps the caught infrastructure exception in a `Core\Common\Domain\Exception\RepositoryException`, with its own business message, the business data as **`context`**, identifiers and never a [secret](../glossary.md#secret), and the caught exception as **`previous`**. A write of a [secret](../glossary.md#secret) is the exception: see the Security topic of the [index](index.md).

This applies to every new repository file and to every new method, including a new method in an existing repository whose neighbours log the failure. In an existing method that is not rewritten, a touched log line follows [ADR-0017](0017-legacy-log-legacy-code-through-adaptation-logger.md).

An example is in [database.md](../database.md#3-repository-failure).

## Consequences

- A repository failure surfaces once, as a typed `RepositoryException`, at the layer that can decide what to do with it: log it, translate it or let it propagate.
- The `previous` chain is logged unmasked by whoever logs the exception, so the business message and context carry identifiers, never a [secret](../glossary.md#secret).
- Legacy repositories also throw `Centreon\Domain\Repository\RepositoryException`, the deprecated class it replaces, unrelated by inheritance. A caller of a new method catches the `src/Core` exception, alongside the legacy one where the class throws both; such a class imports the new one under an alias.
- The rule is blocking on new code, but does not force a rewrite of existing methods.
- `src/Core` is out of scope. `src/App` is out of scope too: its repositories let the failure propagate, as set by [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md).
- The rule is enforced in review.

## When to Amend

- No legacy code remains.
- `RepositoryException` is replaced by another typed failure.
