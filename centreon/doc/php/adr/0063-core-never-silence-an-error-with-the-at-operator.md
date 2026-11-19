# ADR-0063: Never silence an error with the `@` operator in `src/Core`

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Core

## Context

Some `src/Core` code silences a call with the `@` operator, such as `@session_start()` in `SLSController`.

`@` hides the warning that says why the call failed. When the code then ignores the return value, the failure goes unnoticed; when it tests it, the error is reported without its cause.

Two alternatives were weighed:

- **Allow `@` when the code tests the return value.** It lost: the cause is still lost, and a review cannot check every test.
- **Leave it to the tools.** It lost: neither the PHP CS Fixer rules nor the PHPStan configuration of the project flag the operator.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Do not use the `@` error-control operator.** Handle the failure explicitly instead: check the precondition before the call, test the return value and throw an exception that names the operation, or use an API that throws, such as Symfony's `Filesystem`.

**The only exception is a deprecation notice**, `@trigger_error($message, E_USER_DEPRECATED)`, which is the PHP and Symfony idiom: the notice reaches the deprecation log where Symfony's `ErrorHandler` is registered, and stays silent elsewhere.

## Consequences

- A failed call surfaces instead of being ignored: the warning, no longer silenced, carries its cause, and the exception names the operation.
- Existing `@` calls stay until their line is touched.
- The rule follows [ADR-0062](0062-apip-never-silence-an-error-with-the-at-operator.md).
- The rule is enforced in review: a search for `@` before a call is only a heuristic, and a hit is confirmed by reading the code.

## When to Amend

- A tool of the project starts to flag the operator, and the review check becomes redundant.
