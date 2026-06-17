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
| `rstest.config.ts` | Minimal config: `pluginReact()` + jsdom environment |
| `rstest.setup.ts` | jest-dom matchers, RTL cleanup, jsdom polyfills |
| `testRender.tsx` | MUI ThemeProvider render helper (no jest-fetch-mock) |
| `Section.rstest.spec.tsx` | Real `@centreon/ui` SectionPanel: render + interaction |
| `DialogDuplicate.rstest.spec.tsx` | Real MUI 7 dialog: controlled input, disabled state, mock callbacks |
| `Wizard.rstest.spec.tsx` | MUI multi-step wizard: async Next/Previous navigation |
| `LocaleDateTimeFormat.rstest.spec.tsx` | A hook with Jotai state + dayjs (the fast, non-styled "logic" layer) |

Run it (from `centreon/`):

```bash
pnpm rstest
```

Result: **9/9 tests pass** across 4 spec files (MUI dialog/panel/wizard + a
Jotai/dayjs hook), against the real components bundled by Rspack.

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

## Speed

On this micro-sample, Rstest (~1.7 s, 5 tests) was **not faster** than Jest
(~1.0 s, the original specs) — Rspack pays a fixed bundling-startup cost that
dominates at small scale. The speed benefit of Rspack/Vite-based runners shows
on large suites (parallelism, incremental rebuilds), so a fair speed comparison
needs a much larger sample. **Do not adopt Rstest for raw speed at this stage —
adopt it (later) for Rspack fidelity and toolchain consolidation.**

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
