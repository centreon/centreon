# ADR-0036: Use hand-written fakes in `src/App` tests, not mocks

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

A `src/App` test replaces a dependency, such as a repository, with a test double; [ADR-0018](0018-apip-unit-test-every-building-block.md) unit-tests handlers with fake repositories. Some tests use hand-written fakes kept in a `Double/` folder; others build mocks with PHPUnit's `createMock()`. Reviews accept both.

One alternative was weighed:

- **Mock with a framework instead of fakes.** It lost: a mock pins the calls made rather than the behaviour, and breaks on every refactor.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Replace a dependency with a hand-written fake** that implements its interface, not with a mock. Place the fake in the context's `Double/` folder, `tests/php/App/<BC>/Infrastructure/Double/`, or in `tests/php/App/Shared/…/Double/` when several contexts use it; a module uses the same layout in its own test folder.

**A mock is allowed only when a fake is really impossible**, and the pull request justifies it.

## Consequences

- A test asserts the resulting state, not the calls made, and survives a refactor of the code under test.
- A fake is written once and shared by every test of its context.
- Existing tests that use mocks stay until they are touched.
- The rule is enforced in review.

## When to Amend

- A recurring case appears where a fake cannot replace the dependency.
