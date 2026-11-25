# ADR-0028: Write every new `www/front_src` component test as an rstest browser test, never a Cypress spec

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Component tests of `www/front_src` were Cypress component specs (`*.cypress.spec.tsx`), one CI job per spec, with visual snapshots. [#12099](https://github.com/centreon/centreon/pull/12099) introduced [browser tests](../glossary.md#browser-test): rstest in a real Chromium, with React Testing Library, `user-event` and MSW, configured by `rstest.config.ts` and a shared setup in `test-setup/`. The hosts configuration page was the first feature tested that way, and `Configuration/Hosts/Specs/` is the canonical shape: an `initialize.tsx`, one file of `describe` blocks per area, one `*.browser.test.tsx` entry.

At the time of writing, `www/front_src` and `packages/ui` hold 40 browser test files and 107 Cypress specs. With both runners available, a new feature copies whichever its neighbour uses, and the migration never ends.

Two alternatives were weighed:

- **Keep Cypress for component tests.** It lost: one job per spec in CI, retries that hide a flaky assertion, uncaught errors swallowed by default, and screenshots as the main assertion.
- **Jest with jsdom for components.** It lost: no real browser, so layout, portals and user events differ from what a user gets.

Settled in [#12141](https://github.com/centreon/centreon/pull/12141).

## Decision

**Every new component test is an rstest browser test** (`*.browser.test.tsx`), following the shape of `Configuration/Hosts/Specs/`. **Never add a Cypress spec.** A Cypress spec that a change touches is migrated when feasible.

## Consequences

- Commands: `pnpm rstest:run <path>` (`-t '<name>'` for one test), `pnpm rstest:ui <path>` to debug, `pnpm rstest:install` once for Chromium. `pnpm type-check` covers the browser tests.
- An unhandled promise rejection or an uncaught error fails the whole file: the code is fixed, not the error filtered.
- The remaining Cypress specs run until migrated; `pnpm cypress:run --spec <path>` runs one.
- The module frontends of `centreon-modules` cannot run browser tests until `test-setup/` is published as a package; they follow their own rules meanwhile.
- The rule is enforced in review.

## When to Amend

- `test-setup/` is published; the module frontends can then adopt the record.
- rstest is replaced.
