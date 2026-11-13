# ADR-0054: Suffix every new `src/Core` enum with `Enum`

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** Core

## Context

Most `src/Core` enums lack the `Enum` suffix (`HostEvent`, `YesNoDefault`, `UserTheme`). Nothing states a convention, and no tool checks one. Whether an enum is backed is a separate choice, set by [ADR-0045](0045-core-back-every-enum-with-a-business-value.md): a name could change without changing its cases or their values.

One alternative was weighed:

- **Leave the suffix out, as most enums do today.** It lost: an enum then reads like a class or a value object at its use site, and [ADR-0043](0043-core-suffix-every-src-core-interface-with-interface.md) already names the kind of type in its suffix for interfaces.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#pullrequestreview-5875494780).

## Decision

**Every new `src/Core` enum's name ends with `Enum`** (`AllowedFormatEnum`).

## Consequences

- An enum is recognised as one at its use site.
- The existing unsuffixed enums keep their names: renaming one is a change of its own.
- The rule is enforced in review. `EnumAreSuffixedRule` runs only in `phpstan.new.neon`, not on `src/Core`: enabling it on `src/Core` needs a baseline for the existing unsuffixed enums.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
