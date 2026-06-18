# Component tests with Playwright (proof of concept)

Proof of concept for **component-level visual snapshot** tests with
[Playwright Component Testing](https://playwright.dev/docs/test-components)
(`@playwright/experimental-ct-react`), as an alternative to the Cypress
component tests (`*.cypress.spec.tsx` + `cy.makeSnapshot()`).

Unlike the end-to-end suite (`tests/e2e-playwright`, a standalone project that
drives the running app over HTTP), component tests **mount real React
components in a browser** via Vite, so this setup lives in the frontend
workspace where the component sources and their dependencies (MUI, …) resolve.

## Layout

```
centreon/
├── playwright-ct.config.ts          # CT config (testMatch *.pw.spec.tsx, snapshot path)
├── playwright/
│   ├── index.html                   # CT mount host page
│   └── index.tsx                    # beforeMount hook: MUI theme (Centreon palette) + i18n
├── www/front_src/src/**/X.pw.spec.tsx   # specs, colocated with the component
└── tests/component-playwright/
    └── __snapshots__/<spec>/<name>-chromium-linux.png   # visual baselines
```

## Running

```bash
cd centreon
pnpm exec playwright install chromium   # once
pnpm test:ct                            # run (compare against baselines)
pnpm test:ct:update                     # (re)generate the PNG baselines
pnpm test:ct:ui                         # interactive UI mode
```

## Conventions

- Specs are colocated with the component as `*.pw.spec.tsx` (mirroring the
  Cypress `*.cypress.spec.tsx`), so imports stay relative and short.
- Visual assertions use `await expect(component).toHaveScreenshot('name.png')`,
  the equivalent of the Cypress `cy.makeSnapshot()` step. Baselines are captured
  in **both light and dark** themes (as the Cypress suite does), centralised
  under `tests/component-playwright/__snapshots__` and keyed by browser/platform.
- Providers cannot be wrapped inside a spec in Playwright CT; they are injected
  by the `beforeMount` hook in `playwright/index.tsx`. The hook builds an MUI
  theme from the **Centreon palette** (`getPalette`) so components that reference
  custom theme entries (e.g. `theme.palette.chip`) render correctly — reusing
  only the palette, not the full `@centreon/ui` ThemeProvider, to keep its fonts
  and Tailwind stylesheet out of the Vite build. The mode is chosen per test via
  `mount(<Cmp/>, { hooksConfig: { theme: 'dark' } })`.
- i18n is initialised with no catalogue: Centreon labels are their own English
  keys, so `t('Add a host')` resolves to "Add a host".

## Migrated so far

| Component | From | Covers |
| --- | --- | --- |
| `AddButton` | `AddButton.cypress.spec.tsx` | render, disabled state, light/dark snapshots |
| `Tabs` | `Tabs.cypress.spec.tsx` | render, tab switching, light/dark snapshots |

> Baselines are platform-specific PNGs (`*-chromium-linux.png`); regenerate them
> on the same platform CI uses (Linux) with `pnpm test:ct:update`.
