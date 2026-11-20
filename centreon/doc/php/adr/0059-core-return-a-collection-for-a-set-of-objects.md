# ADR-0059: Return a `Collection` for a set of objects in `src/Core`, never an array of objects

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Core

## Context

`src/Core` returns most sets of objects as arrays, documented as `T[]` or `list<T>`, as `ReadHostGroupRepositoryInterface` does. An array of objects carries its item type only in a PHPDoc that PHP never checks.

`src/Core` already has a typed collection, the abstract `Core\Common\Domain\Collection\ObjectCollection`, extended with one class per item type (`AccessGroupCollection`), but few contexts use it. `src/App` returns `App\Shared\Domain\Collection`, a generic collection built with the class of its items ([ADR-0058](0058-apip-return-a-collection-for-a-set-of-objects.md)), and `src/Core` already imports `App\Shared` classes, such as `#[Sensitive]` and the shared `Assert`.

Two alternatives were weighed:

- **A subclass of `ObjectCollection` per item type.** It lost: it costs a class per item type, and every collection is rewritten when its context moves to `src/App`.
- **A typed array, `list<T>`, checked by PHPStan.** It lost: the type is not checked at runtime.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A method that returns a set of objects returns an `App\Shared\Domain\Collection`**, built with the class of its items, **never an array of objects** (`list<T>`, `array<int, T>`, `T[]`). This holds for a repository, a use case and a Domain object alike.

**An array of scalars stays an array** (`list<int>`, `array<string, string>`).

## Consequences

- `src/Core` and `src/App` share one collection type, so a method keeps its signature when its context moves to `src/App`.
- `src/Core` depends on `App\Shared` for it, as it already does for `#[Sensitive]` and `Assert`, never on another `src/App` context.
- No new `ObjectCollection` subclass is written. The existing ones, such as `AccessGroupCollection`, stay until they are touched.
- Existing methods that return an array of objects keep it until they are touched.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- `App\Shared\Domain\Collection` is replaced.
