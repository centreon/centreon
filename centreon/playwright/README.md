# Playwright component testing

Migration of the Cypress component tests (`*.cypress.spec.tsx`) to the
Playwright **story gallery** model (1.62+, on 1.63). It runs alongside the
Cypress component suite, nothing is removed yet: see [Migration status](#migration-status).

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
Cold start of the dev server is ~2 min; each test then runs in 1-3 s. While
writing specs, keep it running with
`node_modules/.bin/rspack serve -c ./rspack.config.gallery.js`, and **restart it
after adding a story file**: the watcher does not always pick new files up
(`Unknown story` errors).

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
  plugins and locales of `Main/index.tsx`, and an i18next instance without
  resources nor key separators (labels render as their English keys). The theme
  follows the emulated color scheme.
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

- `mockApi({ endpoint, method, response, status, delay })` replaces
  `cy.interceptAPIRequest`. It takes the endpoint constant of the app as is
  (relative `./api/...`, query string ignored), answers with JSON or with a
  function of the request URL, and returns the `bodies` and `urls` it received.
  The latest registered mock wins, so a test can override a `beforeEach` one.
  It matches the end of the URL path, so pass API Platform endpoints without
  their leading slash (`'configuration/hosts'`, not `'/configuration/hosts'`).
  `MockApi` and `MockedEndpoint` are exported for shared mock modules.
- `loadStoryData(storyId, globalName)` mounts a data story and returns what it
  published on `window` (see below).
- `expectThemedScreenshot(target, name)` captures `name-light` and `name-dark`,
  the convention of the Cypress component suite. `target` is the mount locator,
  a portalled element, or the page for fixed-position components.
- An automatic fixture fails the test when React reported an error after
  `mount()`; `mount()` itself rejects with the React error when the story throws
  while rendering or in an effect.

### Story helpers

- `WidgetPropertyStoryFrame` / `createWidgetPropertyStory(Input, defaults)` —
  the Jotai store and Formik form of the dashboard widget edition panel, driven
  by serializable props (`canEdit`, `isEditing`, `options`, `user`,
  `inputProps`…), with the form values recorded in `form-values`.
- `createWidgetStory(Widget)` — a dashboard widget mounted with its usual props,
  specs pass `panelData` and `panelOptions`.
- `LocationRecorder` (`playwright/gallery/storyHelpers.tsx`) — records the router
  location in `location`, so specs assert navigations instead of stubbing
  `useNavigate`. Stories use a `MemoryRouter` instead of stubbing `useLocation`.
- Hook and pure function stories expose an API on `window`
  (`useValidationSchema`, the search query language); specs call it with
  `page.evaluate()`. Pure modules that reach `@centreon/ui` cannot be imported
  in Node: it reads `document` at import.
- **Data stories** — test data modules that import `@centreon/ui`, SVGs or
  images are published on `window` by a `Data` export next to the story
  (`NotificationPanel`, `HostGroups`…); specs read them with `loadStoryData`.
  Data modules without such imports (`Hosts/Specs/utils.ts`, the Cypress JSON
  fixtures) are imported directly.
- **Shared API modules** — a page tested by several specs gets one module that
  registers all its mocks and returns them, plus its interaction helpers:
  `tests/components/hostsApi.ts` backs the five Hosts specs, as the Cypress
  `initialize.tsx` did.

### Themes, clock and viewport

- `test.use({ colorScheme: 'dark' })` or `page.emulateMedia({ colorScheme })`
  switches the theme: the gallery sets `userAtom.themeMode` and the Tailwind
  `dark` class, like `Main/useUser.ts`.
- Stories that read app-wide atoms hydrate them in the default Jotai store with
  `useHydrateAtoms`, the one the gallery `ThemeProvider` reads.
- `page.clock.setFixedTime()` before `mount()` replaces `cy.clock()`; use
  `page.clock.install()` + `runFor()` to drive timers.

## Conventions

- One export per scenario; prefer a new export over parameterizing an existing
  one, except for plain serializable props.
- Callbacks never cross the Node/browser boundary: the story provides them and
  records the observable outcome into a hidden `<input data-testid=...>`.
- **Portalled content escapes the mount root.** MUI `Dialog`, `Popover`,
  `Tooltip`, listboxes and snackbars render into `document.body`, so query them
  from `page`, not from the locator `mount()` returns.
- Register `mockApi()` mocks **before** `mount()`, since mounting navigates.
- Prefer `getByRole(role, { name })`: MUI selects are comboboxes named through
  `aria-labelledby`, which `getByLabel` does not resolve.
- Configuration form fields carry their `data-testid` on both the wrapper and
  the input (what `findAllByTestId(...).eq(1)` worked around): target
  `:is(input, textarea)[data-testid="..."]`.
- Assert request bodies with `toEqual`, or `toEqual(expect.objectContaining())`
  to check some keys only: `toMatchObject` also compares nested objects
  partially, which is looser than the Cypress `deep.equal`.
- Seed `Math.random` with `page.addInitScript` when fixtures are random, and
  drive dnd-kit sortables with the keyboard (Space, arrow, Space), waiting for
  `aria-pressed` on the handle before moving it.
- One themed screenshot per spec, on its most representative state; skip
  screenshots of content resized asynchronously (fluid typography) or caught
  mid-transition, assertions cover them.
- Dates are formatted in the browser, so the project pins `timezoneId: 'UTC'`
  — the equivalent of the `TZ=Etc/UTC` the Cypress scripts export.
- Baselines live in `playwright/visual-testing-snapshots/` and are committed —
  not `screenshots/`, which the repository `.gitignore` excludes;
  `playwright/results/` is ignored.

## Migration status

82 of the 97 Cypress component spec files of `www/front_src` are migrated;
their Cypress versions stay until the suite runs in CI. The 15 remaining ones
are the largest (600 to 1,150 lines, heavy API mocking): the Header suite
(including `CloudInstallCommand`), the Resources listing, details, filters and
actions, the dashboards listing and dashboard page, the widget edition modal,
the status grid (with and without BAM) and resources table widgets, the agent
configuration listing and the `ConfigurationBase` suite. The `packages/ui`
specs are out of scope.

## Findings

The typed stories and the stricter assertions surfaced issues the Cypress specs
did not catch:

- **Bug** — `AgentInitiated` passes `ChipProps` to the `@centreon/ui`
  autocomplete, which reads `chipProps`: the delete icon of a CMA token chip does
  nothing. The spec marks it with `test.fail()`.
- `useUpdateVaultConfiguration` resets the form to the saved values; the Cypress
  spec still expected the previous configuration.
- Props now required by components were missing from the Cypress mounts
  (`isInViewport`, `children`, `WidgetPropertyProps`), the web page widget no
  longer takes a `store`, and `DisplayType` reads options typed as
  `SelectEntry`.
- Several Cypress cases clicked around without any assertion; their migrated
  versions assert the outcome.
- `Widgets/widgets.ts` registers its test widgets only for Cypress component
  runs (`window.Cypress.testingType`); the gallery sets that flag for now.
- The resource access rules listing spec only passed in order: the bulk
  deletion confirmation case clicked a button enabled by the rows the previous
  case had selected in the shared store. Each migrated case selects its rows.
- The resource type select of the resource access rule modal has no
  accessible name: specs reach it through its label element.
- The additional connector update case expected the port `1000` after typing
  `100`, an artefact of Cypress typing into a number field; the migrated spec
  expects the value typed.

## Known gaps

- `mount()` still accepts any string next to the registry ids, so a stale id
  fails at runtime; regenerating the registry keeps autocompletion and props
  typing accurate.
- Not wired into CI yet, which is why the migrated Cypress specs still exist.
