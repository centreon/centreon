# ADR-0038: Use hand-written fakes in legacy tests, and mocks only as a last resort

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Legacy tests, outside `tests/php/App` and `tests/php/Core` and their equivalents in a module, replace their dependencies with mocks built by PHPUnit's `createMock()`. Many of the mocked types are interfaces, such as the repository interfaces or `ContactInterface`. A fake can replace any of them. Some are concrete classes that the tested code type-hints directly, even when they implement an interface (`CentreonDB` implements `ConnectionInterface`, `OptionService` implements `OptionServiceInterface`): a fake would have to extend them and rebuild a constructor and dependencies the test does not control.

`tests/php/mock` (`Centreon\Test\Mock\`) holds doubles of legacy globals, such as `CentreonDB`, `CentreonDBStatement`, `CentreonLog` and `PEAR`, used by the `CentreonLegacy` and `CentreonRemote` tests and by the test bootstrap.

Two alternatives were weighed:

- **Keep mocking freely.** It lost: a mock pins the calls made rather than the behaviour, and breaks on every refactor.
- **Ban mocks outright.** It lost: some legacy dependencies are concrete classes, globals or static calls that no fake can replace without a refactor.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Replace a dependency typed with an interface by a hand-written fake** that implements it. Write the fake as an anonymous class in the test file; when several test files use it, move it to a `Double/` subfolder next to them.

**Use a PHPUnit mock only as a last resort**, when nothing cleaner is possible: a dependency that the tested code type-hints as a concrete class.

**Use `tests/php/mock` only as a last resort** too, when nothing cleaner is possible.

## Consequences

- A new test asserts the resulting state rather than the calls made, as in `src/App`.
- Legacy code typed with an interface, such as `ConnectionInterface` rather than `CentreonDB`, can be tested with a fake.
- Existing tests that use mocks or `tests/php/mock` stay until they are touched.
- The rule is enforced in review.

## When to Amend

- No legacy code remains.
