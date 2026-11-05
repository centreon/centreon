# ADR-0035: Write database changes as Doctrine migrations that the engine checks and logs

- **Status:** Proposed
- **Date:** 2026-11-05

## Context

Database changes ship today as `www/install/php/Update-<version>.php` scripts, and as `upgrade.sql` files for modules. A script runs once per version: it cannot be resumed per change, nor reverted, and its tracing depends on the author calling `LoggerUpgrade` ([logging guide](../logging.md#13-writing-upgrade-scripts-update-php)).

[PR 12170](https://github.com/centreon/centreon/pull/12170) adds an upgrade engine on `doctrine/migrations`. It records each migration once executed, resumes after a failure, and reverts on demand. Doctrine alone accepts any class, runs DDL inside transactions that MySQL silently commits, and writes no platform context to the logs.

The alternatives weighed:

- **Keep `Update-*.php` for every change.** No per-change tracking, no rollback, and modules keep a separate mechanism.
- **Plain Doctrine migrations, extending `AbstractMigration`.** Nothing stops a DDL statement from breaking the atomicity of a data change, nor a misplaced class from being silently ignored, and nothing ties the logs to the upgrade lifecycle.

## Decision

Write a schema or data change as a **Doctrine migration of the upgrade engine**:

- **Generate it** with `centreon:migrations:generate`, in the set it changes: `realtime`, `configuration`, or a module. Keep **one class per file**, named after the file, in the namespace of the set.
- **Extend `DdlMigration` for a schema change**, with a **single idempotent statement** guarded by reading the table at stake. **Extend `DmlMigration` for a data change**: it runs in a transaction, and rejects DDL, transaction control and `CALL`.
- **Pass one statement per `addSql()` call**, and **implement `revert()`**.
- **Describe the change in `getDescription()`.** The upgrade log shows it.
- **Do not log the statements.** The engine logs each run and each migration, with durations and errors. **Trace a decision** the migration takes with `$this->write()`, a skipped change included, and **a degraded outcome** with `$this->warnIf()`. Never log a secret.
- **Never delete nor rename a released migration.** Forward-port a fix with the same class name. Make a change shipped both as a legacy script and as a migration idempotent.

## Consequences

- The engine refuses a migration that breaks the conventions: wrong base class, namespace or file name, or an empty description. `DmlMigration` and `DdlMigration` reject the statements they cannot run safely.
- The upgrade log reads as one with the legacy scripts: every line carries `event`, `status`, `version` and `step`.
  - Each set runs as an upgrade step (`migrations:realtime`, `migrations_rollback:configuration`), logged through `UpgradeLoggerInterface` as `upgrade.step`, `upgrade.step_completed` (with `duration_ms`) or `upgrade.step_failure`.
  - Doctrine writes one `upgrade.migration` line per migration, with its description and duration.
- A failed write to the upgrade log never fails an upgrade, as with `LoggerUpgrade`. The file is group-writable (`0664`).
- Idempotency, revert scripts and the relevance of the traced decisions are checked in review only.
- The usage details live in [`migrations/README.md`](../../../migrations/README.md).

## When to Amend

- Doctrine migrations gain native per-statement transaction handling on MySQL and MariaDB, making the DDL/DML split unnecessary.
- The upgrade log moves to another sink or format, such as structured JSON shipped to a central platform.
