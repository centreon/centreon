# ADR-0074: Read the current time in `src/App` from `ClockInterface`, never from `new \DateTimeImmutable()`

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

`src/App` reads the current time with `new \DateTimeImmutable()`: as the default of an event's `firedAt` (`AggregateCreated`, `HostMassChanged`), of `ActivityLog::$performedAt`, and in `Token`'s expiry checks. A test cannot fix that time, so it asserts around it or not at all.

Symfony registers a `clock` service, autowirable as `Psr\Clock\ClockInterface`, and `symfony/clock` provides `MockClock` for tests. No `src/App` class uses them yet.

Two alternatives were weighed:

- **The static `Symfony\Component\Clock\Clock::get()`.** It lost: the Domain may not depend on Symfony, and a static call hides the dependency.
- **Keep `new \DateTimeImmutable()`, and compare with a margin in tests.** It lost: an expiry or an ordering at the boundary cannot be tested.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Read the current time from an injected `Psr\Clock\ClockInterface`**, never from `new \DateTimeImmutable()`, `new \DateTimeImmutable('now')`, `time()` or `date()` without a timestamp.

**The Domain receives the current time as a parameter** from its caller (`$token->isExpired($now)`), since it has no services. A handler passes `$this->clock->now()` to the event it fires instead of relying on a `firedAt` default.

**A test fixes the time** with `Symfony\Component\Clock\MockClock`.

## Consequences

- Time-dependent behaviour is tested at its boundary.
- `symfony/clock` is installed as a dependency of another package; the first pull request that uses it directly adds it to `composer.json`.
- Existing calls and `firedAt` defaults stay until they are touched.
- The rule is enforced in review.

## When to Amend

- The PSR clock is replaced, or the Domain is allowed a static time source.
