# ADR-0043: Suffix every new `src/Core` interface with `Interface`, repository interfaces included

- **Status:** Accepted
- **Date:** 2026-11-06
- **Scope:** Core

## Context

`src/Core` names its interfaces with the `Interface` suffix almost without exception. Its repository interfaces, mostly split into read and write contracts under `Application/Repository/`, carry it too (`ReadHostGroupRepositoryInterface`), and their implementations are named after their storage (`DbReadHostGroupRepository`, `ApiReadHostGroupRepository`). The only interfaces without the suffix are capability interfaces such as `Comparable` and `Identifiable`. Nothing states the convention, and `src/App` follows the opposite rule for repositories, so a developer working in both may carry it over.

Two alternatives were weighed:

- **Drop the suffix on repository interfaces, as `src/App` does.** It lost: it would split the naming inside each `src/Core` context for no gain, and a context that moves to `src/App` is renamed then anyway.
- **Let each new interface choose.** It lost: the naming of a context would depend on who wrote the last interface.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new `src/Core` interface carries the `Interface` suffix, repository interfaces included** (`ReadHostGroupRepositoryInterface`, never `ReadHostGroupRepository`).

## Consequences

- A `src/Core` context keeps one naming convention, the one its existing code already follows.
- When a context moves to `src/App`, its repository interfaces drop the suffix there.
- Existing interfaces keep their names.
- On a repository interface, the suffix is checked indirectly by `RepositoryInterfaceNameCustomRule` in `phpstan.core.neon`, on the class that implements it; an interface without implementation, and the other interfaces, are checked in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
