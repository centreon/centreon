# ADR-0057: Log only from the `src/Core` Infrastructure layer, never from a use case, the Domain or a repository

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Core

## Context

`src/Core` logs in every layer: use cases such as `AddAcc` and `DeleteAcc` log their failures themselves, and a few Domain classes, such as `OpenIdProvider`, log too. Some endpoints already log from their presenter instead, which receives the failure in an `ErrorResponse` carrying the caught exception and its context (`FindMediasPresenter`, through loggers [ADR-0016](0016-core-log-src-core-through-adaptation-logger.md) forbids).

A logger in a use case or a Domain class is an infrastructure concern inside the hexagon, and a repository that logs its own failure duplicates the line its caller logs, as [ADR-0029](0029-core-make-src-core-repositories-throw-repository-exception.md) already lets the failure propagate.

One alternative was weighed:

- **Keep logging in every layer, as `src/Core` does today, and leave taking it out to the move to `src/App`.** It lost: the presenter already receives the exception and its context, so a touched use case can hand its log line over at no cost.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Log only from the Infrastructure layer of `src/Core`.** A use case and a Domain class log nothing: a use case hands a failure to its presenter in an `ErrorResponse` that carries the caught exception and the business context, and the presenter logs it.

**A repository never logs**: it throws a `RepositoryException` ([ADR-0029](0029-core-make-src-core-repositories-throw-repository-exception.md)).

An example is in [logging.md](../logging.md#14-logging-from-core).

## Consequences

- A use case is built and tested without a logger, and its failure is logged once, by its presenter.
- Existing use cases and Domain classes that log keep their lines until they are touched; a touched use case then moves its log line to its presenter.
- `DomainCallNamespacesCustomRule` does not see a `Logger::create()` call, so the rule is enforced in review, blocking on new and touched code.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- Support needs a business trace that only a use case can produce.
