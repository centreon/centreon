# Cypress e2e migration plan

The Cypress + Cucumber suite (`tests/e2e`) holds 146 features and 533
scenarios. They are not ported one for one: each family is triaged first.

## Principles

- **One test, one user journey.** A Cypress feature often splits a lifecycle
  into scenarios that each reprovision the platform (edit, then duplicate, then
  delete the same object). The Playwright test runs the lifecycle once, with a
  `test.step` per stage.
- **Variations are data.** Scenarios differing only by their input (filters,
  roles, object types) become one parametrized test.
- **Test at the right layer.** Behaviour fully handled by the front end (form
  validation, cancel pop-ups, widget display options) is covered by the
  component suite (`tests/components`); the e2e suite keeps what needs the real
  backend, engine or permissions.
- **Isolated data.** Each test provisions its own objects through the API with
  unique names and removes them afterwards; no test depends on another.
- **Page Object Model.** Tests only call intentions on page objects; selectors
  live in `pages/`. Legacy listings share `LegacyListingPage`.

## Triage

| Family | Scenarios | Decision | Target |
| --- | ---: | --- | ---: |
| Legacy configuration CRUD (contacts and groups/templates, host and service templates, categories, groups, dependencies, meta services, escalations, SNMP traps/groups/vendors, connectors, commands, virtual metrics, host group services, time periods) | ~95 | **Merge**: one lifecycle test per object type (edit, duplicate, delete) on `LegacyListingPage` | ~28 |
| Audit logs (`Logs/01-06`) | 24 | **Merge**: one test per object type running add, update, disable, enable and delete through the API, then checking the log lines | 6 |
| Event log ACL, host and host category listing ACL (`Logs/07`, `Hosts/10-11`) | 13 | **Keep**: security checks on the backend | 13 |
| ACL objects (`ACLs/01-04`) | 24 | **Merge** the CRUD stages; **keep** the effect checks (menu, action and resource access really applied to a user) | ~10 |
| Authentication (local policies, autologin, OIDC, SAML, Web SSO, LDAP) | 41 | **Keep**: security-sensitive, needs the real backend and identity providers | ~35 |
| Dashboards: library, sharing, permissions, favourites | 37 | **Merge** permissions by role (parametrized); **drop** cancel and empty-field cases (component suite) | ~18 |
| Dashboards: widget display options (styles, thresholds, value format, time zone, tiles…) | ~45 | **Drop**: rendering options covered by the widget component specs | 0 |
| Dashboards: widgets with real data (create, data shown, link to Resources Status, duplicate/delete, regex filters) | ~45 | **Merge** duplicate/delete into one generic widget lifecycle; **keep** one real-data test per widget | ~14 |
| Resources Status actions (acknowledge, downtime, submit result, CSV export, time zone) | 17 | **Keep**, merging single/multiple variants | ~10 |
| Resources Status filters (`05-filtre`) | 14 | **Merge**: query building is covered by the component suite; one parametrized test checks the backend filtering | 1×14 rows |
| Downtime DST transitions | 14 | **Keep** as table-driven tests (no UI change between cases) | 2×14 rows |
| Additional connectors and agent configurations | 37 | **Drop** validation, cancel and pop-up cases (component suite); **keep** CRUD and poller-filtered permissions | ~12 |
| Cloud notifications, API tokens | 25 | **Drop** discard cases; **merge** CRUD | ~12 |
| Hosts, services, macros, pollers, misc. configuration | ~60 | **Keep** behaviours (inheritance, mass change, generation), **merge** CRUD | ~40 |
| Platform update/upgrade, custom URI/port/DB name, backup, DB partitioning | 9 | **Postpone**: need dedicated stack images; stay in Cypress | 0 |

Roughly 533 Cypress scenarios become ~200 Playwright tests, without losing a
backend behaviour.

## Status

Tracked in the README coverage section, family by family.

## Findings

- **Bug** — a host dependency created through CLAPI has no failure criteria,
  and its legacy form then crashes (`explode()` on `null` in
  `formHostDependency.php`, blank page). The tests set the criteria.
- Disabling a service category through the API v2 is not logged as
  `Disabled`, unlike the host categories and severities: Cypress disabled it
  from the legacy listing, the migrated test does too.
- The change log names fields after the table column (`hc_alias`,
  `sc_alias`) or the API property (`name`, `alias` for time periods).
- Cypress read the first log line and the object of id 1, which only holds on
  a fresh stack; the migrated tests search the log by object name.
- The React shell reloads the legacy iframe after each form submission, which
  detached the elements Cypress and the first Playwright tests acted on:
  `LegacyListingPage` opens `main.get.php`, the document the iframe shows.
