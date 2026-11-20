# ADR-0021: Enforce invariants with `webmozart/assert`, not hand-rolled assertions and custom exceptions

- **Status:** Accepted
- **Date:** 2026-10-28
- **Scope:** APIP

## Context

Value objects enforce their invariants in the constructor. Entities and aggregates enforce theirs in the constructor and in every business method that changes their state ([ADR-0009](0009-apip-aggregates-readonly-by-default.md), [ADR-0012](0012-apip-change-aggregate-internals-through-the-root.md)). Application code checks preconditions before acting. The project depends on `webmozart/assert`, and `src/App` already uses `Webmozart\Assert\Assert` for exactly this. `Assert::*` throws `Webmozart\Assert\InvalidArgumentException`, a subclass of the native `\InvalidArgumentException`.

For a check webmozart lacks, `App\Shared\Domain\Assert\Assert` ([#10650](https://github.com/centreon/centreon/pull/10650)) adds Centreon-specific checks (e.g. `ipOrHostname`) that **delegate to `Webmozart\Assert\Assert`**, so they throw the same exception.

The temptation is to re-invent assertions: a bespoke validator with its own methods and its own exception, for checks webmozart already does (`notEmpty`, `length`, `range`, regex, `inArray`, …). The legacy stack did exactly that with `Centreon\Domain\Common\Assertion\Assertion` and its `AssertionException`. Repeating it in `src/App` gives each invariant its own vocabulary and its own exception, and every layer that catches or maps a rejection has to know them all.

Two alternatives were weighed:

- **Keep the legacy `Assertion` class.** It lost: it is a legacy type from `src/Centreon`, which the domain must never see ([ADR-0005](0005-apip-isolate-legacy-in-infrastructure-legacy.md)), and it duplicates webmozart.
- **Write Centreon's own assertions, with no library in the domain.** It lost: it rewrites a maintained library for no gain in behaviour.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

In `src/App`, enforce invariants and preconditions with **`Webmozart\Assert\Assert`**. When webmozart is missing a check that is reusable and Centreon-specific, add it to **`App\Shared\Domain\Assert\Assert`**.

The domain may depend only on the libraries of deptrac's `Helpers` layer. **`Webmozart\Assert\Assert` is deliberately one of them**, for a single assertion vocabulary across the domain and the application.

Do **not** write a parallel assertion helper, and do **not** raise a custom exception, for a check webmozart already provides. Native `\assert()` is no runtime guard, since it can be disabled in production: use it only to narrow a type the code already guarantees, never to check a value.

This is about invariant and precondition assertions. A domain exception that expresses a business outcome rather than a malformed value (`AggregateNotFoundException`, `AggregateConflictException`, `AccessDeniedException` in `App\Shared\Domain\Exception`) stays legitimate and is unaffected.

## Consequences

- Invariants read the same everywhere, and every rejection reaches the boundary as a native `\InvalidArgumentException`. Code that catches a rejection catches that native type: deptrac keeps `Webmozart\Assert\InvalidArgumentException` out of the domain and the application.
- A new reusable, Centreon-specific check adds a method to the shared `Assert` rather than spawning a bespoke validator; a one-off check stays an inline `Assert::*` call.
- The legacy `Assertion` / `AssertionException` pair is not used by new `src/App` code.
- How a rejection becomes an HTTP response, and where a write request is validated, is governed by [ADR-0001](0001-apip-validate-in-input-and-handler.md), not by this ADR.
- `Assert::*` puts the rejected value in its default message, and a `%s` in a custom message receives it too; that message is logged unmasked. When the value is a secret, pass a message of your own without `%s`. A method added to the shared `Assert` takes an optional `$message` for the same reason; `ipOrHostname` has none, so never call it on a secret.
- Deptrac allows only `Webmozart\Assert\Assert` from webmozart in the domain; the rest is enforced in review.

## When to Amend

- `webmozart/assert` is dropped or replaced as the assertion library.
- The domain must become free of external dependencies.
