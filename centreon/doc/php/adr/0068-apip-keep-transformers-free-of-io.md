# ADR-0068: Keep `src/App` Transformers free of I/O

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

A Transformer maps one representation to another ([ADR-0003](0003-apip-name-transformers-after-their-output.md), [ADR-0004](0004-apip-transformers-are-dedicated-classes.md)). Most of them only map, but some also fetch: `HostNotificationsTransformer` loads contact, contact group and time period names through three repositories, and `OpenIdConfigurationTransformer` reads the client secret from the vault.

A Transformer that fetches hides a query in a mapping, runs it once per transformed object, and cannot be tested without fakes for what it reads.

Two alternatives were weighed:

- **Allow a Transformer to fetch what it maps.** It lost: the queries become invisible to the caller, which cannot batch them.
- **Allow it only through a port, never a connection.** It lost: a port still hides the I/O, and the test still needs a fake.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A Transformer is pure**: `transform()` maps its input to its output with no I/O and no side effect. Its constructor injects **no connection, repository, port, bus or client**.

**What a mapping needs is fetched before it**, by the repository, the provider or the processor, and passed to the Transformer as input.

## Consequences

- The queries a mapping needs are visible to its caller, which can fetch them once for a whole page.
- A Transformer is unit-tested with plain values.
- `HostNotificationsTransformer` and `OpenIdConfigurationTransformer` keep their dependencies until they are touched.
- The rule is enforced in review.

## When to Amend

- A mapping needs data that only the Transformer can know how to fetch.
