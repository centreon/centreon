# ADR-0016: Access logging and the database from legacy code only through the `Adaptation` layer

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

[ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md) and [ADR-0006](0006-expose-symfony-services-to-legacy-via-service-locator.md) draw the boundary between `src/App` and legacy: `src/App` reaching into legacy, and legacy reaching back for Symfony services. Neither says how legacy code itself — everything outside `src/App` (`src/Core`, `www/`, `src/Centreon*`, modules) — should log or reach the database.

The legacy codebase offers several entry points for both, most of them predating the `Adaptation` layer. Logging can go through `CentreonLog`, `error_log()` or the `Centreon\Domain\Log\*` classes (`Logger` and `LoggerTrait`); the database can be reached through `CentreonDB`, `DatabaseConnection` or a repository extending `AbstractRepositoryRDB`. `CentreonLog` is deprecated and already reroutes through `Adaptation\Log\Logger` (see [logging.md](../logging.md)), and both `CentreonDB` and `DatabaseConnection` extend `\PDO`, so reaching for them invites the raw `prepare()`/`bindValue()`/`fetch*()` idiom that the `ConnectionInterface` was built to replace.

Without a rule, code keeps reaching for whichever entry point is nearest, and the modern bridge that already exists goes unused. The convention below is already applied in review; this ADR records it.

## Decision

- **Logging — all code outside `src/App`, new and existing.** Log through `Adaptation\Log\Logger` only (`Logger::create(LogChannelEnum::…)->…`), or one of its semantic siblings (`LoggerAuthentication`, `LoggerPassword`, `LoggerToken`, `LoggerUpgrade`). Never `CentreonLog`, never `error_log()`, and never the `Centreon\Domain\Log\*` classes (its `Logger` and its `LoggerTrait`). This is the one rule here not limited to new code: route a log through `Adaptation\Log\Logger` whenever you write or touch one in legacy. Repositories are stricter — see below.

  Pass the business data as the log **context** (the second argument); including it in the message as well is fine. **Never reuse a caught exception's `getMessage()`** as the message — write your own, and let the context carry the detail:

  ```php
  Logger::create(LogChannelEnum::WEB)->error(
      'Unable to delete the host group',
      ['host_group_id' => $hostGroupId],
  );
  ```

- **Database — new code outside `src/App`.** For any new class, method or function, reach the database only through `Adaptation\Database\Connection\ConnectionInterface` (the `DbalConnectionAdapter`), passing values as `QueryParameters`. Never `CentreonDB`, never `DatabaseConnection`, and therefore none of the `\PDO` statement methods either.
- **Repositories — new repository files.** A new legacy repository must extend `Core\Common\Infrastructure\Repository\DatabaseRepository`, which injects `ConnectionInterface`, never the legacy `AbstractRepositoryRDB`. A repository must **not log at all** — not through the `Adaptation` layer either — and must **not swallow a failure**: it wraps the caught infrastructure exception in a `Core\Common\Domain\Exception\RepositoryException`, passing its own business message, the business data as **`context`**, and the caught exception as **`previous`** (the chain preserves the cause). Never copy the caught exception's `getMessage()` into the new message:

  ```php
  try {
      $this->connection->executeStatement(
          'DELETE FROM `:db`.hostgroup WHERE hg_id = :id',
          QueryParameters::create([QueryParameter::int('id', $hostGroupId)]),
      );
  } catch (ConnectionException $exception) {
      throw new RepositoryException(
          message: 'Error while deleting the host group',
          context: ['host_group_id' => $hostGroupId],
          previous: $exception,
      );
  }
  ```

## Consequences

- Logging and database access in legacy go through the same `Adaptation` interfaces as the rest of the modern stack, and the `\PDO` surface stays out of new code.
- The **database** and **repository** rules are strict and blocking on any new class, method or function, but do not force a rewrite of existing code: improve it when reasonable, otherwise leave it, so a small change is not made to drag a whole legacy class onto the new base. The **logging** rule is the exception — it has no new-only limit and applies to existing code too.
- A new method added to a pre-existing repository keeps the class's `extends`, since `DatabaseConnection implements ConnectionInterface`: the `ConnectionInterface` methods are already available on the injected connection. Only a new repository **file** must extend `DatabaseRepository`.
- A repository that neither logs nor catches-and-returns makes failures visible to its caller as a typed `RepositoryException`, so the error surfaces once, at the layer that can decide what to do with it, instead of being hidden behind a logged fallback value.
- This is the complement to [ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md) and [ADR-0006](0006-expose-symfony-services-to-legacy-via-service-locator.md): those two govern the `src/App` ↔ legacy boundary, this one governs how legacy code reaches its own logging and database.
- The rule is enforced in review. The deprecated entry points are not removed, so a search for `CentreonLog`, `error_log()`, `CentreonDB` or `AbstractRepositoryRDB` is only a heuristic: a hit is confirmed by reading the code.

## When to Amend

- Legacy is fully migrated to `src/App` and no code lives outside it.
- The `Adaptation` layer's logging or database interfaces are replaced.
