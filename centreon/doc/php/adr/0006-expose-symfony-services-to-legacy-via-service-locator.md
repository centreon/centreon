# ADR-0006: Give legacy code access to Symfony services through a dedicated service locator

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

Legacy code, outside the Symfony container, needs Symfony services such as the internal API client (`internal_api_client`). The usual shortcut is to declare those services `public` so that legacy code can fetch them from the container, which exposes far more than needed.

## Decision

Let legacy code **boot the kernel** to reach services. Legacy web code gets the kernel from `Kernel::createForWeb()`, which builds it once per request and reuses it. Collect every service legacy code needs in a **dedicated `ServiceLocator`** (`legacy.service_locator`), a mini-container, and fetch them from it. Keep the services exposed through `legacy.service_locator` private. The locator itself must remain public so legacy code can fetch it.

## Consequences

- The set of services legacy code depends on is explicit and listed in one place.
- Services stay private, so the container can still optimise and remove unused ones.
- Each new legacy need means adding the service to the locator.
- `src/App` code that calls legacy code through `LegacyContainer` boots a second, separate `LegacyKernel`, with its own services and database connection. Work done through it is not part of the caller's transaction.
- The opposite direction, `src/App` calling legacy code, is covered by [ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md).

## When to Amend

- The legacy code that needs Symfony services is migrated to `src/App`, and the locator has no consumers left.
