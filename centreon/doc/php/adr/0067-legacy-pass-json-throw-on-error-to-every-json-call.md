# ADR-0067: Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in legacy code

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** Legacy

## Context

Many legacy calls to `json_encode()` and `json_decode()` omit `JSON_THROW_ON_ERROR`, such as the request decoding in `AbstractController` and `AcknowledgementController`.

Without the flag, `json_encode()` returns `false` and `json_decode()` returns `null` on an error. `null` is also the result of decoding the valid JSON `null`, and a `false` written to a file or a column becomes an empty string, so malformed data travels silently.

Two alternatives were weighed:

- **Test the result with `json_last_error()`.** It lost: the check is easy to forget, takes several lines, and must run before any other JSON call.
- **A shared JSON helper that throws.** It lost: the native flag does the same in one argument, with no class to maintain.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call**, combined with the other flags it needs, so that an error raises a `\JsonException`.

**Catch the `\JsonException` only where the code can do something with it**, such as rejecting malformed input with a client error; otherwise let it propagate.

## Consequences

- Malformed JSON surfaces where it is read or written, not later as an unexplained `null` or empty string.
- Existing calls without the flag stay until their line is touched.
- The rule follows [ADR-0065](0065-apip-pass-json-throw-on-error-to-every-json-call.md).
- The rule is enforced in review.

## When to Amend

- A tool of the project starts to add or require the flag, and the review check becomes redundant.
