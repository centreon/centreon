# ADR-0013: Lazy-load every route of `www/front_src`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Centreon Web is one single-page application whose pages are declared in `www/front_src/src/reactRoutes/index.ts`, each loaded with `React.lazy(() => import(...))`: 18 routes at the time of writing. rspack splits each lazy import into its own chunk, so a user downloads a page when they open it, and `rspack.config.js` warns when an asset exceeds 300 KB (`performance.maxAssetSize`).

One alternative was weighed: **import pages eagerly and let the bundler decide.** It lost: without a dynamic import there is one entry chunk, and every page's code is downloaded by every user on the first load.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**Declare every route with `React.lazy` and a dynamic `import()`.** A page component is never imported statically from the routes file.

## Consequences

- A page is a chunk; its component default-exports, as `React.lazy` requires.
- The 300 KB asset budget is a warning, not a gate: a page that exceeds it is split further rather than ignored.
- The rule is enforced in review.

## When to Amend

- The application moves to a router that handles code splitting on its own.
