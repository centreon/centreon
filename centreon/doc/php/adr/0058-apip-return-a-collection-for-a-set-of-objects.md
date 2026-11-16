# ADR-0058: Return a `Collection` for a set of objects, never an array of objects

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

`src/App` already has a generic collection, `App\Shared\Domain\Collection`, built with the class of its items (`new Collection($hosts, Host::class)`), which checks every item it receives. Most repositories and services return it, but some methods still return an array of objects documented as `list<T>` or `array<int, T>`, and nothing says which form a new method should take.

An array of objects carries its item type only in a PHPDoc that PHP never checks, so a wrong item travels until something calls a method on it. It cannot carry behaviour either.

Two alternatives were weighed:

- **A typed array, `list<T>`, checked by PHPStan.** It lost: the type holds only as long as every producer is analysed at level `max`, and the array still accepts any value at runtime.
- **An `iterable` or a generator.** It lost: it can be read only once and cannot be counted, so most callers convert it back to an array.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**A method that returns a set of objects returns an `App\Shared\Domain\Collection`**, built with the class of its items, **never an array of objects** (`list<T>`, `array<int, T>`, `T[]`). This holds for a repository, a port, a service, a handler and a Domain object alike.

**An array of scalars stays an array** (`list<int>`, `array<string, string>`).

## Consequences

- Every set of objects in `src/App` has one type, whose items are checked when the collection is built.
- Existing methods that return an array of objects keep it until they are touched.
- The rule is enforced in review.

## When to Amend

- `App\Shared\Domain\Collection` is replaced, or a set needs a behaviour it cannot offer, such as lazy loading of a large result.
