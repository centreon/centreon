# ADR-0041: Write upgrade scripts as logged, re-entrant actions that fail loudly

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Each release ships its database and data migration as `www/install/php/Update-<version>.php`, and optionally `Update-<version>.post.php`. Both upgrade runners, the update API (`UpdateCommandHandler`) and the web wizard (`www/install/step_upgrade/process/process_step4.php`), log the script as one step in `prod.upgrade.log`: its start, its completion or its failure.

What happens inside the script is visible only if the script says it. Scripts differ: some log nothing, some swallow an error and return, some run their DML outside a transaction, some reach the database through `CentreonDB` methods. Support then cannot tell which action failed, or whether a rerun is safe. `Update-next.php.tpl` and [logging.md](../logging.md#13-writing-upgrade-scripts-update-php) describe the expected shape, but nothing makes it a rule.

[PR 12170](https://github.com/centreon/centreon/pull/12170) adds a doctrine/migrations engine for new schema and data changes. The `Update-<version>.php` scripts still run: this record governs them until the runners stop executing them.

The rules of the Decision stay in one record: together they give a run that names the failed action and can be repeated.

One alternative was weighed:

- **Let the runner's step log be enough.** It lost: it only says that the script failed, not which action, and gives no trace of what a successful run changed.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Start every upgrade script from `www/install/php/Update-next.php.tpl`**, and keep its shape:

- **One callable per business action.** Set `$errorMessage` to a human description of the action **before** running it, including before `startTransaction()` and `commitTransaction()`, so the failure names the action that failed.
- **Log every action through `LoggerUpgrade::create()->info($version, …)`**, its intent before and its outcome after. Log a skipped action too ("already migrated"): it proves the script is re-entrant. Use `warning()` for a fallback taken, with the caught exception, unless the action writes a secret. `LoggerUpgrade` takes no context, so its message carries the data: put only versions, identifiers and counts in it, never a [secret](../glossary.md#secret) or a raw value.
- **Make every action re-entrant**: check the current state before changing it, so a second run after a partial failure is safe.
- **Run DML in a transaction** guarded by `isTransactionActive()`. DDL is not transactional on MySQL and MariaDB: run it before the transaction, each statement re-entrant on its own.
- **Reach the database only through the `ConnectionInterface` methods** of `$pearDB` and `$pearDBO`, as set by [ADR-0028](0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md). DDL, and DML the query builder cannot express (joined `UPDATE` or `DELETE`), may be written as literal SQL, its values still bound through `QueryParameters`. This widens the literal-SQL case of [ADR-0010](0010-apip-core-legacy-parameterize-repository-queries.md) and ADR-0028 for upgrade scripts only.
- **On failure, rethrow** a `RuntimeException` whose message starts with `UPGRADE - <version>:` and carries `$errorMessage`, with the caught exception as `previous`. Do **not** log the `php_script` or `php_post_script` `stepFailure` yourself: the runner does. The script logs a `stepFailure` only for `php_script_rollback`, when the rollback itself fails.
- **An action that writes a [secret](../glossary.md#secret)** wraps its own database calls in a local `try/catch` and throws a generic message **without** `previous`, before the template's global `catch` chains one: the runner logs the whole chain, and a driver message can quote the value, as a duplicate-key error does.

## Consequences

- `CentreonDB`, `DatabaseConnection` and `DbalConnectionAdapter` log the driver message themselves before they throw, so a failed secret write through them still reaches the log. The local rethrow keeps the secret out of the upgrade log chain only. This is a known gap.
- `prod.upgrade.log` tells support which action ran, which was skipped and which failed, and a failed upgrade can be rerun.
- Existing scripts are not rewritten: they have shipped.
- The rule is enforced in review.

## When to Amend

- The upgrade runners stop executing `Update-<version>.php` files, or move to a migration tool.
- No legacy code remains.
