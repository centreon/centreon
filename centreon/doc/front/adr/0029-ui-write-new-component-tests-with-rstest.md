# ADR-0029: Write every new `packages/ui` component test as an rstest browser test, never a Cypress spec

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

[#12148](https://github.com/centreon/centreon/pull/12148) moved the component tests of `packages/ui` from Cypress to rstest [browser tests](../glossary.md#browser-test): 27 specs, 184 tests with the same names and nesting, their visual snapshots replaced by explicit assertions, and 27 Cypress jobs (42 to 45 minutes of job time) replaced by one rstest job of about two minutes. `packages/ui/rstest.config.ts` reuses the application's configuration, and the `centreon-ui` workflow runs the rstest job with its coverage merged into the coverage gate.

The 11 chart specs (`Graph/`, `components/Zoom`) stayed on Cypress: their assertions are visual, and the visual testing approach for charts is not decided.

Two alternatives were weighed:

- **Keep Cypress for the package.** It lost: the job time and billable minutes measured in #12148, and a runner that differs from the applications'.
- **Migrate the chart specs too, with HTML snapshots.** It lost for now: an SVG chart's HTML snapshot breaks on any coordinate change and proves nothing visually.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

**Every new component test of the package is an rstest browser test** (`*.browser.test.tsx`), run from `packages/ui` with `pnpm rstest:run <path>`. **Never add a Cypress spec.** Exception: a chart of `Graph/` or `components/Zoom` keeps its Cypress spec until the visual testing approach is decided.

## Consequences

- A published component ships with a browser test that asserts what its baseline screenshot showed.
- Jest skips the browser tests and Biome lints them.
- The rule is enforced in review.

## When to Amend

- The visual testing approach for charts is decided; the exception then goes.
- rstest is replaced.
