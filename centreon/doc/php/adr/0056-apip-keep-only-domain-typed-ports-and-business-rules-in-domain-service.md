# ADR-0056: Keep only domain-typed ports and pure business rules in `Domain/Service`

- **Status:** Accepted
- **Date:** 2026-11-15
- **Scope:** APIP

## Context

A `src/App` context has two `Service/` folders, and nothing says what goes where. In `MonitoringConfiguration`, `Domain/Service` holds [ports](../glossary.md#port) such as `DnsResolver`, `ServiceDeployer` and `GorgoneNodesSynchronizer` next to pure business rules such as `HostAddressResolver`, while `Application/Service` holds concrete classes shared by several handlers. `DnsResolver` and `ServiceDeployer` take value objects of the Domain; `GorgoneNodesSynchronizer::synchronize(): void` takes none.

The question came up in the review of [PR 12170](https://github.com/centreon/centreon/pull/12170), which first moved every port of the `Upgrade` context to `Domain/Service`, including technical ones (locks, cache, logging) that no Domain class uses and whose signatures carry no Domain type.

Two alternatives were weighed:

- **Every port other than a repository in `Domain/Service`.** It lost: the Domain would declare technical capabilities it never uses, logging included, against [ADR-0055](0055-apip-log-only-from-the-src-app-infrastructure-layer.md).
- **Every port in the layer that calls it.** It lost: Domain ports such as `ServiceDeployer` are only called by handlers, yet belong to the language of the Domain, as repositories do.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

- **`Domain/Service/` holds only two kinds of classes:** the ports of the context, other than repositories, **whose signatures take or return a Domain type**, and **stateless business rules**, concrete classes that depend only on the Domain.
- **A port whose signatures use no Domain type** (a lock, a cache, a log, a file to write) is a technical capability: it lives in **`Application/`**, next to the handlers that call it.
- **`Application/Service/` holds only concrete classes** shared by several handlers of the context: they call ports and repositories, take locks and chain steps. No interface goes there.

## Consequences

- Whether a port belongs to the Domain is read from its signatures, without a debate in review.
- The Domain never declares a logging, lock or cache port.
- The rule applies to new ports. An existing port that breaks it, such as `GorgoneNodesSynchronizer`, moves in a dedicated refactor, never in the middle of a feature.
- The rule is enforced in review.

## When to Amend

- A context needs a technical port that the Domain itself calls.
