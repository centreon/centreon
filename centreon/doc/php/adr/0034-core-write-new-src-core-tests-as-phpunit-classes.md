# ADR-0034: Write new `src/Core` tests as PHPUnit classes, not Pest

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

Most `src/Core` tests, under `tests/php/Core`, are **Pest** closures (`it()`, `test()`); the rest are PHPUnit classes. `composer test` runs them through the Pest runner, except the integration tests under `tests/php/Core/Integration`, which `composer test:core` runs with PHPUnit. Two styles for one suite split the idioms and the helpers, and reviewers have to know which applies where.

One alternative was weighed:

- **Write new `src/Core` tests with Pest.** It lost: keeping both styles keeps the split. PHPStan also cannot type `$this` inside Pest's closures, so `phpstan.core.test.neon` carries `ignoreErrors` entries for it.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new test file under `tests/php/Core`, or a module's equivalent folder, is a PHPUnit `TestCase` class.** Do **not** add new Pest files. An existing Pest file may still receive a new case in place; Pest files are migrated opportunistically, not in a sweep.

## Consequences

- New `src/Core` tests share one style with the `src/App` tests.
- Pest stays as the runner until the last Pest file is migrated; then its dependency, and the PHPStan `ignoreErrors` entries that exist only for Pest, can be dropped.
- The rule is enforced in review.

## When to Amend

- Pest is removed from the project and no Pest file remains.
- `src/Core` is fully migrated to `src/App`.
