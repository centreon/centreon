# ADR-0073: Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in `src/App`

- **Status:** Accepted
- **Date:** 2026-11-18
- **Scope:** APIP

## Context

Without `JSON_THROW_ON_ERROR`, `json_encode()` returns `false` and `json_decode()` returns `null` on an error. `null` is also the result of decoding the valid JSON `null`, and a `false` written to a file or a column becomes an empty string, so malformed data travels silently.

Two alternatives were weighed:

- **Test the result with `json_last_error()`.** It lost: the check is easy to forget, takes several lines, and reads a stale error once another call passes the flag.
- **A shared JSON helper that throws.** It lost: the native flag does the same in one argument, with no class to maintain.

Settled in [#12701](https://github.com/centreon/centreon/pull/12701).

## Decision

**Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call**, as the named argument `flags:` or combined with the other flags, so that an error raises a `\JsonException`.

**Catch the `\JsonException` only where the code can do something with it**, such as rejecting malformed input with a client error; otherwise let it propagate.

## Consequences

- Malformed JSON surfaces where it is read or written, not later as an unexplained `null` or empty string.
- The rule is enforced by the Rector rule `JsonThrowOnErrorFlagRector`, which also runs on `src/Adaptation`. It skips a class or function that calls `json_last_error()`; such code is migrated by hand.

## When to Amend

- PHP makes throwing the default behavior of the JSON functions.
