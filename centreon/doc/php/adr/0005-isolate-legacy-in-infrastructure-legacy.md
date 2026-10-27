# ADR-0005: Isolate legacy code used from `src/App` in `Infrastructure/Legacy` adapters

- **Status:** Accepted
- **Date:** 2026-09-03

## Context

During migrations, `src/App` sometimes has to call legacy code that is not migrated yet. In a real case, `CentreonDB` was injected directly into the upgrade repository as a surgical fix for a release blocker. Deptrac only accepted it because the `upgrade` bounded context was not registered in the deptrac configuration. Without a rule, legacy calls spread across `src/App` and the debt stays invisible.

## Decision

Wrap any legacy code consumed from `src/App` in an **adapter under `Infrastructure/Legacy/`**, behind a clean interface that does not leak legacy concepts. Register the dependency in the deptrac **`skip_violations`**, explicitly as temporary. For **every file created in `Infrastructure/Legacy/`**, open a technical Jira ticket that documents and **sizes its removal**, on the PHP Backend board with a tech-debt label or epic.

## Consequences

- Legacy calls are confined to one place per need, and the domain never sees legacy types.
- The `Legacy/` folder must drain over time. The tickets are repaid opportunistically in sprints.
- A release-blocker fix may still introduce legacy code, but as the smallest change possible, commented and tracked.
- Exception: the `Upgrade` context's `LegacyConnectionFactory` returns `\CentreonDB`. Its consumers are the legacy upgrade scripts themselves, which need the legacy connection API, so no clean interface can stand between them.
- The rule is not enforced by tooling yet. A check could flag `CentreonDB` or legacy imports in `src/App` outside `Infrastructure/Legacy/`.
- The reverse direction, legacy code calling Symfony services, is covered by [ADR-0006](0006-expose-symfony-services-to-legacy-via-service-locator.md).

## When to Amend

- The legacy layer used by `src/App` is fully migrated and the folder is empty.
- Deptrac gains a better mechanism for time-boxed exceptions.
