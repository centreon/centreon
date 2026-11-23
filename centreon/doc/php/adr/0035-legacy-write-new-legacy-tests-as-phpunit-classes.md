# ADR-0035: Write new legacy tests as PHPUnit classes, not Pest

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Most legacy tests, under `tests/php` outside `tests/php/App` and `tests/php/Core`, are PHPUnit classes; some are **Pest** closures (`it()`, `test()`). `composer test` runs them all through the Pest runner. Two styles for one suite split the idioms and the helpers, and reviewers have to know which applies where.

One alternative was weighed:

- **Write new legacy tests with Pest.** It lost: keeping both styles keeps the split. PHPStan also cannot type `$this` inside Pest's closures, so `phpstan.legacy.test.neon` carries `ignoreErrors` entries for it.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new legacy test file, outside `tests/php/App` and `tests/php/Core` and their equivalents in a module, is a PHPUnit `TestCase` class.** Do **not** add new Pest files. An existing Pest file may still receive a new case in place; Pest files are migrated opportunistically, not in a sweep.

## Consequences

- New legacy tests share one style with the `src/App` tests.
- Pest stays as the runner until the last Pest file is migrated; then its dependency, and the PHPStan `ignoreErrors` entries that exist only for Pest, can be dropped.
- The rule is enforced in review.

## When to Amend

- Pest is removed from the project and no Pest file remains.
- No legacy code remains.
