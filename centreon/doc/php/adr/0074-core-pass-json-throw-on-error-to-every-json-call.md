# ADR-0074: Pass `JSON_THROW_ON_ERROR` to every new `json_encode()` and `json_decode()` call in `src/Core`

- **Status:** Accepted
- **Date:** 2026-11-17
- **Scope:** Core

## Context

Many `src/Core` calls to `json_encode()` and `json_decode()` omit `JSON_THROW_ON_ERROR`, such as in `ApiWriteCommandRepository` or `UpdateStreamConnectorFile`, which tests the result of `json_decode()` for truthiness.

Without the flag, `json_encode()` returns `false` and `json_decode()` returns `null` on an error. `null` is also the result of decoding the valid JSON `null`, and a `false` written to a file or a column becomes an empty string, so malformed data travels silently.

In `src/App` and `src/Adaptation`, a Rector rule adds the flag to every call ([ADR-0073](0073-apip-pass-json-throw-on-error-to-every-json-call.md)). It is not enabled on `src/Core`: some callers rely on the `false` or `null` result, such as the empty body decoded by `InternalApiClient` after a `204` response, and adding the flag everywhere would turn them into errors.

Three alternatives were weighed:

- **Enable the Rector rule on `src/Core`.** It lost: it changes the contract of every existing call at once, with no review of its callers.
- **Test the result with `json_last_error()`.** It lost: the check is easy to forget, takes several lines, and must run before any other JSON call.
- **A shared JSON helper that throws.** It lost: the native flag does the same in one argument, with no class to maintain.

Settled in [#12701](https://github.com/centreon/centreon/pull/12701).

## Decision

**Pass `JSON_THROW_ON_ERROR` to every new `json_encode()` and `json_decode()` call**, as the named argument `flags:` or combined with the other flags, so that an error raises a `\JsonException`.

**Add the flag to an existing call only after checking its callers**, and replace any test of a `false` or `null` result.

**Catch the `\JsonException` only where the code can do something with it**, such as rejecting malformed input with a client error; otherwise let it propagate.

## Consequences

- Malformed JSON surfaces where it is read or written, not later as an unexplained `null` or empty string.
- Existing calls without the flag stay until their callers are checked.
- The rule is enforced in review.

## When to Amend

- No `src/Core` caller relies on a `false` or `null` result any more, and the Rector rule can be enabled on `src/Core`.
