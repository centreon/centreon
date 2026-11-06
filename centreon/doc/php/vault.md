# Vault writes in command handlers

This document describes how a `src/App` command handler orders its vault writes around the transaction the `command.bus` middleware owns ([ADR-0020](adr/0020-apip-let-the-command-bus-own-the-transaction.md)). The vault is not rolled back with the database, so the order of the writes decides which divergence a failure can leave.

- **Write to the vault before saving.** An unreachable vault then saves nothing.
- **Log a warning when the save fails after the vault write.** The vault keeps a value the database does not reference.
- **Remove a whole vault entry only after the commit**, through an event implementing `App\Shared\Domain\Event\DeliveredAfterCommitInterface`. A rollback then never deletes an entry the database still points to.
- **Keys cleared before the save may be missing after a rollback.** This divergence is covered by the same warning.
- **An entry the failing command created itself**, which nothing committed references, may be removed at once when the command fails.
- **A failure of the commit itself**, after the handler returns, leaves the vault ahead of the database. This divergence is accepted.
