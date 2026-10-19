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

## Phase 0b — broader batch + harness extensions

Ported a batch of real `www/front_src` component tests covering the common
shapes. **9 spec files / 18 tests pass** (`pnpm rstest:app`, ~3.5 s):

| Spec | Shape |
| --- | --- |
| `AddCommentForm` | React Query **mutation** + MSW + request-body assertion |
| `NotificationsFilter` | **list + debounced search** + query-param assertion |
| `AuthenticationDenied` | render-only fallback page |
| `DashboardsFilter` | **Jotai state** (store) + search input + clear |
| `WidgetWarning` | **Formik** + Jotai state |
| `TextWidget` | dashboard widget render |
| `AddButton` | button interaction (text, icon, disabled, aria, callback) |
| `TimeInput` | **MUI dropdown/select** — open + pick an option (portal) |
| `BreadcrumbTrail` | **routing** (BrowserRouter + `useLocation` spy + fixtures) |

The harness setup also extends the **dayjs plugins** (duration, utc, timezone…)
that components expect, mirroring `setupTest.js`.

One Cypress test could **not** move to jsdom and was deliberately skipped: the
BreadcrumbTrail "hover reveals copy icon (CSS opacity) then writes to clipboard"
test — CSS `:hover` and the clipboard are real-browser concerns (Browser Mode /
Chromatic), not jsdom. This is the expected jsdom boundary, not a harness gap.

Harness extensions this required:
- `server.ts`: query-param discrimination (`query`), full request **history**
  (`getRequests`) and `verifyRequestQueries` — the equivalent of
  `cy.waitForRequestAndVerifyQueries`.
- **Relative-URL fetch shim** (`app.setup.ts`): the app's `customFetch` issues
  *relative* URLs (`./api/...`). A real browser resolves them against the page
  origin; Node's `fetch` throws "Failed to parse URL". The harness wraps fetch
  (after MSW patches it) to absolutise relative URLs. **This is the single most
  important finding for a jsdom migration** — without it, any component that
  fetches a relative endpoint fails.

## What this validates / what's left

- ✅ The **two hardest patterns** are feasible under Rstest jsdom and much
  faster: (a) providers + React Query **mutation** + MSW + request-payload
  assertion (AddCommentForm); (b) **list + debounced search** + query-param
  assertion (NotificationsFilter).
- ✅ The shared harness can be rebuilt without the `jest-fetch-mock` coupling
  that hangs (see `README.md` finding #5), and now handles MSW interception,
  request history and the relative-URL gap.
- ✅ **9 spec files / 18 tests** ported across **all** the common shapes
  (mutation, list + search, render, Jotai state, Formik, widget, interaction,
  MUI dropdown, routing) — all green. The harness handles the hard parts;
  remaining migration is **mechanical**.
- ✅ Confirmed jsdom boundary: CSS `:hover`/opacity, clipboard and visual
  snapshots stay in Browser Mode / Chromatic (one BreadcrumbTrail test skipped).
- ⚠️ Rstest stays **pre-1.0** (0.10.x). Gate the bulk migration on its 1.0 or a
  fuller Phase 0b.

## Recommendation

Green light to continue **in stages**: (1) finish Phase 0b on ~10–15 specs;
(2) migrate the functional bulk to Rstest jsdom; (3) move visual coverage to
Chromatic / Playwright `toHaveScreenshot`; (4) keep a small real-browser suite
(Cypress now, Rstest Browser Mode after 1.0). Do **not** big-bang on 0.10.x.
