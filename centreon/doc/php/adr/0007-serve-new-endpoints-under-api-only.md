# ADR-0007: Run legacy and new endpoints side by side, and serve new endpoints under `/api` only

- **Status:** Accepted
- **Date:** 2026-07-22

## Context

All endpoints used to live under `/api/latest/`. Migrating a legacy endpoint to API Platform can change its response format or its error codes, which breaks the frontend and other clients.

## Decision

Keep serving the **legacy** endpoints unchanged under **`/api/latest/`**. Serve the **new** API Platform endpoints under **`/api/`**. The API Platform endpoints that existed before this split are listed in `LegacyApiPrefixAliasLoader` and stay available under both prefixes. **Expose every new endpoint under `/api` only**, never under `/api/latest`.

## Consequences

- A legacy endpoint can be migrated with a new format or new error codes without a BC break. The frontend keeps using the old endpoint and switches feature by feature.
- New API Platform endpoints coexist with their API v2 equivalents until clients have moved.
- The list of dual-exposed endpoints in `LegacyApiPrefixAliasLoader` is legacy. It shrinks, it never grows.
- The rule is not enforced by tooling yet. A search for `latest` in new routes or `ApiResource` prefixes catches a violation.
- Whether non-legacy endpoints return standard errors instead of mimicking legacy ones is not decided by this ADR.

## When to Amend

- `/api/latest` is retired, because all legacy endpoints are migrated or removed.
- A versioning scheme, such as `/api/v3`, is introduced.
