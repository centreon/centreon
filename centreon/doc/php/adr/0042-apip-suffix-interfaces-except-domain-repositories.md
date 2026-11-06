# ADR-0042: Suffix every new `src/App` interface with `Interface`, except a repository interface

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

`src/App` names its interfaces two ways. Repository interfaces carry no suffix (`HostRepository`), and their implementations are named after their storage (`DbalHostRepository`, `FileSystemPluginRepository`). Among the other interfaces, a few carry the `Interface` suffix (`TransformerInterface`, `VaultInterface`, `AclScopedInterface`), but most do not (`CommandBus`, `CacheClearer`, `DnsResolver`), and their implementations are then named after a mechanism (`MessengerCommandBus`, `SymfonyCacheClearer`, `SystemDnsResolver`). Nothing says which way a new one should go.

Without a suffix, the implementation has to invent a name. The interface `App\Upgrade\Application\UpgradeLogger` is implemented by `LegacyUpgradeLoggerWrapper`, and a new implementation would have to be called `UpgradeChannelLogger` or `MonologUpgradeLogger`, names that describe a mechanism rather than the role. [PR 12170](https://github.com/centreon/centreon/pull/12170) suffixes the interface as `UpgradeLoggerInterface`, so that its implementation takes the short name, `App\Upgrade\Infrastructure\Log\UpgradeLogger`.

Two alternatives were weighed:

- **Never suffix an interface, as most of them do today.** It lost: an interface with a single implementation leaves that implementation with a name invented from its mechanism, case by case.
- **Always suffix, repositories included.** It lost: a repository interface names a business concept of the Domain, and its implementations are already told apart by their storage prefix, so the suffix adds nothing.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Every new `src/App` interface carries the `Interface` suffix, except a repository interface, and its implementation takes the short name** (`UpgradeLoggerInterface`, implemented by `UpgradeLogger`).

**A repository interface**, which gives access to a context's aggregates from `Domain/Repository/`, **never carries the suffix**: `HostRepository`, never `HostRepositoryInterface`. Its implementation is named after its storage (`DbalHostRepository`).

## Consequences

- An implementation is named after its role, and a reader finds it next to its interface by the shared name.
- The Domain keeps speaking its business language: a repository reads as a collection of aggregates, not as a technical contract.
- Existing interfaces keep their names: the rule applies to new interfaces, and renaming an existing one is a change of its own.
- The rule is enforced in review.

## When to Amend

- The Domain repositories stop being named after the aggregate they hold, or their implementations stop being named after their storage.
