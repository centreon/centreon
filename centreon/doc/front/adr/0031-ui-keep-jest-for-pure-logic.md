# ADR-0031: Keep Jest for pure logic in `packages/ui`, never for a component

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` has two runners: Jest (`pnpm test`, `*.test.ts(x)`) and rstest for [browser tests](../glossary.md#browser-test). The package's pure logic, `buildListingEndpoint` and its `index.test.ts` for instance, needs no browser. A component does: the package's components are tested in a real Chromium since [#12148](https://github.com/centreon/centreon/pull/12148), and the package README that described a migration from Jest to Cypress is out of date.

One alternative was weighed: **one runner for everything, rstest.** It lost for now: Jest is wired in CI and its tests are fast.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

**Jest tests pure logic**: endpoint builders, decoders, utilities, hooks without rendering. **A component is tested in a browser test, never in Jest.**

## Consequences

- Jest's configuration skips the browser tests, and Biome lints them.
- The rule is enforced in review.

## When to Amend

- rstest's node mode replaces Jest for pure logic.
