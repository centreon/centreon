# Phase 0 — migrating Cypress component tests to Rstest (jsdom): feasibility

Goal: prove (or disprove) that **real app component tests** — not leaf
components, but the ones with providers + API interception that make Cypress CT
slow — can run under **Rstest jsdom**, and measure the speed difference.

## Harness built (`rstest-poc/app/`)

The Rstest equivalent of Cypress' `cy.mount` + `cy.interceptAPIRequest` /
`cy.waitForRequest`:

- **`render.tsx`** — `renderApp()` wraps the component in the same providers the
  app needs at runtime: `@centreon/ui` `ThemeProvider` (MUI), a fresh React
  Query client, and a Jotai store. (Cypress' `cy.mount` only wraps the theme.)
- **`server.ts`** — MSW **node** server with `interceptApiRequest()` /
  `waitForRequest()`. This mirrors `cy.interceptAPIRequest` (itself MSW-based via
  cypress-msw-interceptor) and captures the outgoing request so a test can assert
  its payload — the equivalent of `cy.waitForRequest(...).then(({request}) => …)`.
- **`app.setup.ts`** — jest-dom matchers, jsdom polyfills (`ResizeObserver`,
  `matchMedia`), i18n init, MSW lifecycle, Testing Library cleanup.

Run: `pnpm rstest:app`.

## Result — hardest pattern works

Ported `AddCommentForm.cypress.spec.tsx` (a real `www/front_src` component:
React Query **mutation** via `customFetch`, MSW interception, and an assertion
on the **outgoing request body**). It passes under Rstest jsdom, with the
`cy.makeSnapshot()` visual step dropped (visual belongs in Chromatic/Playwright).

### Speed, same spec, 1 test

| Runner | Environment | Wall-clock |
| --- | --- | --- |
| **Cypress CT** | real browser (electron) + Rspack bundle + snapshot | **~19.5 s** |
| **Rstest jsdom** | jsdom + Rspack bundle | **~2.2 s** |

→ **~9× faster** on a representative real app spec (data + API mock + request
assertion). For functional/interaction tests (no visual assertion), jsdom is
sufficient and the Cypress per-spec startup cost disappears.

## What this validates / what's left

- ✅ The **hardest pattern** (providers + React Query + MSW + request-payload
  assertion) is feasible under Rstest jsdom, and is dramatically faster.
- ✅ The shared harness can be rebuilt without the `jest-fetch-mock` coupling
  that hangs (see `README.md` finding #5).
- ⚠️ Only **one** representative spec was ported here — enough for a go/no-go,
  not a full validation. Next (Phase 0b): port ~10–15 specs covering the other
  common shapes (GET + list + **debounced search with query-param assertion**,
  routing, Jotai-driven state). The list/search shape needs the MSW helper to
  discriminate by query param (small extension of `server.ts`).
- ⚠️ Rstest stays **pre-1.0** (0.10.x). Gate the bulk migration on its 1.0 or a
  clean Phase 0b.

## Recommendation

Green light to continue **in stages**: (1) finish Phase 0b on ~10–15 specs;
(2) migrate the functional bulk to Rstest jsdom; (3) move visual coverage to
Chromatic / Playwright `toHaveScreenshot`; (4) keep a small real-browser suite
(Cypress now, Rstest Browser Mode after 1.0). Do **not** big-bang on 0.10.x.
