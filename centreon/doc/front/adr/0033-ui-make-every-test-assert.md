# ADR-0033: Make every `packages/ui` browser test assert

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

A [browser test](../glossary.md#browser-test) whose assertions never run passes: an `await` forgotten before a `findBy*`, an assertion inside a callback that is never called. rstest has no implicit retry, so a test can end green having checked nothing. The migration of the package's tests in [#12148](https://github.com/centreon/centreon/pull/12148) made every test assert with `expect.hasAssertions()` and checked it by injecting bugs into each component: every injected bug was caught, except two the Cypress specs missed too.

One alternative was weighed: **rely on review to spot an assertion that cannot run.** It lost: the failure mode is silent by construction.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

Every browser test file of the package calls **`expect.hasAssertions()` in a `beforeEach`**. **Every test asserts**, and every `userEvent.*`, `findBy*`, `waitFor` and `waitForRequest` is **awaited**.

## Consequences

- A test that checks nothing fails, which is the point.
- The rule is enforced by the runner and in review.

## When to Amend

- The runner enforces assertions by configuration; the call then goes.
