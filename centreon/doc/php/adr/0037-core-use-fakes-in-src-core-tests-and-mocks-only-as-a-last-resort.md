# ADR-0037: Use hand-written fakes in `src/Core` tests, and mocks only as a last resort

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

`src/Core` tests replace their dependencies with mocks built by PHPUnit's `createMock()`. Most of the mocked types are interfaces: the repository interfaces, `ContactInterface`, the presenters. A fake can replace any of them. Some are concrete classes that the tested code type-hints directly, even when they implement an interface (`OptionService` implements `OptionServiceInterface`): a fake would have to extend them and rebuild a constructor and dependencies the test does not control.

`tests/php/mock` (`Centreon\Test\Mock\`) holds doubles of legacy globals, such as `CentreonDB` and `PEAR`. `src/Core` code gets its dependencies injected, so it has no use for them.

Two alternatives were weighed:

- **Keep mocking freely.** It lost: a mock pins the calls made rather than the behaviour, and breaks on every refactor.
- **Ban mocks outright.** It lost: some dependencies are concrete classes that no fake can replace without a refactor.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Replace a dependency typed with an interface by a hand-written fake** that implements it. Place the fake in a `Double/` subfolder of the tested context, such as `tests/php/Core/<Context>/Double/`.

**A hand-written spy is allowed where the call itself is the behaviour**, such as a message dispatched to a bus.

**Use a PHPUnit mock only as a last resort**, when a fake is impossible, such as a dependency that the tested code type-hints as a concrete class, and the pull request justifies it.

**Never use `tests/php/mock`** (`Centreon\Test\Mock\`) in a `src/Core` test.

## Consequences

- A new test asserts the resulting state rather than the calls made, as in `src/App`.
- Code typed with an interface, such as `OptionServiceInterface` rather than `OptionService`, can be tested with a fake.
- Existing tests that use mocks stay until they are touched.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
