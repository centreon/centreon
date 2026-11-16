# ADR-0067: Build `src/Core` exceptions with named static factories

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Core

## Context

Most `src/Core` exception classes build each case, with its message, through a named static factory, such as `HostGroupException::errorWhileDeleting()`. Other code still throws `new XxxException('…')` with a message written where it is thrown, so the same case reads differently from one use case to the next.

Two alternatives were weighed:

- **One exception class per case, built with `new`.** It lost: it is not the practice of `src/Core`, and it multiplies classes for cases that differ only by their message.
- **Leave the form free.** It lost: messages drift between the places that throw the same case.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Throw a `src/Core` exception of a context's Domain or Application through a named static factory** of its exception class (`HostGroupException::errorWhileDeleting()`), **never through `new` with a message written at the throw site.**

A new case adds a factory to the exception class of its context; the factory takes the business values it needs and the caught exception as `previous` when there is one.

A repository's `Core\Common\Domain\Exception\RepositoryException` is out of this rule: it is built with `new`, with the message and context of each call, as set by [ADR-0029](0029-core-make-src-core-repositories-throw-repository-exception.md).

## Consequences

- A case has one message, defined once, and its name tells the reader what failed.
- Existing `new` throws stay until they are touched.
- `src/App` is out of scope: its exception form is not settled yet.
- The rule is enforced in review.

## When to Amend

- `src/App` settles its own exception form, and `src/Core` should follow it before its move.
