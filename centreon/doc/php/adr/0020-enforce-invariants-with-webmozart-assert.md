# ADR-0020: Enforce invariants with `webmozart/assert`, not hand-rolled assertions and custom exceptions

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

Value objects, entities and aggregates enforce their invariants in the constructor, and application code checks preconditions before acting. The project depends on `webmozart/assert`, and `src/App` already uses `Webmozart\Assert\Assert` in ~160 places for exactly this. `Assert::*` throws an `InvalidArgumentException` (a native `\InvalidArgumentException`), which the pipeline recognises as a value-object / domain rejection — `App\Shared\Infrastructure\Messenger\LoggingMiddleware` maps it to a client error (4xx) instead of a logged server error (5xx). The convention is wired end to end.

For a check webmozart lacks, `App\Shared\Domain\Assert\Assert` adds a thin Centreon extension (e.g. `ipOrHostname`) that **delegates to `WebmozartAssert`**, so it throws the same exception and flows through the same mapping.

The temptation is to re-invent assertions: a bespoke validator with its own methods and a custom exception, for checks webmozart already does (`notEmpty`, `length`, `range`, regex, `inArray`, …). The legacy stack did exactly that with `Centreon\Domain\Common\Assertion\Assertion` and its `AssertionException`. Repeating it in `src/App` fragments the convention, and a custom exception that is not an `\InvalidArgumentException` silently breaks the 4xx mapping — the same rejection becomes a logged 500.

## Decision

Enforce invariants and preconditions in the domain and application with **`Webmozart\Assert\Assert`**. When webmozart is missing a check that is reusable and Centreon-specific, add it to **`App\Shared\Domain\Assert\Assert`**, delegating to `WebmozartAssert` so it throws the same `\InvalidArgumentException`.

Do **not** write a parallel assertion helper, and do **not** raise a custom exception, for a check webmozart already provides. Reserve native `\assert()` for type-narrowing in tests — it can be disabled in production, so it is no runtime guard.

This is about input and precondition assertions. A domain exception that expresses a genuine business outcome rather than a malformed value — `AggregateNotFoundException`, `AggregateConflictException`, `AccessDeniedException` in `App\Shared\Domain\Exception` — stays legitimate and is unaffected.

## Consequences

- Invariants read the same everywhere, and a single exception type reaches the boundary, mapped to a client error without polluting the server logs.
- A new reusable, Centreon-specific check extends the shared `Assert` rather than spawning a bespoke validator; a one-off check stays an inline `Assert::*` call.
- The legacy `Assertion` / `AssertionException` pair is not used by new `src/App` code.
- `webmozart/assert` in a test, or native `\assert()` in production code, is the inverse mistake (see the assertion convention).
- Where to validate — input and handler — is governed by [ADR-0001](0001-validate-in-input-and-handler.md); this ADR governs how.

## When to Amend

- `webmozart/assert` is dropped or replaced as the assertion library.
- The pipeline stops mapping `\InvalidArgumentException` to a client error, so the shared exception type no longer matters.
