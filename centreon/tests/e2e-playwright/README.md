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
├── global-setup.ts          # One-time provisioning (feature flags, ACL user)
├── fixtures/
│   ├── auth.ts              # Paths of the saved dashboard-creator / admin sessions
│   ├── credentials.ts       # Test users (overridable via env vars)
│   ├── dashboards.ts        # Dashboard seed data + ACL provisioning actions
│   ├── monitoring.ts        # CLAPI builders (host/service/host group) + submit results
│   ├── resources.ts         # Resources-status seed (host + CRITICAL/OK services)
│   ├── notifications.ts     # Cloud-notification rule builder + host group
│   ├── oidc.ts              # OIDC provider settings + ACL/contact provisioning
│   └── test.ts              # Custom Playwright fixtures (API seed/cleanup)
├── helpers/
│   ├── CentreonApi.ts       # HTTP client: v1 auth, CLAPI, v2 session, dashboards,
│   │                        #   monitoring provisioning, submit results, notifications
│   └── docker.ts            # docker compose exec helpers (feature flags, ACL)
├── pages/
│   ├── BasePage.ts          # Shared base class (navigation helpers)
│   ├── LoginPage.ts         # Login form Page Object (+ "Login with" provider)
│   ├── MainHeader.ts        # Authenticated header / profile menu (logout)
│   ├── DashboardsListPage.ts     # Dashboards library (cards, actions menu)
│   ├── DashboardFormDialog.ts    # Create / update properties dialogs
│   ├── DashboardDetailPage.ts    # Single dashboard page (edit mode, quick access)
│   ├── DeleteDashboardDialog.ts  # Deletion confirmation dialog
│   ├── OidcConfigurationPage.ts  # Admin OpenID Connect configuration form
│   ├── KeycloakLoginPage.ts      # Keycloak login form (external IdP)
│   ├── ResourcesStatusPage.ts    # Monitoring resources listing (filter + search)
│   └── NotificationsListPage.ts  # Cloud notifications listing (pagination)
└── tests/
    ├── auth.setup.ts        # `setup` project: saves the dashboard-creator + admin sessions
    ├── authentication.spec.ts
    ├── authentication/
    │   └── oidc-authentication.spec.ts
    ├── dashboards/
    │   ├── dashboard-creation.spec.ts
    │   ├── dashboard-navigation.spec.ts
    │   ├── dashboard-properties-edition.spec.ts
    │   └── dashboard-deletion.spec.ts
    ├── resources-status/
    │   └── resource-listing.spec.ts   # migrates Cypress Resources-status/01-listing
    └── notifications/
        └── notification-listing.spec.ts  # migrates Cypress Cloud-notifications/05-listing
```

## OpenID Connect tests

The OIDC specs (migration of the Cypress `OpenID-connect` feature 01) need the
docker compose **`openid` profile** (Keycloak + an `sso-proxy`). They run under
a dedicated **`oidc` Playwright project** that launches Chromium with
`--host-resolver-rules=MAP sso-proxy 127.0.0.1`: this lets the browser reach
Keycloak at the **same** host name the `web` container uses (`sso-proxy`), so a
single provider Base URL (`http://sso-proxy:8080/...`) works for both the
authorization redirect (browser) and the token exchange (server). The provider
is configured through the UI form, and the login flow is driven end-to-end
(Centreon → Keycloak → back). `pnpm stack:up` starts the `openid` profile.

## Test data setup (dashboards)

The dashboard specs need a feature flag, a dedicated ACL user and seed data.
This setup — handled by Cypress custom commands — is split here into:

- **`global-setup.ts`** (runs once): enables the dashboard feature flag and
  provisions the `user-dashboard-creator` contact + ACL group via the legacy
  CLAPI API, then recomputes ACLs. Bypass with `SKIP_GLOBAL_SETUP=1` when the
  platform is already provisioned.
- **`auth.setup.ts`** (the `setup` project, runs once): logs in as the
  dashboard creator through the UI and saves the session to `.auth/`. The
  dashboard specs reuse it via `test.use({ storageState })` (a `dependencies:
  ['setup']` makes it run first), so no UI login happens in each test.
- **`fixtures/test.ts`** (per test): seeds/cleans dashboards through the REST
  API (`CentreonApi`), replacing the Cypress `beforeEach`/`afterEach` DB hooks.

Dashboards are addressed **by name** in the Page Objects rather than by list
position, which is more robust than the index-based selection used by Cypress.
Page Object actions are wrapped in `test.step(...)` and key locators carry a
`.describe(...)` label, so traces and the HTML report read as readable steps.

## Test data setup (resources status & cloud notifications)

These specs use the admin session (saved by `auth.setup.ts`) and seed their data
through `CentreonApi`, mirroring the Cypress CLAPI commands:

- **Resources status** (`resources-status/`, migrates `Resources-status/01-listing`):
  the `beforeAll` creates one passive host with three CRITICAL services and one
  OK service (CLAPI `ADD HOST`/`ADD SERVICE` + `APPLYCFG`), pushes passive
  results (`submit_results`), then polls `monitoring/resources` until the engine
  has loaded them — the equivalent of the Cypress `submitResults` +
  `checkServicesAreMonitored`. Provisioning is **idempotent**: it is skipped when
  the services are already monitored, and the host is left in place.
- **Cloud notifications** (`notifications/`, migrates `Cloud-notifications/05-notification-listing`):
  the feature flag is enabled in `global-setup.ts`, a host group is created once,
  and each test creates N rules through the configuration API and deletes them
  before/after, replacing the Cypress `DELETE FROM notification` cleanup.

> **Fresh engine for resources status:** seeding live monitoring data needs a
> cleanly-booted engine, so the resources spec is reliable against a fresh `web`
> container (as in CI, and as the Cypress suite guarantees by recreating a
> container per spec). Re-running it many times against the *same* long-lived
> container can leave the engine with stale, disabled service entries after
> repeated config reloads.

## Requirements

- Docker + Docker Compose
- Node.js >= 20, pnpm >= 8

## Running the tests against the docker compose stack

The tests target the `web` service of the shared docker compose stack
(`.github/docker/docker-compose.yml`), which exposes Centreon on
`http://localhost:4000/centreon`.

> This is a **standalone pnpm project** (its own `pnpm-workspace.yaml` /
> `pnpm-lock.yaml`), intentionally **not** part of the `centreon` frontend
> workspace: the suite drives the running app over HTTP and imports no
> `@centreon` package, so installing it pulls only Playwright — not the whole
> frontend dependency graph.

```bash
cd centreon/tests/e2e-playwright

# 1. Install dependencies and the Chromium browser
pnpm install
pnpm install:browsers

# 2. Run the tests — the required services are started automatically
pnpm test            # headless
pnpm test:headed     # with a visible browser
pnpm test:ui         # Playwright UI mode
pnpm report          # open the last HTML report

# 3. (optional) tear the stack down when finished
pnpm stack:down
```

### Automatic stack management

The required services are ensured at the start of the run: `global-setup.ts`
makes sure the `web` stack is up (for the auth/dashboard specs) and the OIDC
spec's `beforeAll` brings up the `openid` profile (Keycloak + sso-proxy). If a
needed service is not running it is started (and recreated if its configuration
drifted), so you do not have to run `pnpm stack:up` yourself.

Set `SKIP_STACK_MANAGEMENT=1` to manage the stack manually (e.g. when you
already started it with `pnpm stack:up` and want faster startup).

## Configuration

| Env var                   | Default                              | Purpose                         |
| ------------------------- | ------------------------------------ | ------------------------------- |
| `CENTREON_BASE_URL`       | `http://localhost:4000/centreon`     | Base URL of the platform        |
| `CENTREON_ADMIN_LOGIN`    | `admin`                              | Admin login                     |
| `CENTREON_ADMIN_PASSWORD` | `Centreon!2021`                      | Admin password                  |
| `SKIP_STACK_MANAGEMENT`   | _(unset)_                            | If set, the tests do not start/recreate the stack |
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
