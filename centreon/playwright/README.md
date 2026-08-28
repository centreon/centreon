# Playwright component testing

Proof of concept of the Playwright 1.62 **story gallery** model, migrating a few
Cypress component tests (`*.cypress.spec.tsx`). It runs alongside the Cypress
component suite — nothing is removed yet.

## Run

```bash
node_modules/.bin/playwright test --project=components
node_modules/.bin/playwright test --project=components --ui
node_modules/.bin/playwright test --project=components --update-snapshots
```

The equivalent `pnpm playwright:ct*` scripts exist in `package.json` but `pnpm run`
currently fails on this checkout: the `allowBuilds` entries of
`pnpm-workspace.yaml` are unresolved `set this to true or false` placeholders, so
pnpm's pre-run dependency check exits non-zero. That is pre-existing on
`develop` and affects every script, not only these.

`playwright.config.ts` starts the gallery dev server itself
(`rspack.config.gallery.js`, port 9093) and reuses a running one outside CI.
Cold start of the dev server is ~2 min; each test then runs in 1-3 s.

## How it works

Three pieces, per the Playwright model:

- **Stories** — `*.story.tsx` files next to the component. Each named export is
  one scenario and owns everything the component needs: providers, mock data,
  state, callbacks. Tests own only interactions and assertions.
- **The gallery** — `playwright/gallery/`, a page served by our own rspack dev
  server. It discovers the story files, exposes `window.mount({ story, props })`
  and `window.unmount()`, renders into `#root`, and reuses the React root so
  `update()` reconciles instead of remounting. The wrapping providers
  (`ThemeProvider`, `CssBaseline`) live here, mirroring `cy.mount`.
- **The `mount` fixture** — built into `@playwright/test`, no extra package. It
  navigates to the gallery (`baseURL`), calls `window.mount()` and returns a
  locator scoped to the gallery root.

Story ids are the path under `www/front_src/src` without the `.story.tsx`
extension, plus the export name. Any unique suffix resolves too, so specs use
short ids like `Inputs/Warning/WithLabel`.

## What the migrated specs demonstrate

| Spec | Pattern |
| --- | --- |
| `tests/components/authenticationDenied.spec.ts` | Plain rendering plus `toHaveScreenshot()`, replacing `cy.makeSnapshot()` |
| `tests/components/dashboardsFilter.spec.ts` | Jotai provider owned by the story; the debounced atom value is recorded into a hidden input and asserted with the retrying `toHaveValue()` |
| `tests/components/widgetWarningField.spec.ts` | Formik + Jotai providers in the story, per-test props via `mount(id, props)`, and `update()` for a prop transition without remounting |
| `tests/components/addCommentForm.spec.ts` | `page.route()` replacing `cy.interceptAPIRequest`, request body asserted from the route handler, two callbacks recorded by the story, and a screenshot of a portalled dialog |

## Conventions

- One export per scenario; prefer a new export over parameterizing an existing
  one, except for plain serializable props.
- Callbacks never cross the Node/browser boundary: the story provides them and
  records the observable outcome into a hidden `<input data-testid=...>`.
- Screenshot the locator returned by `mount()`, never the page.
- **Portalled content escapes the mount root.** MUI `Dialog`, `Popover`,
  `Tooltip` and friends render into `document.body`, so query them from `page`,
  not from the locator `mount()` returns — and screenshot the dialog itself.
  The story's hidden recording inputs stay inside the root.
- Register `page.route()` mocks **before** `mount()`, since mounting navigates.
- **Centreon endpoints are relative** (`./api/latest/...`), resolved against the
  gallery URL. Route globs must drop the leading `./`:
  `` `**/${endpoint.replace(/^\.\//, '')}` ``.
- Dates are formatted in the browser, so the project pins `timezoneId: 'UTC'`
  — the equivalent of the `TZ=Etc/UTC` the Cypress scripts export.
- Baselines live in `playwright/visual-testing-snapshots/` and are committed —
  not `screenshots/`, which the repository `.gitignore` excludes;
  `playwright/results/` is ignored.

## Known gaps

- Story ids are strings: renaming a story breaks specs at runtime, not at
  compile time. `mount<typeof Story>` at least type-checks the props.
- The gallery surfaces synchronous render errors only; an error thrown in an
  effect shows up as a failed assertion rather than a rejected `mount()`.
- Not wired into CI yet, which is why the three Cypress specs still exist.
