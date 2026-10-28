# ADR-0016: Access logging and the database from new legacy code only through the `Adaptation` layer

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

[ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md) and [ADR-0006](0006-expose-symfony-services-to-legacy-via-service-locator.md) draw the boundary between `src/App` and legacy: `src/App` reaching into legacy, and legacy reaching back for Symfony services. Neither says how new legacy code itself — everything outside `src/App` (`src/Core`, `www/`, `src/Centreon*`, modules) — should log or reach the database.

The legacy codebase offers several entry points for both, most of them predating the `Adaptation` layer. Logging can go through `CentreonLog`, `error_log()` or the DDD `LoggerTrait` (`Centreon\Domain\Log\LoggerTrait`); the database can be reached through `CentreonDB`, `DatabaseConnection` or a repository extending `AbstractRepositoryRDB`. `CentreonLog` is deprecated and already reroutes through `Adaptation\Log\Logger` (see [logging.md](../logging.md)), and both `CentreonDB` and `DatabaseConnection` extend `\PDO`, so reaching for them invites the raw `prepare()`/`bindValue()`/`fetch*()` idiom that the `ConnectionInterface` was built to replace.

Without a rule, new code keeps reaching for whichever entry point is nearest, and the modern bridge that already exists goes unused. The convention below is already applied in review; this ADR records it.

## Decision

For any **new** class, method or function outside `src/App`:

- **Logging:** log through `Adaptation\Log\Logger` only (`Logger::create(LogChannelEnum::…)->…`), or one of its semantic siblings (`LoggerAuthentication`, `LoggerPassword`, `LoggerToken`, `LoggerUpgrade`). Never `CentreonLog`, never `error_log()`, never the DDD `LoggerTrait` (`Centreon\Domain\Log\LoggerTrait`), which is the injected logger of the modern `src/Core` / `src/App` stack. Repositories are stricter — see below.
- **Database:** reach the database only through `Adaptation\Database\Connection\ConnectionInterface` (the `DbalConnectionAdapter`), passing values as `QueryParameters`. Never `CentreonDB`, never `DatabaseConnection`, and therefore none of the `\PDO` statement methods either.
- **Repositories:** a new legacy repository must extend `Core\Common\Infrastructure\Repository\DatabaseRepository`, which injects `ConnectionInterface`, never the legacy `AbstractRepositoryRDB`. A repository must **not log at all** — not through the `Adaptation` layer either — and must **not swallow a failure**: it wraps the caught infrastructure exception in a `Core\Common\Domain\Exception\RepositoryException` (chaining the previous) and lets it surface, rather than logging and returning a fallback value.

## Consequences

- New legacy code reaches logging and the database through the same `Adaptation` interfaces as the rest of the modern stack, and the `\PDO` surface stays out of new code.
- The rule is **strict and blocking** on any new class, method or function. A plain adjustment to existing code is **not** blocking: improve it when reasonable, otherwise leave it, so a small change is not forced to drag a whole legacy class onto the new base.
- A new method added to a pre-existing repository keeps the class's `extends`, since `DatabaseConnection implements ConnectionInterface`: the `ConnectionInterface` methods are already available on the injected connection. Only a new repository **file** must extend `DatabaseRepository`.
- A repository that neither logs nor catches-and-returns makes failures visible to its caller as a typed `RepositoryException`, so the error surfaces once, at the layer that can decide what to do with it, instead of being hidden behind a logged fallback value.
- This is the complement to [ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md) and [ADR-0006](0006-expose-symfony-services-to-legacy-via-service-locator.md): those two govern the `src/App` ↔ legacy boundary, this one governs how new legacy code reaches its own logging and database.
- The rule is enforced in review. The deprecated entry points are not removed, so a search for `CentreonLog`, `error_log()`, `CentreonDB` or `AbstractRepositoryRDB` is only a heuristic: a hit is confirmed by reading the code and checking it is new.

## When to Amend

- Legacy is fully migrated to `src/App` and no code lives outside it.
- The `Adaptation` layer's logging or database interfaces are replaced.
