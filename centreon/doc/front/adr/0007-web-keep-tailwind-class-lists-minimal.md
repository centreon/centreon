# ADR-0007: Keep `www/front_src` Tailwind class lists to the utilities that change the result

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A `className` built from utilities grows easily: a class that restates a browser or MUI default (`flex-row`, `items-stretch`, `static`), a responsive or state variant the design does not ask for (`sm:`, `dark:`, `hover:`), an `!` important added to win a conflict. Each dead class costs a reader a check, and an `!` hides the real cause of a conflict, which the CSS layers already settle.

The rule was first written for the shared assistant rules and reviewed by the frontend chapter.

One alternative was weighed: **a hard limit on the number of classes.** It lost: a complex layout legitimately needs many utilities, and a limit moves the problem to a wrapper component with the same class list.

Settled in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

## Decision

Write the **fewest utilities that produce the design**, then re-read the `className` and **remove what does not change the result**:

- no class that restates a default, or what the MUI component already styles;
- no variant (`sm:`, `dark:`, `hover:`) the design does not ask for;
- no `!` important to win a conflict: read the computed style and fix the cause.

A long or repeated class list is a signal to simplify the markup or extract a component, not to add more utilities.

## Consequences

- The goal is no dead class, not a hard limit: a complex layout may need many utilities.
- A repeated class list across components is a candidate for `packages/ui`.
- The rule is enforced in review.

## When to Amend

- A lint rule checks dead utilities; the record then names it as the enforcement.
