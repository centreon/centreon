---
paths:
  - "centreon/src/**"
  - "centreon/www/**/*.php"
  - "centreon/tests/php/**"
  - "centreon/config/**"
  - "centreon/config.new/**"
  - "centreon/doc/php/**"
  - "centreon/composer.json"
  - "centreon/phpstan*.neon"
  - "centreon/rector*.php"
  - "centreon/.php-cs-fixer*.php"
  - "centreon/deptrac*.yaml"
  - "centreon/phpunit*.xml"
---

# Backend (PHP)

Read the guide of the zone you touch, linked from the root `AGENTS.md`.

## Pitfalls

- Run `composer install` in both `php-tools/` (Rector, PHP CS Fixer, PHPStan,
  Deptrac) and `centreon/`; run the composer scripts from `centreon/`.
- If PHP CS Fixer refuses to run because the local PHP version is newer than
  it supports, set `PHP_CS_FIXER_IGNORE_ENV=1`.
- `composer rector:diff` and `cs:diff` check only `git diff --name-only`:
  unstaged changes to tracked files. To check a branch, pass the files:
  `PHP_DIFF_FILES="$(git diff --relative --name-only origin/develop -- '*.php')" composer cs:diff`.
- `composer test:new` skips `tests/php/App/*/Infrastructure`: those tests need
  a database and run with `composer test:new:integration`.

## Definition of done

The quality scripts of the zone you changed pass (`composer new:check`,
`core:check` or `legacy:check`, all defined in `centreon/composer.json`), with
the related tests. State in the PR what ran and what did not (tests needing a
database, CI-only checks).
