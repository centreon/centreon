# ADR-0019: Let the command bus own the transaction, and keep handlers free of transaction management

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

A command handler changes the system and must do so atomically: on failure, nothing is written. The obvious way is for each handler to open a transaction, commit at the end and roll back in a `catch`. Done by hand in every handler, that boilerplate is repeated, easy to get wrong (a forgotten rollback, a commit on a half-failed path), and it couples the handler to the connection.

The platform already wraps every `command.bus` dispatch in `App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware` (wired in `config.new/packages/messenger.yaml`): it opens a transaction, lets the handler run, commits on success and rolls back on any exception, re-throwing it with its cause. The transaction is therefore already there, around the handler, for free.

## Decision

A command handler **does not manage transactions**. It writes through its repositories and lets the result stand or throw; the `command.bus` middleware opens, commits and rolls back the surrounding transaction. Do **not** call `beginTransaction()`, `commit()` or `rollBack()` from a handler, and do not inject the connection to do so.

When a command must **not** run inside the managed transaction — for example a best-effort, after-commit legacy relink whose own failure must not roll back the main write — mark the command with `App\Shared\Application\Command\NonTransactionalCommand`. The middleware then runs the handler without a transaction, so its writes are no longer rolled back together — use it only when that is acceptable, or when the work manages its own persistence.

## Consequences

- Handlers hold business logic only; atomicity is guaranteed uniformly by one audited middleware instead of per-handler boilerplate.
- A handler that throws gets the whole command rolled back, so it must let infrastructure failures surface (a repository raises a `RepositoryException` rather than swallowing it, as [ADR-0016](0016-access-logging-and-database-through-adaptation-layer.md) requires) instead of catching and returning.
- `NonTransactionalCommand` is a deliberate, visible opt-out from the managed transaction. It buys no automatic atomicity — the handler's writes are not rolled back together — so it is chosen for work that must not be wrapped (best-effort or after-commit work whose failure must not undo a main write) or that manages its own persistence, never as a shortcut.
- This complements [ADR-0014](0014-one-aggregate-per-command.md): one command changes one aggregate in one transaction, and a follow-up on another aggregate reacts through an event — itself dispatched and committed under the same mechanism.

## When to Amend

- A use case needs a transaction boundary the per-message middleware cannot express (nested or cross-bus transactions).
- The messaging stack changes such that the middleware no longer wraps every command.
