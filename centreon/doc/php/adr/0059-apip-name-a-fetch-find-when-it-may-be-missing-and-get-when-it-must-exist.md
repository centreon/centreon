# ADR-0059: Name a `src/App` fetch `find*` when its result may be missing, and `get*` when it must exist

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

`src/App` repositories mostly follow one convention: `CommandRepository::findOneByName()` returns `?Command`, while `CommandRepository::getById()` returns `Command` and throws `CommandNotFoundException`. It is not written down, and exceptions exist: `ResourceAccessRepository::findHostCategoryAccessScope()` never returns `null`.

A `get*` that may return `null` makes every caller check a result it was told exists, or forget to.

Two alternatives were weighed:

- **Only `find*`, and every caller throws when the result is missing.** It lost: each caller repeats the check and picks its own exception.
- **Leave the verb free, and document the contract in the PHPDoc.** It lost: it is today's practice, and the name still says nothing.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A method that fetches one object is named `find*` when the object may be missing**, and returns a nullable type (`?T`).

**It is named `get*` when the object must exist**: it returns a non-nullable type (`T`) and throws a dedicated not-found exception (`CommandNotFoundException`) when the object is missing, declared in its `@throws`.

**A method that fetches a set is named `find*`** and returns an empty set when nothing matches. **A fetch uses no other verb**, such as `resolve*` or `load*`.

This applies to a repository and to any port or service method that fetches.

## Consequences

- The name states the contract: a caller of `find*` handles the missing case, a caller of `get*` does not.
- The rule applies to new methods; renaming an existing one is a change of its own.
- The rule is enforced in review.

## When to Amend

- A fetch needs a third contract, such as a default value when the object is missing.
