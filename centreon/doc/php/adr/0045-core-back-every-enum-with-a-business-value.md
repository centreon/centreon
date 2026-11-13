# ADR-0045: Back every new `src/Core` enum with a business value, a string by default

- **Status:** Accepted
- **Date:** 2026-11-06
- **Scope:** Core

## Context

Most `src/Core` enums are pure (`HostEvent`, `YesNoDefault`, `UserTheme`). Nothing states a convention. A pure enum has no value of its own, so every representation goes through a hand-written converter, and the copies drift: `YesNoDefault` has several `YesNoDefaultConverter` classes, whose copies throw different exceptions (`ValueError`, `AssertionException`), and the two `NotificationType` enums have several `NotificationTypeConverter` classes.

A backed enum gives `from()` and `tryFrom()` for its business value, which leaves one converter per other representation, in the Infrastructure.

Two alternatives were weighed:

- **Keep pure enums, and map them in converters.** It lost: every representation needs its converter, and the copies drift apart.
- **Back with a storage value** (`0`, `1`, `2` for `YesNoDefault`). It lost: the codes of a legacy column enter the Domain.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new `src/Core` enum is backed.**

**The backing value is a stable business value, exposed as is when the API exposes it: a `string` by default, an `int` when the business value is a number**, such as a priority. A storage code is never a backing value, whatever its type.

**Any other representation is mapped in the Infrastructure, never in the enum**: a storage format in the repository, and an API value that already differs from the backing value in the presenter or the request mapping, so that backing an existing enum never breaks the API contract.

## Consequences

- A new enum needs no converter for its business value: `from()`, `tryFrom()` and `->value` replace it.
- The backing value of a case exposed by the API is part of its contract: renaming it is a breaking change, unless the presenter keeps the old value.
- The existing pure enums keep their shape: the rule applies to new enums, and changing an existing one is a change of its own.
- The rule is enforced in review.

## When to Amend

- PHP stops giving backed enums `from()` and `tryFrom()`.
