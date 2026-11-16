# ADR-0066: Keep the API format out of the `src/Core` Domain

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Core

## Context

In `src/Core`, the presenter maps a use case's response to the API format, but some Domain classes carry it too: `Endpoint::toArray()` returns the `custom_endpoint` field that `FindOpenIdConfigurationResponse` puts in the API response, and `ValueObjectInterface` extends `\JsonSerializable`.

A Domain object that carries the API format changes when the API changes, and couples every consumer of the Domain to one representation.

Two alternatives were weighed:

- **Allow a `toArray()` on a Domain object when it is used only for the API.** It lost: the Domain would still change with the API, and the next consumer would reuse it.
- **Leave the mapping to the move to `src/App`.** It lost: every new Domain class written the old way adds to what the move must undo.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A Domain object carries no API format**: no method that returns or reads the shape of a request or a response (`toArray()`, `fromArray()`, `jsonSerialize()`, an API field name), and no new `JsonSerializable` implementation.

**The presenter maps the use case's response to the API format**, as it already maps the API value of an enum ([ADR-0045](0045-core-back-every-enum-with-a-business-value.md)).

## Consequences

- The Domain does not change when the API changes.
- Existing Domain classes that carry the API format, such as `Endpoint` and the implementations of `ValueObjectInterface`, keep it until they are touched.
- The rule follows [ADR-0065](0065-apip-keep-the-api-format-out-of-the-domain.md), so the mapping is already in place when a context moves to `src/App`.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
