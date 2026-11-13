# Vault writes in command handlers

This document describes how a `src/App` command handler orders its vault writes around the transaction the `command.bus` middleware owns ([ADR-0020](adr/0020-apip-let-the-command-bus-own-the-transaction.md)). The vault is not rolled back with the database, so the order of the writes decides which divergence a failure can leave.

- **Write to the vault before saving.** An unreachable vault then saves nothing.
- **Rethrow when the save fails after the vault write.** The vault keeps a value the database does not reference. Under [ADR-0055](adr/0055-apip-log-only-from-the-src-app-infrastructure-layer.md), the handler rethrows the failure wrapped in a dedicated exception whose message names the aggregate's identifier and says the vault may diverge, and which keeps the HTTP status of the wrapped one through an exception listener the first such exception adds; `LoggingMiddleware` logs it, and the handler logs nothing.
- **Remove a whole vault entry only after the commit**, through an event implementing `App\Shared\Domain\Event\DeliveredAfterCommitInterface`. A rollback then never deletes an entry the database still points to.
- **Keys cleared before the save may be missing after a rollback.** The dedicated exception logged on the save reports this divergence.
- **An entry the failing command created itself**, which nothing committed references, may be removed at once when the command fails. The removal is best-effort: the handler goes on when it fails, and the failure is logged once with the aggregate's identifier. Under ADR-0055, that log moves to a best-effort method of the vault adapter.
- **A failure of the commit itself**, after the handler returns, leaves the vault ahead of the database. This divergence is accepted.

**Current state:** the handlers still log on their own. `UpdateHostCommandHandler` and `PatchHostCommandHandler` log a `warning` with the host id and rethrow the original failure, and `DuplicateHostCommandHandler` and `DuplicateHostTemplateCommandHandler` log the failed removal of the entry the failing command created. Remove this paragraph once they follow ADR-0055.
