# Playwright end-to-end tests

Proof of concept of a Playwright e2e suite for centreon-web, running against the
`.github/docker/docker-compose.yml` stack and built around the Page Object Model
(what the Cypress suite in `tests/e2e` does not do).

## Requirements

- Docker with the Compose plugin, and access to `docker.centreon.com`
- Node 20+, pnpm

## Install

```bash
pnpm install --ignore-workspace
pnpm install:browsers
```

`--ignore-workspace` is required because this proof of concept is deliberately
kept out of `pnpm-workspace.yaml`: it has its own lockfile so it can be tried
without regenerating the root one. Turning it into a real workspace package is a
follow-up.

## Run

```bash
pnpm test                  # starts the stack, runs the suite, stops the stack
pnpm test:headed
pnpm test:ui               # Playwright UI mode
pnpm report                # open the last HTML report
```

The stack is started by `globalSetup` and stopped by `globalTeardown` under the
compose project `centreon-e2e-playwright`, so it never collides with a stack
started by hand or by the Cypress suite.

While writing tests, keep the platform between runs:

```bash
pnpm stack:up
E2E_SKIP_STACK=1 pnpm test:ui
pnpm stack:down
```

## Environment variables

| Variable              | Default                                                        | Purpose                                  |
| --------------------- | -------------------------------------------------------------- | ---------------------------------------- |
| `WEB_IMAGE`           | `docker.centreon.com/centreon/centreon-web-alma9:develop`       | Image under test                         |
| `E2E_BASE_URL`        | `http://127.0.0.1:4000`                                        | Where the `web` service is published     |
| `E2E_SKIP_STACK`      | unset                                                          | `1` reuses an already running stack      |
| `E2E_KEEP_STACK`      | unset                                                          | `1` keeps the stack after the run        |
| `E2E_COMPOSE_PROJECT` | `centreon-e2e-playwright`                                      | Compose project name                     |
| `E2E_ADMIN_LOGIN`     | from `tests/e2e/fixtures/users/admin.json`                     | Admin alias                              |
| `E2E_ADMIN_PASSWORD`  | from `tests/e2e/fixtures/users/admin.json`                     | Admin password                           |

## Layout

```
playwright.config.ts          Playwright configuration
biome.json                    extends packages/js-config/biome/biome.json
src/config/environment.ts     stack + URL settings, all env driven
src/config/users.ts           credentials, reused from the Cypress fixtures
src/stack/dockerStack.ts      docker compose up/down + readiness probe
src/stack/globalSetup.ts      starts the platform once per run
src/api/CentreonApi.ts        data seeding and cleanup through API v1 (CLAPI) and v2
src/pages/BasePage.ts         navigation + "am I on this page" assertion
src/pages/LoginPage.ts        page object of /centreon/login
src/pages/ResourcesStatusPage.ts
src/pages/AuthenticationPage.ts          Administration > Authentication
src/pages/AuthenticationTokensPage.ts    Administration > Authentication tokens
src/components/               component objects: UserMenu, Snackbar, ConfirmationDialog,
                              PasswordSecurityPolicyForm, CreateTokenDialog
src/fixtures/test.ts          page objects, API client and disposable users as fixtures
tests/auth.setup.ts           logs the admin in once, specs reuse the session
tests/authentication/         specs
tests/administration/
```

### Page Object Model conventions

- One class per page, extending `BasePage`, which owns its `path`.
- Locators are built once in the constructor and exposed as `readonly`
  properties; nothing else in the suite writes a selector.
- Locators are user facing (`getByLabel`, `getByRole`, `getByText`) and mirror
  the labels declared in `translatedLabels.ts`, referenced in a comment next to
  them.
- Actions (`login`, `logout`) are `async` methods without assertions; assertions
  live in explicit `expect*` methods so a spec reads as intent.
- Reusable widgets are component objects (`UserMenu`) owned by the pages that
  display them, never duplicated.
- Specs get their page objects through fixtures and never instantiate a page
  object themselves.

### Test data

- Specs start logged in as admin (`storageState` saved by `tests/auth.setup.ts`);
  specs testing the login form opt out with `anonymousStorageState`.
- Specs seed their data through the `api` fixture and `createUser()`, with
  `uniqueName()` names, and clean it in fixture teardown: specs never share data
  and run in parallel on a single stack, with no container restart per feature.
- Logging out ends the current session: do it as a dedicated user, never as the
  shared admin.

## Notes

- `fullyParallel` is enabled. Specs changing a platform-wide setting declare a
  [test lock](https://playwright.dev/docs/test-parallel) named after it, e.g.
  `{ lock: 'password-security-policy' }`, and restore it in a fixture: tests
  sharing a lock never run concurrently, across files and workers, and unlike
  serial mode a failure does not skip the following tests.
- Traces, screenshots and videos are kept on failure only, under `results/`.
