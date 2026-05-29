# PHP 8.4 Migration Plan

This document tracks the migration of Centreon from PHP 8.2 to PHP 8.4 across the main repo and all satellite modules. It covers the work done, the work in progress, and the work that remains until PHP 8.4 becomes the runtime in production.

> **Status legend** — `[x]` done · `[~]` in progress · `[ ]` not started · `[-]` not applicable

## 0. Reference material

- Official: <https://www.php.net/manual/en/migration83.php> (PHP 8.3 — BC, deprecations, new features)
- Official: <https://www.php.net/manual/en/migration84.php> (PHP 8.4 — BC, deprecations, new features)
- Cheatsheet (third-party): <https://eusonlito.github.io/php-changes-cheatsheet/incompatible.html>

## 1. Backward incompatible changes (BC)

Goal: code must run identically on PHP 8.2 (current production) and PHP 8.4 (target). No behavior regression on 8.2.

### 1.1 Main repo — `centreon` (MON-197576)

- [x] Audit all 15 PHP 8.3 BC items against the codebase
- [x] Audit all 27 PHP 8.4 BC items against the codebase
- [x] **DateTime exception hierarchy** (PHP 8.3 BC)
  - [x] `www/include/monitoring/downtime/AddDowntime.php` — wrap `new DateTime($x)` in try/catch, return validation error
  - [x] `www/class/centreonGMT.class.php` — wrap in try/catch, re-throw as `InvalidArgumentException` in both `getUTCDate()` and `getUTCDateFromString()`
  - [x] `www/class/centreonExternalCommand.class.php` — wrap in try/catch, re-throw as `InvalidArgumentException`
- [x] **`E_STRICT` removal** (PHP 8.4 BC) — strip `& ~E_STRICT` from `error_reporting()`
  - [x] `www/main.php`
  - [x] `www/main.get.php`
  - [x] `www/api/external.php`
  - [x] `tests/php/bootstrap.php`
- [x] **PDO boolean attributes** (PHP 8.4 BC) — replace `toBe(0)/toBe(1)` with `toBeFalsy()/toBeTruthy()`
  - [x] `tests/php/Adaptation/Database/Connection/Adapter/Dbal/DbalConnectionAdapterTest.php`
  - [x] `tests/php/Centreon/Infrastructure/DatabaseConnectionTest.php`
  - [x] `tests/php/www/class/CentreonDBTest.php`
- [x] **Closure naming in stack traces** (PHP 8.4 BC) — read trace dynamically instead of asserting hardcoded `{closure}`
  - [x] `tests/php/Core/Common/Domain/Exception/BusinessLogicExceptionTest.php`
  - [x] `tests/php/Core/Common/Domain/Exception/ExceptionFormatterTest.php` (+ `__LINE__` offsets)
  - [x] `tests/php/Core/Common/Infrastructure/ExceptionLogger/ExceptionLoggerTest.php` (+ JSON-escaped backslashes)
  - [x] `tests/php/www/class/CentreonLogTest.php`
- [x] **`fputcsv()` escape parameter** (PHP 8.4 deprecation, bundled with BC) — 11 call sites in 6 files
- [x] PR squashed into 3 logical commits, rebased on develop, pushed
- [~] PR review and merge (awaiting reviewers)

### 1.2 Satellite repos / modules

- [x] **centreon-awie** — 1 fix (E_STRICT in `generateExport.php`), PR ready
- [x] **centreon-dsm** — no BC issues found
- [x] **centreon-open-tickets** — no BC issues found
- [x] **centreon-ha** — no PHP files
- [x] **centreon-modules monorepo** — PR opened with:
  - [x] `centreon-anomaly-detection/tests/php/bootstrap.php` — E_STRICT
  - [x] `centreon-license-manager/tests/php/bootstrap.php` — E_STRICT
  - [x] `centreon-bam/tests/php/polyfill.php` — explicit nullable (deprecation, bundled)
  - [x] `php-tools/php-cs-fixer/config/base.{strict,unstrict}.php` + ruleset — CI tooling mirror (MON-198134 follow-up)
- [x] **All 10 modules audited:** anomaly-detection, autodiscovery, bam, cloud-business-extensions, cloud-extensions, it-edition-extensions, license-manager, map, mbi, pp-manager
- [~] Module PRs review and merge

## 2. CI tooling temporary workarounds — MON-198134

Required so `php-cs-fixer` 3.76 (which officially supports PHP syntax up to 8.3) can run on the PHP 8.4 CI image. Tagged for cleanup once the runtime is PHP 8.4 minimum OR cs-fixer is upgraded.

- [x] `php-tools/php-cs-fixer/config/base.strict.php` — `setUnsupportedPhpVersionAllowed(true)` + sequential call refactor (PHPStan compat)
- [x] `php-tools/php-cs-fixer/config/base.unstrict.php` — same
- [x] `php-tools/php-cs-fixer/src/PhpCsFixerRuleSet.php` — `'mb_str_functions' => false` (rule deprecated upstream + would generate PHP 8.4-only `mb_trim`/`mb_ltrim`)
- [ ] **Post-migration cleanup** (MON-198134): remove `setUnsupportedPhpVersionAllowed(true)`, decide on `mb_str_functions` (re-enable or permanently remove since deprecated in cs-fixer since 3.42), restore fluent-chain style

## 3. Deprecations — separate ticket (non-blocking)

PHP 8.3 and 8.4 deprecations emit warnings but don't break execution. Scoped out of MON-197576 into a dedicated follow-up because warnings ≠ blockers for migration.

- [x] **Already handled during BC work:** `E_STRICT`, `fputcsv` escape, closure naming, implicit-nullable polyfill in `centreon-bam`
- [x] Implicit-nullable scan across all 11 repos → only 1 hit total (centreon-bam polyfill, fixed)
- [ ] PHP 8.3 deprecations sweep (`get_class()`/`get_parent_class()` without args, traits constants, `mt_srand()/srand()`, etc.)
- [ ] PHP 8.4 deprecations sweep (`Reflection::*` return-by-reference, etc.)
- [ ] Remove `@AllowDynamicProperties` shims if any (PHP 8.2 carry-over)
- [ ] PHPStan / Rector `PHP_84` ruleset pass for residual findings

## 4. Sibling tickets / other teams

- [~] **MON-198133** — `centreon-test-lib` accepts PHP 8.4
  - [x] Bump composer constraint: `"php": "8.2.*"` → `"php": "8.4.*"` (lib will be decommissioned post-migration, no need for dual support)
  - [x] Bump `config.platform.php` to `"8.4"` — composer free to pick PHP 8.3+/8.4 dependency versions
  - [x] Refresh `composer.lock` (21 deps upgraded, mostly Symfony 7.4.x → 8.0.x major bumps on transitive utility components)
  - [x] Fix implicit-nullable in `src/behat/Api/Context/RestContextTrait.php` (`string $v = null` → `?string $v = null`)
  - [x] PR opened
  - [ ] Consumers (centreon, centreon-modules) point composer.json at this branch via `dev-MON-198133-php-8.4` reference (temporary, until merge)
  - [ ] PR merged
- [ ] **Build team** — Docker images on PHP 8.4 (alma9, alma10, trixie)
- [ ] **Build team** — Packaging (RPM/DEB) on PHP 8.4
- [ ] **CI team** — finalize PHP 8.4 CI matrix (currently testing forward-compat in some jobs)

## 5. Test & validation

- [~] Unit tests pass on PHP 8.2 (current)
- [ ] Unit tests pass on PHP 8.4 (CI job — partly green, blocked on test-lib for some modules)
- [ ] Pest/PHPUnit full suite on PHP 8.4 — green for main centreon repo
- [ ] E2E suite (Cypress) on Trixie + MySQL 8.4 — investigate brittle tests like MON-152006 (`05-agent-configuration-check-permissions`) — non-regression, but `eq()` selectors are fragile
- [ ] Smoke test on dev environment with PHP 8.4
- [ ] Performance regression check (legacy WWW pages, API endpoints) — informational

## 6. Runtime switch (post-migration)

When PHP 8.4 becomes the production minimum:

- [ ] Bump `composer.json` `require.php` constraint to `^8.4`
- [ ] Update production Docker images to PHP 8.4
- [ ] Update RPM/DEB packages to PHP 8.4
- [ ] Update install / upgrade documentation
- [ ] Update README / system requirements pages
- [ ] Announce in changelog / release notes

## 7. Post-migration cleanup

After the runtime switch, drop the PHP 8.2 compatibility scaffolding:

- [ ] Remove `setUnsupportedPhpVersionAllowed(true)` from cs-fixer configs (MON-198134)
- [ ] Re-enable or permanently remove `mb_str_functions` rule (recommended: remove — deprecated upstream)
- [ ] Restore fluent-chain style in `php-tools/php-cs-fixer/config/base.{strict,unstrict}.php`
- [ ] Drop the `PHP_CS_FIXER_IGNORE_ENV=1` prefix from any composer scripts (if any remain)
- [ ] Remove PHP 8.2-only branches in code, if any (none expected — BC fixes were forward-compatible)
- [ ] Update CI matrix to remove PHP 8.2 jobs

## Tracking summary (as of this commit)

| Phase | Status |
|---|---|
| BC — main centreon | Done, PR in review |
| BC — satellite repos / modules | Done, PRs ready / opened |
| BC — CI tooling workarounds | Done, MON-198134 captures cleanup |
| Deprecations sweep | Not started (separate ticket) |
| centreon-test-lib (MON-198133) | Code changes done — ready for PR |
| Build / packaging on PHP 8.4 | Not started (build team) |
| Runtime switch | Not started (depends on above) |
| Post-migration cleanup | Not started (depends on runtime switch) |

## Related tickets

- **MON-197576** — PHP 8.4 BC fixes (this work)
- **MON-198133** — `centreon-test-lib` PHP 8.4 support
- **MON-198134** — cs-fixer workaround cleanup after migration
- Deprecations ticket — created separately, non-blocking
