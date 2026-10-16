# Rstest proof of concept

Evaluates [Rstest](https://rstest.rs/) (the Rspack-powered, Jest-compatible test
runner) as a possible future for Centreon's React component tests — instead of
Jest (jsdom) and/or Cypress component testing.

**Why Rstest specifically:** Centreon builds with **Rspack**, and its Cypress
component tests already bundle with Rspack (`cypress-rspack-dev-server`). Rstest
is the only modern runner that keeps that same engine, so components are bundled
in tests exactly as in production — no Vite/jest transform divergence (which
Vitest Browser Mode or Playwright CT would introduce).

## What this POC contains

| File | Purpose |
| --- | --- |
| `rstest.config.ts` | jsdom config: `pluginReact()` + jsdom environment |
| `rstest.browser.config.ts` | **Browser Mode** config: real Chromium via Playwright |
| `rstest.setup.ts` / `browser.setup.ts` | jest-dom matchers, RTL cleanup (+ jsdom polyfills) |
| `testRender.tsx` | MUI ThemeProvider render helper (no jest-fetch-mock) |
| `Tabs.browser.spec.tsx` | **Browser Mode** port of the `Tabs` Cypress component test |
| `Section.rstest.spec.tsx` | Real `@centreon/ui` SectionPanel: render + interaction |
| `DialogDuplicate.rstest.spec.tsx` | Real MUI 7 dialog: controlled input, disabled state, mock callbacks |
| `Wizard.rstest.spec.tsx` | MUI multi-step wizard: async Next/Previous navigation |
| `WizardActionsBar.rstest.spec.tsx` | MUI Wizard actions bar: labels, disabled state, callbacks |
| `LocaleDateTimeFormat.rstest.spec.tsx` | Hook with Jotai state + dayjs (locale fallback) |
| `LocaleDateTimeFormatFull.rstest.spec.tsx` | Hook: date/time/ISO/duration formatting |

Run it (from `centreon/`):

```bash
pnpm rstest
```

Result: **20/20 tests pass** across 6 spec files (MUI panel/dialog/wizard/actions
+ Jotai/dayjs hooks), against the real components bundled by Rspack.

In CI, a dedicated `rstest-component-test` job runs `pnpm rstest` (no docker
stack needed — component tests are isolated).

## Migration effort from Jest (low)

The specs are ports of the existing Jest tests. The only changes:

- import test globals from `@rstest/core` (`describe`, `it`, `expect`, `rstest`);
- `jest.fn()` → `rstest.fn()`, `toBeCalledWith` → `toHaveBeenCalledWith`;
- one-time setup wiring (see below). Assertions and Testing Library usage are
  unchanged.

## Findings

1. **jest-dom matchers must be extended manually** — `expect.extend(matchers)`
   from `@testing-library/jest-dom/matchers` in the setup file (same as Vitest).
2. **Testing Library cleanup must be wired** — RTL only auto-registers cleanup
   when it detects the runner's global `afterEach`. Under Rstest, add
   `afterEach(cleanup)` in setup, otherwise rendered trees accumulate and
   queries like `getByDisplayValue` match duplicates across tests.
3. **Real CSS / real bundling changes some behaviours** — because Rspack bundles
   the actual component (real MUI transitions, real CSS) instead of Jest's
   stubbed CSS, the secondary-panel of `SectionPanel` is mounted-but-hidden from
   the start. The Jest test asserted it was *absent* before the click; the POC
   asserts the user-visible outcome instead. This is a fidelity gain, but
   existing Jest assertions that rely on stubbed CSS may need review on
   migration.
4. **Workspace `minimumReleaseAge` (7 days)** blocks the very latest Rstest, so
   `@rstest/core` is pinned to `0.10.3` (and `@rsbuild/plugin-react` to `2.0.1`).
5. **Existing specs do NOT run unmodified — and several hang (Rstest 0.10.x).**
   Attempting to point Rstest at the existing Jest specs failed in three ways,
   which is why the comparison set had to be hand-ported and stayed at 5 files:
   - `jest` is not a runtime global under Rstest (only `rstest`); the specs and
     `setupTest.js` use `jest.*`. A `jest = rstest` shim helps but is not enough.
   - The shared `packages/ui/test/testRenderer` imports `jest-fetch-mock`, whose
     load **hangs** under Rstest — so every spec using it hangs. Migration
     therefore requires reworking the shared test harness, not just the specs.
   - Pure-logic `.ts` specs (e.g. `buildListingEndpoint`, `timeSeries`) also
     **hang** on import under Rstest 0.10.x, in both jsdom and node environments.
   These are maturity rough edges of a pre-1.0 tool.

## Speed (benchmark on the same 6 specs)

| Runner | Files | Tests | Wall-clock |
| --- | --- | --- | --- |
| Jest (`@swc/jest`, jsdom) | 6 | 20 | **~0.5–1.2 s** |
| Rstest (Rspack, jsdom) | 6 | 20 | **~2.3 s warm / ~5.4 s cold** |

At this scale Jest is **~3–5× faster**: Rstest pays a fixed Rspack
bundling-startup cost (note the cold/warm gap) that dominates for a handful of
files. (Jest does **not** bundle — it transforms modules on demand with
`@swc/jest` and stubs CSS/assets — which is why it wins at small scale but is
less faithful to production.) The speed benefit
of Rspack/Vite-based runners only appears on large suites (parallelism,
incremental rebuilds) — but we could **not** build a large benchmark here
because the bigger/existing specs hang (finding #5). **Conclusion: do not adopt
Rstest for raw speed today; its value is Rspack fidelity + toolchain
consolidation, and it needs to mature (1.0) before a wholesale migration.**

## The real comparison: Cypress CT vs Rstest Browser Mode

Component tests at Centreon are mostly **Cypress** (~130 specs) running in a
**real browser**, Rspack-bundled, with API interception and visual snapshots.
The like-for-like alternative is **Rstest Browser Mode** (real Chromium via
Playwright), not jsdom. Same component (`Tabs`), same 2 tests, both real browser:

| Approach | Environment | 2 tests (1 spec) | Notes |
| --- | --- | --- | --- |
| **Cypress CT** | real browser (electron) | **~34.7 s** | + Rspack bundle + visual snapshot (`cy.makeSnapshot`) |
| **Rstest Browser Mode** | real Chromium (Playwright) | **~1.4 s** | experimental 0.10.x; visual snapshot dropped |

→ For one spec, Rstest Browser Mode is **~20–25× faster** than Cypress CT
(`pnpm rstest:browser` vs `cypress run --component`). Caveats that keep this from
being a verdict:

- Cypress' per-spec wall-clock is dominated by **startup** (dev server, browser,
  snapshot plugin); in CI those costs are **parallelised/amortised** across the
  130 specs, so the real-suite gap is smaller than 25×.
- The Rstest port **drops visual regression** (`cy.makeSnapshot`) and the rich
  `cy.*` ecosystem (API interception, time-travel, retries). A fair replacement
  must re-implement those (Rstest/Playwright have equivalents, but it's work).
- Rstest Browser Mode is **experimental** (0.10.x) and the React render path is
  undocumented — here it works via `@testing-library/react` rendering into the
  real DOM, queried with Testing Library / `page` locators.

So the **direction** is very promising (Rspack-native, real browser, far less
per-spec overhead), but it is **not production-ready** today.

## Recommendation

- **Now:** keep Jest (jsdom) + Cypress CT (Rspack). Rstest is **pre-1.0**
  (v0.10.x) and its Browser Mode is experimental — too young for the whole
  suite.
- **Later:** once Rstest reaches 1.0, pilot it as the Jest replacement
  (Jest-compatible API → low-friction), then evaluate its Playwright-backed
  Browser Mode as a Cypress CT replacement. Endgame: a single Rstack toolchain
  (Rspack build + Rstest component tests + Playwright E2E).
- **Avoid** Vitest Browser Mode (introduces Vite, a foreign bundler here) and
  Playwright CT (still experimental).

This POC is intentionally isolated under `rstest-poc/` and adds two dev
dependencies; it can be removed cleanly if not pursued.
