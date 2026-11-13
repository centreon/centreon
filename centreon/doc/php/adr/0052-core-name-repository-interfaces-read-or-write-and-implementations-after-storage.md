# ADR-0052: Name a `src/Core` repository interface `Read*` or `Write*`, and its implementation after its storage

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** Core

## Context

`src/Core` splits most repositories into read and write contracts under `Application/Repository/` (`ReadHostGroupRepositoryInterface`, `WriteHostGroupRepositoryInterface`), and names their implementations after their storage (`DbReadHostGroupRepository`, `ApiReadHostGroupRepository`). `phpstan.core.neon` checks the `Repository` and `RepositoryInterface` endings and that an implementation's name contains its interface's name; no record states the `Read`/`Write` split or the storage prefix. `src/App` names its repositories differently ([ADR-0042](0042-apip-suffix-interfaces-except-domain-repositories.md)), so a developer working in both may carry its naming over.

The suffix of the interface is a separate choice, set by [ADR-0043](0043-core-suffix-every-src-core-interface-with-interface.md).

One alternative was weighed:

- **One repository interface per aggregate, as `src/App` does.** It lost: it would split the naming inside each `src/Core` context, and a context that moves to `src/App` is renamed then anyway.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#pullrequestreview-5875494780).

## Decision

**A new `src/Core` repository interface is named `Read*` or `Write*`** (`ReadHostGroupRepositoryInterface`, never `HostGroupRepositoryInterface`).

**Its implementation is named after its storage**, followed by the interface's name without the suffix (`DbReadHostGroupRepository`, `ApiReadHostGroupRepository`).

## Consequences

- Reads and writes of a context are found by their prefix, and two implementations of one interface are told apart by their storage.
- Existing repositories keep their names.
- The `Read`/`Write` prefix and the storage prefix are enforced in review. `RepositoryInterfaceNameCustomRule` and `RepositoryNameCustomRule` only check the `RepositoryInterface` and `Repository` endings, and `RepositoryNameValidationByInterfaceCustomRule` checks that an implementation's name contains its interface's name without the suffix.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
