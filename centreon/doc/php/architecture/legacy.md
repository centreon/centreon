# Legacy PHP

The oldest code, served by the legacy kernel or directly by PHP pages. It takes
fixes only, with the smallest change that works: no style or architecture
rewrite in an unrelated PR. New or changed code still follows the ADRs that
apply to legacy code.

## Where it lives

- `centreon/www/`: pages (`www/include`), classes (`www/class`), the legacy
  REST API (`www/api`), install and upgrade scripts (`www/install`). The React
  application in `www/front_src` is front end, not legacy PHP.
- `centreon/src/` folders other than `App`, `Core` and `Adaptation`
  (`Centreon`, `CentreonLegacy`, `CentreonRemote`…).

## Bridges

- `src/Adaptation`: database and logging adapters used by legacy and
  `src/Core` code. New code types the connection as
  `Adaptation\Database\Connection\ConnectionInterface` and calls only its
  methods; queries use its builders and bound `QueryParameters`. Logging adapters are in `src/Adaptation/Log`.
- From `src/App` to legacy: adapters in `Infrastructure/Legacy`.
- From legacy to Symfony services: a dedicated service locator.

## Quality

`composer legacy:check` runs Rector, PHP CS Fixer and PHPStan on legacy code.
Tests: `composer test`.
