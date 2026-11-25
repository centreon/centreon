# ADR-0008: Keep `packages/ui` Tailwind class lists to the utilities that change the result

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

A `className` built from utilities grows easily: a class that restates a browser or MUI default (`flex-row`, `items-stretch`, `static`), a responsive or state variant the design does not ask for (`sm:`, `dark:`, `hover:`), an `!` important added to win a conflict. In `packages/ui`, a dead class ships to every consumer, and an `!` blocks the consumer's own utilities on that element.

The rule was first written for the shared assistant rules and reviewed by the frontend chapter.

One alternative was weighed: **a hard limit on the number of classes.** It lost: a complex component legitimately needs many utilities, and a limit moves the problem to a wrapper with the same class list.

Settled in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

## Decision

Write the **fewest utilities that produce the design**, then re-read the `className` and **remove what does not change the result**:

- no class that restates a default, or what the MUI component already styles;
- no variant (`sm:`, `dark:`, `hover:`) the design does not ask for;
- no `!` important to win a conflict: read the computed style and fix the cause.

A long or repeated class list is a signal to simplify the markup or extract a smaller component.

## Consequences

- The goal is no dead class, not a hard limit.
- A component keeps its elements overridable by a consumer's utilities: no `!` on a published component.
- The rule is enforced in review.

## When to Amend

- A lint rule checks dead utilities; the record then names it as the enforcement.
