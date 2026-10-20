---
paths:
  - "centreon/www/front_src/**"
  - "centreon/packages/**"
  - "centreon/package.json"
  - "centreon/biome.json"
  - "centreon/tsconfig*.json"
  - "centreon/{rspack,jest,cypress}.config*.{js,ts}"
---

# Frontend (React) in centreon/centreon

For frontend and fullstack developers. Loaded only when working on frontend
files. Team-owned and reviewed like code: update it in the same PR as any
convention change. Personal notes go in `CLAUDE.local.md` (git-ignored).
`.claude/settings.json` is the team baseline (read-only allowlist, deny rules
for risky git flags); personal settings go in `.claude/settings.local.json`.
Its hook (`.claude/hooks/biome-on-edit.sh`) runs Biome on every edited
frontend file.

## Layout

- `centreon/www/front_src/src/`: Centreon Web React app
- `centreon/packages/ui`: `@centreon/ui`, published to npm and consumed by the
  centreon-modules frontends. A breaking change there cascades.
- `centreon/packages/ui-context`, `centreon/packages/js-config` (shared Biome,
  TS, Jest, Cypress, rspack configs)
- `.github/workflows/web.yml` (app) and `centreon-ui.yml` (`packages/ui`): CI

## Commands

Run from `centreon/` (pnpm workspace root; CI uses pnpm 10 and Node 24):

| Command                          | Does                                            |
| -------------------------------- | ----------------------------------------------- |
| `pnpm build` / `pnpm build:dev`  | rspack production / development build           |
| `pnpm centreon:watch`            | rspack dev server                               |
| `pnpm lint` / `pnpm lint:fix`    | Biome on `www/front_src`                        |
| `pnpm type-check`                | `tsc` on `tsconfig.typecheck.json` (runs in CI) |
| `pnpm test`                      | Jest                                            |
| `pnpm cypress:run --spec <path>` | Cypress component tests (Chromium, UTC)         |

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

Cypress component tests are the default; Jest (`*.test.ts(x)`) only for pure
logic. Copy the shape of `Configuration/HostGroups/Specs/`:

- `initialize.tsx`: sets up i18n, a Jotai store (permissions, feature flags),
  mocks every API call with `cy.interceptAPIRequest` (with an `alias`), and
  mounts the feature with its providers. Mock responses go in `utils.ts`;
  shared JSON fixtures in `centreon/cypress/fixtures`.
- `<Feature>.<Area>.tsx` files export `describe` blocks; one
  `<Feature>.cypress.spec.tsx` entry composes them.
- In a test: `cy.waitForRequest('@alias')` before asserting; find elements by
  the labels from `translatedLabels.ts`; assert sent payloads through
  `cy.waitForRequest(...).then(({ request }) => ...)`; end visual tests with
  `cy.makeSnapshot()`.
- Specs are excluded from `pnpm type-check`; Biome still lints them.

## Git and PRs

- Branch `MON-XXXXXX-short-description` (CI requires the Jira key), targeting `develop` unless based on a `dev-XX.XX.x` series; conventional commits (`fix(web): ...`), one Jira key per PR.
- Enable the gitleaks hooks once with `git config core.hooksPath .githooks`; never bypass them with `--no-verify`.

## Do not

- Regenerate visual snapshots wholesale (`cypress:run:updateSnapshot`) to make
  a spec pass. Run the one spec, inspect the `*.diff.png`, update only
  intended changes.
- Commit secrets, or mention security vulnerabilities in branch names, commit
  messages or PR titles and descriptions.
- Put module- or feature-specific detail here. It belongs in a `CLAUDE.md`
  inside that scope, so it loads only when working there.
