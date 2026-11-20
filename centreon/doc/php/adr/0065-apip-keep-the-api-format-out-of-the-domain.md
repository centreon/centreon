# ADR-0065: Keep the API format out of the `src/App` Domain

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

In `src/App`, the API format lives in the ApiPlatform layer: the resources, their Input and Output DTOs, and the transformers between them and the Domain. The Domain does not know it, but no record says so, and a method such as `toArray()` or `fromArray()` that returns or reads the API shape is a short step away whenever a handler needs to build a response.

A Domain object that carries the API format changes when the API changes, and couples every consumer of the Domain to one representation.

Two alternatives were weighed:

- **Allow a `toArray()` on a Domain object when it is used only for the API.** It lost: the Domain would still change with the API, and the next consumer would reuse it.
- **Make Domain objects `JsonSerializable`.** It lost for the same reason: the JSON shape becomes the Domain's.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A Domain object carries no API format**: no method that returns or reads the shape of a request or a response (`toArray()`, `fromArray()`, `jsonSerialize()`, an API type or field name), and no `JsonSerializable`.

**The ApiPlatform layer maps between the API and the Domain**, in its resources, its Input and Output DTOs and its transformers.

A method that builds a Domain object from Domain values, such as a named constructor, is not an API format and stays allowed.

## Consequences

- The Domain does not change when the API changes, and one aggregate can serve several representations.
- The rule is enforced in review.

## When to Amend

- The API stops being the only external representation of the Domain, such as a message format that must equal the Domain shape.
