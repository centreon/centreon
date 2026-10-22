---
paths:
  - "centreon/www/front_src/**"
  - "centreon/packages/**"
  - "centreon/package.json"
  - "centreon/biome.json"
  - "centreon/tsconfig*.json"
  - "centreon/{rspack,jest,cypress,rstest}.config*.{js,ts}"
  - "centreon/test-setup/**"
---

# Frontend (React) in centreon/centreon

For frontend and fullstack developers. Loaded only when working on frontend
files. Team-owned and reviewed like code: update it in the same PR as any
convention change. Personal notes go in `CLAUDE.local.md` (git-ignored).

## Layout

- `centreon/www/front_src/src/`: Centreon Web React app
- `centreon/packages/ui`: `@centreon/ui`, published to npm and consumed by the
  centreon-modules frontends. A breaking change there cascades.
- `centreon/packages/ui-context`, `centreon/packages/js-config` (shared Biome,
  TS, Jest, Cypress, rspack configs)
- `.github/workflows/web.yml` (app) and `centreon-ui.yml` (`packages/ui`): CI

## Commands

Run from `centreon/` (pnpm workspace root; CI uses pnpm 10 and Node 24):

| Command                          | Does                                         |
| -------------------------------- | -------------------------------------------- |
| `pnpm build` / `pnpm build:dev`  | rspack production / development build        |
| `pnpm centreon:watch`            | rspack dev server                            |
| `pnpm lint` / `pnpm lint:fix`    | Biome on `www/front_src`                     |
| `pnpm type-check`                | `tsc` on app and browser tests (runs in CI)  |
| `pnpm test`                      | Jest                                         |
| `pnpm rstest:run <path>`         | rstest browser tests (`-t '<name>'` for one) |
| `pnpm rstest:ui <path>`          | the same in a visible browser, to debug      |
| `pnpm cypress:run --spec <path>` | legacy Cypress component tests               |

First rstest run: `pnpm rstest:install` (Playwright Chromium).

`packages/ui` has the same scripts plus `storybook`; run them from that directory.

## Styling: migrating to Tailwind

We are migrating from `tss-react` (`makeStyles` in `*.styles.ts`) to Tailwind.
Most existing code still uses `makeStyles`; do not copy it.

1. New styling: Tailwind utilities in `className`, using the theme tokens from
   `packages/ui/src/ThemeProvider/tailwindcss.css` (Tailwind v4, no
   `tailwind.config`), not raw values.
2. Touching a component that uses `makeStyles`: migrate the styles you change
   to Tailwind in the same PR, and the whole component when feasible. Delete
   the `*.styles.ts` file once it is empty. Do not add new `makeStyles` rules.
3. Fallback, only for what Tailwind cannot express: MUI props, then
   `makeStyles` from `tss-react/mui`. Never `@mui/styles`.

Utilities override MUI by design (`ThemeProvider/index.tsx` orders the CSS
layers `…,mui,components,utilities`). If one seems not to apply, check the
computed style before falling back.

## Conventions

- Code style: Biome owns it (`centreon/biome.json`, extending
  `packages/js-config/biome/biome.json`). Do not restate or fight it.
- Canonical feature to copy: `www/front_src/src/Configuration/HostGroups`
  (built on `Configuration/ConfigurationBase`). Standalone page:
  `www/front_src/src/AgentConfiguration`.
- Feature layout: `api/endpoints.ts`, `api/decoders.ts`, and at the feature
  root `models.ts`, `atoms.ts`, `translatedLabels.ts`.
- Function components only (error boundaries excepted). Components are
  default exports (lazy routes and federated modules need them); hooks,
  atoms, models and labels are named exports. Routes are lazy-loaded.
- Data: `useFetchQuery` / `useMutationQuery` from `@centreon/ui` (TanStack
  Query over native `fetch`), always with a `JsonDecoder` decoder from
  `api/decoders.ts`. No new `useRequest`, axios or `useEffect` fetching.
- State: Jotai atoms in `atoms.ts`. No Redux, no new React Context.
- Forms: `Form` from `@centreon/ui` with an inputs hook (`useFormInputs.ts`,
  as in HostGroups) and `useValidationSchema.ts` (Yup); submit through
  `useMutationQuery`.
- Labels: English constants in `translatedLabels.ts`; `t()` only ever takes
  those constants, never a string literal.

## Tests

We are migrating component tests from Cypress to rstest (real Chromium,
Testing Library, MSW 2). **Every new component test is an rstest browser test
(`*.browser.test.tsx`); never add a Cypress spec.** Jest (`*.test.ts(x)`) stays
for pure logic. `packages/ui` is not migrated yet: it stays on Cypress for now.

Copy the shape of `Configuration/Hosts/Specs/`:

- `initialize.tsx`: sets up i18n, a Jotai store (permissions, feature flags),
  mocks every API call with `interceptAPIRequest` from `@test-setup/api` (with
  an `alias`), and renders with `render` from `@test-setup/render`. Mock
  responses go in `utils.ts`.
- `<Feature>.<Area>.tsx` files export `describe` blocks; one
  `<Feature>.browser.test.tsx` entry composes them and calls
  `expect.hasAssertions()` in a `beforeEach`.
- In a test: await every `userEvent.*`, `findBy*`, `waitFor` and
  `waitForRequest('@alias')`. There are no implicit retries like Cypress
  `should`: anything that appears after an async step needs `findBy*` or
  `waitFor`, not a plain `getBy*` + `expect`. Find elements by the labels from `translatedLabels.ts`; assert sent payloads with
  `const { request } = await waitForRequest('@alias')`. No whole-page
  snapshots: assert what matters explicitly.

Migrating a Cypress spec, or unsure how to translate a command: follow
`centreon/test-setup/MIGRATING_FROM_CYPRESS.md` (same test names, nothing
dropped).

## Definition of done

A frontend change is done when the `verify-frontend` skill passes
(`.claude/skills/verify-frontend/verify.sh`): Biome, type-check and the
related tests on the changed files. Run it before saying a change is done.

## Git and PRs

- Branch `MON-XXXXXX-short-description` (CI requires the Jira key), targeting `develop` unless based on a `dev-XX.XX.x` series; conventional commits (`fix(web): ...`), one Jira key per PR.
- Enable the gitleaks hooks once with `git config core.hooksPath .githooks`; never bypass them with `--no-verify`.

## Do not

- Update snapshots wholesale to make a test pass. Run the one test file,
  inspect the diff, update only intended changes.
- Commit secrets, or mention security vulnerabilities in branch names, commit
  messages or PR titles and descriptions.
- Put module- or feature-specific detail here. It belongs in a `CLAUDE.md`
  inside that scope, so it loads only when working there.
