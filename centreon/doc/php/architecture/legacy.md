# Legacy PHP

The oldest code, served by the legacy kernel or directly by PHP pages. It takes
fixes only, with the smallest change that works: no style or architecture
rewrite in an unrelated PR.

## Where it lives

- `centreon/www/`: pages (`www/include`), classes (`www/class`), the legacy
  REST API (`www/api`), install and upgrade scripts (`www/install`). The React
  application in `www/front_src` is front end, not legacy PHP.
- `centreon/src/` folders other than `App`, `Core` and `Adaptation`
  (`Centreon`, `CentreonLegacy`, `CentreonRemote`…).

## Bridges

- `src/Adaptation`: database and logging adapters used by legacy and
  `src/Core` code. New database calls go through
  `Adaptation\Database\Connection\ConnectionInterface`, even when the injected
  instance is a `CentreonDB`; queries use the query builder and bound
  parameters. Logging adapters are in `src/Adaptation/Log`.
- From `src/App` to legacy: adapters in `Infrastructure/Legacy`.
- From legacy to Symfony services: a dedicated service locator.

## Quality

`composer legacy:check` runs Rector, PHP CS Fixer and PHPStan on legacy code.
Tests: `composer test`.
