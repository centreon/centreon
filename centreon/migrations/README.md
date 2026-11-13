# Database migrations

Schema and data changes of Centreon and its modules are shipped as [doctrine/migrations](https://www.doctrine-project.org/projects/migrations.html)
classes. The legacy `www/install/php/Update-*.php` scripts keep running first and fade away over time.

## Migration sets

Each set is versioned on its own, with its own metadata table:

| Set                                     | Database           | Directory                            | Namespace                                                                            | Metadata table                       |
| --------------------------------------- | ------------------ | ------------------------------------ | ------------------------------------------------------------------------------------ | ------------------------------------ |
| `realtime` (`--set=realtime`)           | `centreon_storage` | `centreon/migrations/realtime/`      | `Centreon\Migrations\Realtime`                                                       | `doctrine_migration_versions`        |
| `configuration` (`--set=configuration`) | `centreon`         | `centreon/migrations/configuration/` | `Centreon\Migrations\Configuration`                                                  | `doctrine_migration_versions`        |
| a module (`--module=<name>`)            | `centreon`         | `www/modules/<name>/migrations/`     | `<Name>\Migrations` (`centreon-open-tickets` gives `CentreonOpenTickets\Migrations`) | `doctrine_migration_versions_<name>`, dashes as underscores |

A module ships migrations only if its name is made of lowercase letters and digits, starting with a letter, in words
separated by single dashes, at most 35 characters (`centreon-open-tickets`): it names its metadata table and its
namespace. A module directory with migrations and another name, or whose namespace collides with another one when
case is ignored (`foo-1` and `foo1`), is ignored: `status` without `--set`/`--module` reports it as an error and logs
it as a warning.

`centreon:migrations:migrate` without a set runs `realtime`, then `configuration`, then records `centreon.version`
(`config.new/packages/centreon.yaml`) as the platform version, under the rules below. A single set (`--set`) records
nothing, and warns when a version remains to record.

The recorded version never moves backwards, and is left to the legacy scripts while `Update-next.php` exists
(development builds): recording the coming release would skip that script once it is renamed after that release. It
is also left to them once the update wizard has removed `www/install`, since a pending `Update-next.php` can no longer
be detected.

## Writing a migration

```shell
bin/console.new centreon:migrations:generate --set=configuration --dml --env=dev
bin/console.new centreon:migrations:generate --module=centreon-open-tickets --ddl --env=dev
```

The generated migration fails until written: its empty `getDescription()` makes the runner refuse the whole set,
and its `up()` and `revert()` throw.

Every migration extends one of the two base classes of `App\Upgrade\Infrastructure\Migration`, in the namespace of its
set, alone in a `Version<timestamp>.php` file named after it, at the root of the set directory: the runner refuses the
set when any other file or a subdirectory is there, as Doctrine would never run it.

- `DdlMigration` for schema changes (`CREATE`, `ALTER`, `DROP`...). MySQL commits implicitly around DDL, so it runs
  outside any transaction: hold a **single idempotent statement**, so that replaying it after a failure is harmless.
  MySQL has no `ADD COLUMN IF NOT EXISTS`: guard in `up()` by reading the one table at stake, e.g.
  `if ($this->connection->createSchemaManager()->introspectTableByUnquotedName('host')->hasColumn('host_notes_url')) { $this->write('host_notes_url already exists, skipped'); return; }`.
  Reading `$schema` works too, but loads every table of the database, for each migration. A migration that skips its
  statement this way leaves a Doctrine warning in the upgrade log: "was executed but did not result in any SQL
  statements".
- `DmlMigration` for data changes (`INSERT`, `UPDATE`, `DELETE`...). It runs in a transaction with its metadata row:
  all or nothing. DDL, transaction control, `CALL`, `PREPARE`, `EXECUTE` and `SET STATEMENT` are rejected, as they
  could silently commit that transaction, and so is a statement run through `$this->connection` that commits it.

Both reject, in `addSql()`, a session setting (`SET foreign_key_checks`, `SET NAMES`, `USE`, `CREATE TEMPORARY
TABLE`...), as it would apply to the next migrations of the run: use a user variable (`@name`). A `DdlMigration` also
rejects transaction control and table locks. A statement run through `$this->connection` escapes these checks: never
change the session that way.

In both, never skip (`skipIf()`, `SkipMigration`): a skipped migration fails the run, as Doctrine would not record it.
Describe the change in `getDescription()`, pass one statement per `addSql()` call, and implement `revert()`,
which `down()` calls. `$schema` is read only: a migration changing it fails, as Doctrine would run the SQL of the
change out of reach of these checks.

Rules:

- Never delete or rename a migration once released: a platform where it ran refuses to upgrade until it is restored.
  A fix shipped on a maintained branch is forward-ported with the **same class name**, in a release published no later
  than the maintenance one: a platform upgrading to a version without it refuses to upgrade.
- A fix shipped both as a legacy script (maintained branch) and as a migration runs twice on a platform upgraded
  through that branch: the migration must be idempotent.
- Never write a secret in a migration: a failing statement can quote its values in the upgrade log. Secrets go through
  the vault.

Logging (`/var/log/centreon/prod.upgrade.log`, like the legacy scripts):

- The engine logs the start, the outcome and the duration of each set, then each migration with its description and
  duration, and the failing one with its error: do not log the statements yourself.
- Trace a decision the migration takes with `$this->write()`, a skipped change included, and a degraded but accepted
  outcome with `$this->warnIf()`. Never log a secret.

## Operating

```shell
bin/console.new centreon:migrations:status [--set=realtime|configuration | --module=<name>] [-v]
bin/console.new centreon:migrations:migrate [--set=realtime|configuration | --module=<name>]
bin/console.new centreon:migrations:rollback <version|prev|first> (--set=realtime|configuration | --module=<name>)
```

- Run them as the web server user (`su - apache -s /bin/bash -c '...'`, `www-data` on Debian): run as root, they
  leave a cache and an upgrade log the wizard and the API cannot write anymore.
- `status` exits with `1` when a set cannot be read or, without `--set`/`--module`, a module directory is ignored, and
  with `2` when `migrate` refuses a set for now (legacy update scripts pending).
- The Centreon Web sets refuse to run while legacy `Update-*.php` scripts are pending: update the platform first, with
  the upgrade wizard or the API.
- A module set only runs once its module is installed, and `status` only lists installed modules.
- A failing migration stops the run: a `DmlMigration` leaves nothing behind, the migrations executed before it stay
  applied, and the next run resumes there.
- A rollback is explicit and per set: it reverts the executed migrations versioned after the given one (as `status -v`
  lists it, or its timestamp), whatever order they ran in, after listing them for confirmation (skipped with
  `--no-interaction`). It changes neither `informations.version`, nor `mod_release`, nor the code. The legacy scripts
  stay irreversible.

## Known limits

- A module set targets the `centreon` database only: a module owning tables in `centreon_storage` cannot change
  them through migrations yet.
- A module set only takes its own lock: do not migrate a module while the platform is being updated.
- Uninstalling a module keeps its `doctrine_migration_versions_<name>` table: once reinstalled, the migrations it
  already executed do not run again.
- After a doctrine/migrations upgrade changing its metadata table, `status` and `rollback` fail until `migrate`
  syncs the table.
- A migration statement holds no semicolon outside its literals and comments: a trigger, procedure, function or event
  with a `BEGIN ... END` body cannot be created through a migration yet.
