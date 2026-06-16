# End-to-End tests with Playwright (proof of concept)

This is a proof of concept for running Centreon E2E tests with
[Playwright](https://playwright.dev/) using the **Page Object Model** pattern,
as an alternative to the existing Cypress + Cucumber suite (`tests/e2e`).

Unlike the Cypress suite — where selectors and interactions are spread across
Gherkin step definitions and global custom commands — every interaction here is
encapsulated in a Page Object (`pages/`). Tests describe *intentions*
("log in as admin", "log out") and never touch a selector directly.

## Layout

```
tests/e2e-playwright/
├── playwright.config.ts     # Playwright config (baseURL, reporters, optional stack boot)
├── global-setup.ts          # One-time provisioning for the dashboard specs
├── fixtures/
│   ├── credentials.ts       # Test users (overridable via env vars)
│   ├── dashboards.ts        # Dashboard seed data + ACL provisioning actions
│   └── test.ts              # Custom Playwright fixtures (login + API cleanup)
├── helpers/
│   ├── CentreonApi.ts       # HTTP client: v1 auth, CLAPI, v2 session, dashboard CRUD
│   └── docker.ts            # docker compose exec helpers (feature flag, ACL)
├── pages/
│   ├── BasePage.ts          # Shared base class (navigation helpers)
│   ├── LoginPage.ts         # Login form Page Object
│   ├── MainHeader.ts        # Authenticated header / profile menu (logout)
│   ├── DashboardsListPage.ts     # Dashboards library (cards, actions menu)
│   ├── DashboardFormDialog.ts    # Create / update properties dialogs
│   ├── DashboardDetailPage.ts    # Single dashboard page (edit mode, quick access)
│   └── DeleteDashboardDialog.ts  # Deletion confirmation dialog
└── tests/
    ├── authentication.spec.ts
    └── dashboards/
        ├── dashboard-creation.spec.ts
        ├── dashboard-navigation.spec.ts
        ├── dashboard-properties-edition.spec.ts
        └── dashboard-deletion.spec.ts
```

## Test data setup (dashboards)

The dashboard specs need a feature flag, a dedicated ACL user and seed data.
This setup — handled by Cypress custom commands — is split here into:

- **`global-setup.ts`** (runs once): enables the dashboard feature flag and
  provisions the `user-dashboard-creator` contact + ACL group via the legacy
  CLAPI API, then recomputes ACLs. Bypass with `SKIP_GLOBAL_SETUP=1` when the
  platform is already provisioned.
- **`fixtures/test.ts`** (per test): logs in through the UI as the dashboard
  creator and seeds/cleans dashboards through the REST API (`CentreonApi`),
  replacing the Cypress `beforeEach`/`afterEach` DB hooks.

Dashboards are addressed **by name** in the Page Objects rather than by list
position, which is more robust than the index-based selection used by Cypress.

## Requirements

- Docker + Docker Compose
- Node.js >= 20, pnpm >= 8

## Running the tests against the docker compose stack

The tests target the `web` service of the shared docker compose stack
(`.github/docker/docker-compose.yml`), which exposes Centreon on
`http://localhost:4000/centreon`.

```bash
cd centreon/tests/e2e-playwright

# 1. Install dependencies and the Chromium browser
pnpm install
pnpm install:browsers

# 2. Start the Centreon web stack (image pulled from the Centreon registry)
pnpm stack:up
# Wait until the platform answers:
#   curl --fail http://localhost:4000/centreon/api/latest/platform/versions

# 3. Run the tests
pnpm test            # headless
pnpm test:headed     # with a visible browser
pnpm test:ui         # Playwright UI mode
pnpm report          # open the last HTML report

# 4. Tear the stack down
pnpm stack:down
```

### Letting Playwright start the stack

Set `START_STACK=1` to make Playwright bring the `web` service up automatically
and wait for the platform API before running the tests:

```bash
START_STACK=1 pnpm test
```

## Configuration

| Env var                   | Default                              | Purpose                         |
| ------------------------- | ------------------------------------ | ------------------------------- |
| `CENTREON_BASE_URL`       | `http://localhost:4000/centreon`     | Base URL of the platform        |
| `CENTREON_ADMIN_LOGIN`    | `admin`                              | Admin login                     |
| `CENTREON_ADMIN_PASSWORD` | `Centreon!2021`                      | Admin password                  |
| `START_STACK`             | _(unset)_                            | If set, Playwright boots the stack |
| `RECORD_VIDEO`            | _(unset)_                            | If set, record a video for every test (not only failures) |
| `RECORD_TRACE`            | _(unset)_                            | If set, capture a trace for every test (not only failures) |

> **Language note:** most locators are language-independent (`data-testid` for the
> login inputs, `data-cy` for the profile menu, `role="alert"` for errors). A few
> assertions still rely on visible text (the *Logout* entry, the "Authentication
> failed" message), so the stack must run in **`en_US`** — which is the docker
> compose default (`CENTREON_LANG=en_US`).

## What the proof of concept demonstrates

- **Page Object Model**: locators and actions are isolated from test logic.
- A reusable `BasePage` so concrete pages only describe locators and intentions.
- Strong typing of test data through the `Credentials` fixture interface.
- The full authentication flow: successful login, invalid credentials, logout.
