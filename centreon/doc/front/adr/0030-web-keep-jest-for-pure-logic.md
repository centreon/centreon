# ADR-0030: Keep Jest for pure logic in `www/front_src`, never for a component

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`www/front_src` has two runners: Jest (`pnpm test`, `*.test.ts(x)`, 82 files at the time of writing) and rstest for [browser tests](../glossary.md#browser-test). An endpoint builder, a decoder, a formatter or a reducer needs no browser: Jest runs it in milliseconds. A component does: a Jest test with jsdom renders without layout, real events or portals, and the browser test already covers it in a real Chromium.

One alternative was weighed: **one runner for everything, rstest.** It lost for now: Jest is wired in CI and its tests are fast; moving them buys nothing.

Settled in [#12141](https://github.com/centreon/centreon/pull/12141).

## Decision

**Jest tests pure logic**: endpoint builders, decoders, utilities, hooks without rendering. **A component is tested in a browser test, never in Jest.**

## Consequences

- A Jest test file is `*.test.ts` next to the logic it tests; a component test is `*.browser.test.tsx` in the feature's `Specs/`.
- Jest's configuration excludes the browser tests.
- The rule is enforced in review.

## When to Amend

- rstest's node mode replaces Jest for pure logic.
