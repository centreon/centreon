# ADR-0045: Back every new `src/Core` enum, with a string by default, and suffix it with `Enum`

- **Status:** Accepted
- **Date:** 2026-11-06
- **Scope:** Core

## Context

Of the 28 enums in `src/Core`, 19 are pure and 21 lack the `Enum` suffix (`HostEvent`, `YesNoDefault`, `UserTheme`); only `AllowedFormatEnum`, `ExportViewEnum` and `RequestedAuthnContextComparisonEnum` are both backed and suffixed. Nothing states a convention. The pure enums come with hand-written converters, and they get copied: `YesNoDefault` is mapped by five `YesNoDefaultConverter` classes, in `Common`, `Service` and `ServiceTemplate`, and `NotificationType` by four `NotificationTypeConverter` classes. Each one repeats the same `match` in both directions, with its own error.

A backed enum gives `from()` and `tryFrom()` for free, and the Symfony serializer reads and writes it by its value. Only a representation that differs from the business value still needs a converter.

Three alternatives were weighed:

- **Keep pure enums, and map them in converters.** It lost: every enum needs its converters and their tests, and the copies drift apart.
- **Back with an `int` storage code** (`0`, `1`, `2` for `YesNoDefault`). It lost: the codes of a legacy column enter the Domain.
- **Leave the suffix out, as most enums do today.** It lost: an enum then reads like a class or a value object at its use site, and [ADR-0043](0043-core-suffix-every-src-core-interface-with-interface.md) already names the kind of type in its suffix.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new `src/Core` enum is backed, and its name ends with `Enum`** (`AllowedFormatEnum`).

**The backing value is a stable business value, which the API exposes as is: a `string` by default, an `int` when the business value is a number**, such as a level or a priority. A storage code is never a backing value, whatever its type.

**Any other representation is mapped in the Infrastructure, never in the enum**: a storage format in the repository, and an API value that already differs from the backing value in the presenter or the request mapping, so that backing an existing enum never breaks the API contract.

## Consequences

- A new enum needs no converter for its business value: `from()`, `tryFrom()` and `->value` replace it.
- The backing value of a case exposed by the API is part of its contract: renaming it is a breaking change, unless the presenter keeps the old value.
- The existing pure and unsuffixed enums keep their shape: the rule applies to new enums, and changing an existing one is a change of its own.
- The rule is enforced in review.

## When to Amend

- `src/Core` code moves to `src/App`, where the APIP record on enums applies.
