# ADR-0033: Write `src/App` tests as PHPUnit classes, never Pest

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

The backend test suite mixes two writing styles: **Pest** closures (`it()`, `test()`) and PHPUnit classes. The `src/App` tests, under `tests/php/App`, are all plain **PHPUnit** `TestCase` classes, run by the PHPUnit configurations (`phpunit.new.xml`, `phpunit.new.integration.xml`), and their helpers are PHPUnit classes too. Nothing states that this must stay so.

One alternative was weighed:

- **Allow Pest in `src/App` tests.** It lost: it would split the idioms and the helpers of a suite that has only one style today. PHPStan also cannot type `$this` inside Pest's closures, so its test configuration would need `ignoreErrors` entries for it.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every test under `tests/php/App`, or a module's equivalent folder, is a PHPUnit `TestCase` class.** Do **not** add a Pest file there.

## Consequences

- The `src/App` tests share one style and one set of helpers, and `phpstan.new.test.neon` needs no Pest-specific `ignoreErrors` entry.
- The rule is enforced in review.

## When to Amend

- PHPUnit is replaced as the test framework.
