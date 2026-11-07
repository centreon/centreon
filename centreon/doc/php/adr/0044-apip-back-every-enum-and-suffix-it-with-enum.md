# ADR-0044: Back every new `src/App` enum, with a string by default, and suffix it with `Enum`

- **Status:** Accepted
- **Date:** 2026-11-06
- **Scope:** APIP

## Context

Of the 44 enums in `src/App`, 36 are string-backed, and all 44 carry the `Enum` suffix. Nothing written says so, and the exceptions get copied. `NotificationOptionEnum` is pure, so its API strings live in a hand-written `NotificationOptionEnumResolver`, with its own test, and the host template detail endpoint copied the pattern into a second resolver ([#12522](https://github.com/centreon/centreon/pull/12522#discussion_r4490416689)). `GorgoneCommunicationTypeEnum` is pure too, and `ResourcePollerTransformer` spells out its API strings by hand. `CommandTypeEnum` is backed by the `command_type` column codes (`1` to `4`), so the API, which speaks the case names, needs a hand-written `fromName()` and a hand-written list of names in `CommandResource`.

A string-backed enum needs none of that: API Platform and the Symfony serializer read and write it by its value, validate an unknown value, and list the cases in the OpenAPI doc. `TriStateEnum` is backed with the strings the API speaks, and the mapping to its database columns stays in the Dbal layer (`TriStateColumnTrait`).

Two alternatives were weighed:

- **Keep pure enums, and map them in the infrastructure.** It lost: every pure enum exposed by the API needs its own resolver and test, and the mapping can drift from the cases it lists.
- **Back with a storage value** (`1` to `4`, `'d'`, `'1'`). It lost: the storage format of a table leaks into the Domain and the API contract.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new `src/App` enum is backed, and its name ends with `Enum`** (`TriStateEnum`, `ActionEnum`), like the interfaces of [ADR-0042](0042-apip-suffix-interfaces-except-domain-repositories.md).

**The backing value is a stable business value, exposed as is when the API exposes it: a `string` by default** (`case UseDefault = 'use_default';`), **an `int` when the business value is a number**, such as a priority (`case High = 3;`). A storage code is never a backing value, whatever its type.

**Any other representation is mapped in the Infrastructure, never in the enum**: a storage format (the engine letters, a numeric column) in the Dbal layer, and an API value that already differs from the backing value in an ApiPlatform transformer, so that backing an existing enum never breaks the API contract.

## Consequences

- API Platform reads and writes an enum without a resolver, and its cases appear in the generated API doc.
- The backing value of a case exposed by the API is part of its contract: renaming it is a breaking change, unless an ApiPlatform transformer keeps the old value.
- A storage mapping stays in the Dbal layer, next to the repository that needs it.
- The existing pure and `int`-backed enums keep their shape: the rule applies to new enums, and backing an existing one is a change of its own, as [#12522](https://github.com/centreon/centreon/pull/12522) does for `NotificationOptionEnum`.
- The rule is enforced in review.

## When to Amend

- API Platform stops (de)serializing backed enums by their value.
