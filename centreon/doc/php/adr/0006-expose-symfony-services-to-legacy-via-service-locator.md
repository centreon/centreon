# ADR-0006: Give legacy code access to Symfony services through a dedicated service locator

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

Legacy code, outside the Symfony container, needs Symfony services such as the `InternalApiHttpClient`. The usual shortcut is to declare those services `public` so that legacy code can fetch them from the container, which exposes far more than needed.

## Decision

Let legacy code **boot the kernel** to reach services. The kernel is a singleton, so booting it more than once is not a risk. Collect every service legacy code needs in a **dedicated `ServiceLocator`**, a mini-container, and fetch them from it. **Do not make a service `public`** for legacy code's sake.

## Consequences

- The set of services legacy code depends on is explicit and listed in one place.
- Services stay private, so the container can still optimise and remove unused ones.
- Each new legacy need means adding the service to the locator.
- The opposite direction, `src/App` calling legacy code, is covered by [ADR-0005](0005-isolate-legacy-in-infrastructure-legacy.md).

## When to Amend

- The legacy code that needs Symfony services is migrated to `src/App`, and the locator has no consumers left.
