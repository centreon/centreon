# Database migrations

Schema and data changes of Centreon and its modules are shipped as [doctrine/migrations](https://www.doctrine-project.org/projects/migrations.html)
classes. The legacy `www/install/php/Update-*.php` scripts keep running first and fade away over time.

## Migration sets

Each set is versioned on its own, with its own metadata table:

| Set | Database | Directory | Namespace | Metadata table |
|---|---|---|---|---|
| `realtime` (`--set=realtime`) | `centreon_storage` | `centreon/migrations/realtime/` | `Centreon\Migrations\Realtime` | `doctrine_migration_versions` |
| `configuration` (`--set=configuration`) | `centreon` | `centreon/migrations/configuration/` | `Centreon\Migrations\Configuration` | `doctrine_migration_versions` |
| a module (`--module=<name>`) | `centreon` | `www/modules/<name>/migrations/` | `<Name>\Migrations` (`centreon-open-tickets` gives `CentreonOpenTickets\Migrations`) | `doctrine_migration_versions_<name>` |

A module ships migrations only if its name is made of lowercase letters, digits and dashes, at most 36 characters: it
names its metadata table and its namespace.

A platform upgrade (wizard, `POST /api/latest/platform/updates`) runs: the legacy scripts, `realtime`, `configuration`,
then records `centreon.version` (`config.new/packages/centreon.yaml`), then upgrades the modules (their legacy
scripts, then their set), and clears the cache once. A module upgrade from the Extension Manager runs the module set.

The recorded version never moves backwards, and is left to the legacy scripts while `Update-next.php` exists
(development builds): recording the coming release would skip that script once renamed to it.

## Writing a migration

```shell
bin/console.new centreon:migrations:generate --set=configuration --dml --env=dev
bin/console.new centreon:migrations:generate --module=centreon-open-tickets --ddl --env=dev
```

Every migration extends one of the two base classes, in the namespace of its set, alone in a file named after it: the
runner refuses any other one.

- `DdlMigration` for schema changes (`CREATE`, `ALTER`, `DROP`...). MySQL commits implicitly around DDL, so it runs
  outside any transaction: hold a **single idempotent statement**, so that replaying it after a failure is harmless.
  MySQL has no `ADD COLUMN IF NOT EXISTS`: guard in `up()` with the schema, e.g.
  `if ($schema->getTable('host')->hasColumn('host_notes_url')) { return; }`.
- `DmlMigration` for data changes (`INSERT`, `UPDATE`, `DELETE`...). It runs in a transaction with its metadata row:
  all or nothing. DDL, transaction control and `CALL` are rejected, as they would silently commit that transaction.

In both, pass one statement per `addSql()` call, and implement `revert()`, which `down()` calls. `$schema` is read
only: a migration changing it fails, as doctrine would run the SQL of the change out of reach of these checks.

Rules:

- Never delete nor rename a migration once released: a platform where it ran refuses to upgrade until it is restored.
  A fix shipped on a maintained branch is forward-ported with the **same class name**.
- A fix shipped both as a legacy script (maintained branch) and as a migration runs twice on a platform upgraded
  through that branch: the migration must be idempotent.
- A fresh install creates the current schema with `www/install/*.sql`, then runs every core migration: the core
  install SQL is frozen for what a migration changes. Never add the same change to both.
- Once a module ships migrations, its `install.sql` is frozen: new changes come as migrations, which run after it at
  install. Its `uninstall.sql` must still drop everything the module owns, migrations included: an uninstallation
  does not revert them.
- A module version that failed resumes after its last successful `upgrade.sql` query. If that `upgrade.sql` is fixed
  in between, it restarts from its first query: a fixed `upgrade.sql` must be idempotent.

## Operating

```shell
bin/console.new centreon:migrations:status [--set=realtime|configuration | --module=<name>]
bin/console.new centreon:migrations:migrate [--set=realtime|configuration | --module=<name>]
bin/console.new centreon:migrations:rollback <version|prev|first> (--set=realtime|configuration | --module=<name>)
```

- The core sets refuse to run while legacy `Update-*.php` scripts are pending: update the platform first (upgrade
  wizard or API), which runs both in order.
- A module set only runs once its module is installed, and `status` only lists installed modules.
- A failing migration stops the run: a `DmlMigration` leaves nothing behind, the migrations executed before it stay
  applied, and the next run resumes there. A module left with pending migrations is reported as outdated, even at its
  installed version.
- A rollback is explicit and per set: it reverts the migrations executed after the given version, and changes neither
  `informations.version`, nor `mod_release`, nor the code. The legacy scripts stay irreversible.
- Logs go to the `upgrade` channel (`/var/log/centreon/prod.upgrade.log`).

## Known limits

- A module set targets the `centreon` database only: a module owning tables in `centreon_storage` cannot change
  them through migrations yet.
- Migrations run behind web requests (wizard, Extension Manager): a long change on a large table can hit the web
  server timeout. Keep them short, or run them with the console.
