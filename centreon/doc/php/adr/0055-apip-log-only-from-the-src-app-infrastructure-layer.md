# ADR-0055: Log only from the `src/App` Infrastructure layer, never from the Application, the Domain or a repository

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** APIP

## Context

`App\Shared\Infrastructure\Messenger\LoggingMiddleware` logs every command and query `command.bus` and `query.bus` dispatch, its outcome, and the exception when its handler fails. A failure that reaches the bus is therefore logged once, at the boundary, with its masked payload.

Some code logs on top of that. Eight Application handlers inject a `LoggerInterface`:

- `UpdateHostCommandHandler` and `PatchHostCommandHandler` log that the vault may diverge from the database when the save fails after the vault write, a fact only the handler knows, then rethrow.
- `DuplicateHostCommandHandler` and `DuplicateHostTemplateCommandHandler` log a failed removal of the vault entry the failing command created, `PurgeHostVaultEventHandler` a failed best-effort after-commit purge, and `CleanHostTemplateServicesEventHandler` a failed after-commit removal of the services of removed templates, which it runs through the `ServiceDeployer` port and ignores.
- `DeployHostServicesEventHandler` and `DuplicateHostServicesEventHandler` log the failure of a command they dispatch through the `command.bus` and then ignore, which `LoggingMiddleware` has already logged.

A logger in the Application layer is an infrastructure concern inside the hexagon: the handler depends on a logging port for a side effect that is not business logic, the same failure is logged twice when it also reaches the bus, and a test has to fake the logger to build the handler. A repository that logs its own failure duplicates the line its caller logs, as [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md) already lets the failure propagate.

The `Upgrade` context logs every step of an upgrade to `prod.upgrade.log` for support, from its Application handler `UpdateCommandHandler` and from some of its repositories, such as `DbalModuleRepository`, `DbalWidgetRepository` and `FileSystemUpdateRepository`. [PR 12170](https://github.com/centreon/centreon/pull/12170) adds more Application classes that log.

Two alternatives were weighed:

- **Let a handler log the failure of a best-effort step it ignores.** It lost: an adapter method whose contract is best-effort can log the failure with the context the handler passes, and keeps the handler free of logging.
- **Let an adapter log every failure before it throws.** It lost: the adapter cannot tell whether its caller ignores the failure, so a failure that propagates is logged twice.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Log only from the Infrastructure layer of `src/App`.** The **Application** and **Domain** layers carry no logging: no logger dependency (constructor parameter, property, trait) and no `debug()`, `info()`, `warning()`, `error()` or other log call.

**A repository carries no logging either**, in any layer: it lets its failure propagate.

**A failure is logged once, in the Infrastructure:**

- `LoggingMiddleware` logs a failure that reaches the bus, including the failure of a command a handler dispatches and then ignores.
- An event handler delivered after the commit runs outside `LoggingMiddleware`. It lets no failure escape, except a dedicated exception whose HTTP status is mapped, such as `VaultPurgeFailedException` (502), which the HTTP kernel logs as an uncaught exception.
- A fact only the handler knows, such as a vault that may diverge from the database, travels in a dedicated exception that wraps the caught one as `previous` and names the business identifiers in its message, since `LoggingMiddleware` logs the message chain, not the exception's properties. An exception listener gives it the HTTP status of the exception it wraps, since `exception_to_status` maps by class, so a rejected input stays a 4xx.
- A step the handler runs best-effort goes through a port method whose contract is best-effort: it never throws, and its adapter logs the failure with the context the handler passes, such as the host id. The handler catches nothing.
- A handler catches a failure only in these cases: the failure of a command it dispatches, or a failure it wraps in a dedicated exception. It never swallows a failure that none of them logs.

## Consequences

- A handler is built and tested without a logger, and a failure raised during the dispatch is logged once.
- The eight handlers above keep their logger until they are touched. The change then raises a dedicated exception (Update, Patch), calls a best-effort port method (the vault removals and the best-effort purge, the removal of template services), or drops the log `LoggingMiddleware` already writes (the event handlers).
- No exception listener passes on the wrapped status yet: the first dedicated exception introduces it.
- A port gains a best-effort method only where a handler ignores the failure, and its adapter gets a logger. `VaultInterface` and `ServiceDeployer` have no such method today.
- The `Upgrade` context complies through [MON-214886](https://centreon.atlassian.net/browse/MON-214886), which moves the logs of its Application handlers and services into Infrastructure decorators, and [MON-214938](https://centreon.atlassian.net/browse/MON-214938), which removes the logger from its repositories; until then, that logging is known debt and does not block [PR 12170](https://github.com/centreon/centreon/pull/12170) or the pull requests stacked on it.
- The rule is blocking in review on new and touched `src/App` code. deptrac does not catch a `Psr\Log` dependency in the Domain or the Application (`Psr\` is in its `Helpers` layer).

## When to Amend

- `LoggingMiddleware` stops logging handler failures.
- Support needs a business trace that only the Application layer can produce.
