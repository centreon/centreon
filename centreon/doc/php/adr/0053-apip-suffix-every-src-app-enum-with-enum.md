# ADR-0053: Suffix every new `src/App` enum with `Enum`

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** APIP

## Context

All `src/App` enums carry the `Enum` suffix (`TriStateEnum`, `ActionEnum`), and `EnumAreSuffixedRule` enforces it in `phpstan.new.neon`, but no record states it. Whether an enum is backed is a separate choice, set by [ADR-0044](0044-apip-back-every-enum-with-a-business-value.md): a name could change without changing its cases or their values.

One alternative was weighed:

- **Leave the suffix out.** It lost: an enum then reads like a class or a value object at its use site, and [ADR-0042](0042-apip-suffix-interfaces-except-domain-repositories.md) already names the kind of type in its suffix for interfaces.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#pullrequestreview-5875494780).

## Decision

**Every new `src/App` enum's name ends with `Enum`** (`TriStateEnum`, `ActionEnum`).

## Consequences

- An enum is recognised as one at its use site.
- The rule is enforced by `EnumAreSuffixedRule` in `phpstan.new.neon`.

## When to Amend

- `EnumAreSuffixedRule` is removed, or `src/App` drops type suffixes.
