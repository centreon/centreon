# Playwright component testing

Proof of concept of the Playwright **story gallery** model (1.62+, on 1.63), migrating a few
Cypress component tests (`*.cypress.spec.tsx`). It runs alongside the Cypress
component suite — nothing is removed yet.

## Run

```bash
node_modules/.bin/playwright test --project=components
node_modules/.bin/playwright test --project=components --ui
node_modules/.bin/playwright test --project=components --update-snapshots
node playwright/generate-stories.mjs && node_modules/.bin/tsc -p tests/components
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
  `update()` reconciles instead of remounting. App-wide setup lives here, like
  the app entry and `cy.mount`: `ThemeProvider`, `CssBaseline`, the dayjs
  plugins of `Main/index.tsx` and an i18next instance without resources (labels
  render as their English keys). The theme follows the emulated color scheme.
- **The `mount` fixture** — built into `@playwright/test`, no extra package. It
  navigates to the gallery (`baseURL`), calls `window.mount()` and returns a
  locator scoped to the gallery root.

Story ids are the path under `www/front_src/src` without the `.story.tsx`
extension, plus the export name. Any unique suffix resolves too, so specs use
short ids like `Inputs/Warning/WithLabel`.

`playwright/generate-stories.mjs` writes `playwright/stories.d.ts`, which
augments the `Stories` interface of `@playwright/test` (1.63) with every full id
and unambiguous suffix: `mount('Inputs/Warning/WithLabel', { label })`
autocompletes the id and type-checks the props without importing the story.
Rerun it after adding, renaming or moving a story; `tests/components/tsconfig.json`
type-checks the specs, the stories and the registry together.

### Shared fixtures

Specs import `test` and `expect` from `tests/components/fixtures.ts`:

- `mockApi({ endpoint, method, response, status })` replaces
  `cy.interceptAPIRequest`: it takes the endpoint constant of the app as is and
  returns the JSON bodies it received.
- `expectThemedScreenshot(locator, name)` captures `name-light` and `name-dark`,
  the convention of the Cypress component suite.
- An automatic fixture fails the test when React reported an error after
  `mount()`; `mount()` itself rejects with the React error when the story throws
  while rendering or in an effect.

### Themes, clock and viewport

- `test.use({ colorScheme: 'dark' })` or `page.emulateMedia({ colorScheme })`
  switches the theme: the gallery sets `userAtom.themeMode` and the Tailwind
  `dark` class, like `Main/useUser.ts`.
- Stories that read app-wide atoms hydrate them in the default Jotai store with
  `useHydrateAtoms`, the one the gallery `ThemeProvider` reads.
- `page.clock.setFixedTime()` before `mount()` replaces `cy.clock()`.

## What the migrated specs demonstrate

| Spec | Pattern |
| --- | --- |
| `tests/components/authenticationDenied.spec.ts` | Plain rendering plus `toHaveScreenshot()`, replacing `cy.makeSnapshot()` |
| `tests/components/dashboardsFilter.spec.ts` | Jotai provider owned by the story; the debounced atom value is recorded into a hidden input and asserted with the retrying `toHaveValue()` |
| `tests/components/widgetWarningField.spec.ts` | Formik + Jotai providers in the story, per-test props via `mount(id, props)`, and `update()` for a prop transition without remounting |
| `tests/components/addCommentForm.spec.ts` | `mockApi()` replacing `cy.interceptAPIRequest`, request body asserted, two callbacks recorded by the story, and a screenshot of a portalled dialog |
| `tests/components/about.spec.ts` | Frozen clock, app-wide atoms hydrated in the default store, light and dark screenshots, dark-only assertions with `test.use({ colorScheme })` |
| `tests/components/timeInput.spec.ts` | Story defaults overridden by serializable props, `onChange` recorded by the story, portalled listbox options, data-driven tests |
| `tests/components/searchHelp.spec.ts` | Platform variant (cloud / on-premise) as a story prop, portalled tooltip asserted and captured |
| `tests/components/breadcrumbTrail.spec.ts` | `MemoryRouter` replacing a `useLocation` stub, JSON fixtures shared with Cypress, hover, real clipboard instead of a `writeText` stub |

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
- Register `mockApi()` mocks **before** `mount()`, since mounting navigates.
- Prefer `getByRole(role, { name })`: MUI selects are comboboxes named through
  `aria-labelledby`, which `getByLabel` does not resolve.
- Dates are formatted in the browser, so the project pins `timezoneId: 'UTC'`
  — the equivalent of the `TZ=Etc/UTC` the Cypress scripts export.
- Baselines live in `playwright/visual-testing-snapshots/` and are committed —
  not `screenshots/`, which the repository `.gitignore` excludes;
  `playwright/results/` is ignored.

## Known gaps

- `mount()` still accepts any string next to the registry ids, so a stale id
  fails at runtime; regenerating the registry keeps autocompletion and props
  typing accurate.
- Not wired into CI yet, which is why the migrated Cypress specs still exist.
