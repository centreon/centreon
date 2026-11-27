# ADR-0032: Make every `www/front_src` browser test assert

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A [browser test](../glossary.md#browser-test) whose assertions never run passes: an `await` forgotten before a `findBy*`, an assertion inside a callback that is never called, a `waitFor` that returns before the check. Cypress retried commands and failed on a missing element; rstest does not, so a test can end green having checked nothing.

`expect.hasAssertions()` makes a test fail when no assertion ran. The canonical entry file, `Configuration/Hosts/Specs/HostsConfiguration.browser.test.tsx`, calls it in a `beforeEach`.

One alternative was weighed: **rely on review to spot an assertion that cannot run.** It lost: the failure mode is silent by construction.

Settled in [#12141](https://github.com/centreon/centreon/pull/12141).

## Decision

The entry file of a feature's browser tests calls **`expect.hasAssertions()` in a `beforeEach`**. **Every test asserts**, and every `userEvent.*`, `findBy*`, `waitFor` and `waitForRequest` is **awaited**.

## Consequences

- A test that checks nothing fails, which is the point.
- A test that only checks that nothing throws asserts it explicitly (`expect(...).toBeInTheDocument()` on the rendered root).
- The rule is enforced by the runner and in review.

## When to Amend

- The runner enforces assertions by configuration; the call then goes.
