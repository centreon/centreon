# ADR-0020: Let the command bus own the transaction, and keep handlers free of transaction management

- **Status:** Accepted
- **Date:** 2026-10-28
- **Scope:** APIP

## Context

A command handler changes the system and must do so atomically: on failure, nothing is written. The obvious way is for each handler to open a transaction, commit at the end and roll back in a `catch`. Done by hand in every handler, that boilerplate is repeated, easy to get wrong (a forgotten rollback, a commit on a half-failed path), and it couples the handler to the connection.

The platform already wraps every `command.bus` dispatch in `App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware` ([#7819](https://github.com/centreon/centreon/pull/7819), wired in `config.new/packages/messenger.yaml`): it opens a transaction on `doctrine.dbal.default_connection`, lets the handler run, commits on success and rolls back on any exception.

That transaction covers only the default DBAL connection. Writes through the `realtime` DBAL connection (`doctrine.dbal.realtime_connection`), through a legacy connection (`CentreonDB`, `DatabaseConnection`, or a service of the second kernel built by `LegacyContainer` or `LegacyKernelWrapper`), to the vault or to Gorgone are not rolled back with it. `NonTransactionalCommand` was added for such cases in [#11216](https://github.com/centreon/centreon/pull/11216).

Two alternatives were weighed:

- **Each handler opens and commits its own transaction.** It lost: the boilerplate is repeated, a rollback is easily forgotten, and the handler is coupled to the connection.
- **Wrap the handler body in `Connection::transactional()`.** It lost for the same coupling, and it leaves the choice to each handler instead of the bus.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

A command handler **does not manage transactions**. It writes through its repositories and lets the result stand or throw; the `command.bus` middleware opens, commits and rolls back the surrounding transaction. Do **not** call `beginTransaction()`, `commit()`, `rollBack()` or `transactional()` from a `src/App` handler, or from a repository reached through `command.bus`, and do not inject the connection to do so. A repository lets an infrastructure failure propagate: it may translate a DBAL exception into a domain exception, keeping it as `previous`, but never catches it to return a fallback. Code outside `src/App` is not dispatched through `command.bus` and is out of scope of this ADR.

An event handler delivered after the commit (`DeliveredAfterCommitInterface`) runs outside any transaction: when it must write to the database, it dispatches a command through `command.bus`, which owns that write's transaction, as `DeployHostServicesEventHandler` does.

Mark a command with `App\Shared\Application\Command\NonTransactionalCommand` **only** when the managed transaction cannot cover its writes: they go through a connection or a system the transaction does not cover (`DuplicateHostServicesCommand` clones through legacy code; `UpdateCommand` runs upgrade scripts through `CentreonDB` and the `realtime` connection). The middleware then runs the handler without adding a transaction. The marker does **not** isolate the handler from a parent transaction: dispatched from a handler already inside one, its writes share it. Do **not** use the marker to make a write best-effort. Work that must run **after** the commit uses an event implementing `App\Shared\Domain\Event\DeliveredAfterCommitInterface`.

## Consequences

- Handlers hold business logic only; the rollback of the default connection is handled by one middleware instead of per-handler boilerplate.
- A handler that throws gets the command's writes on the default connection rolled back, so it must let infrastructure failures surface instead of catching and returning.
- Writes outside the default connection are not rolled back, even when they come last: the commit itself, or a parent transaction, can still fail after them. A handler that writes to one of them orders its writes so that a rollback never leaves the database pointing at a removed vault entry, and accepts that the external system may be left ahead of the database. How this applies to the vault is described in [vault.md](../vault.md).
- `NonTransactionalCommand` is a visible opt-out. It buys no atomicity and does **not** defer work until a commit.
- This complements [ADR-0014](0014-apip-one-aggregate-per-command.md): one command changes one aggregate in one transaction.
- The rule is enforced in review. An existing repository that manages its own transaction is handled by who calls it:
  - reached only through `command.bus`, its transaction is redundant and only opens a savepoint (`DbalCommandRepository`, `DbalBrokerConfigurationRepository`, `DbalEngineConfigurationRepository`): it is migrated when touched;
  - called outside the bus, its transaction is the only thing keeping its writes atomic and stays (`DbalTokenRepository::update`, called from `TokenAuthenticator` and `OpenIdIdp`).

## When to Amend

- A use case needs a transaction boundary the per-message middleware cannot express (nested or cross-bus transactions).
- The messaging stack changes such that the middleware no longer wraps every command.
