# ADR-0018: Decode every API response of `packages/ui` with a `JsonDecoder`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` defines the data hooks and some components that fetch on their own (connected autocomplete fields, listing filters): 10 files of the package use `JsonDecoder` from `ts.data.json` at the time of writing, and `src/api/buildListingDecoder.ts` builds the decoder of any paginated listing. A response typed with an `as` cast is checked by nobody at runtime, and in a published component the failure shows up in every consumer.

Two alternatives were weighed:

- **Trust the API and cast.** It lost: the type is a promise, not a check.
- **Another validation library.** It lost: `ts.data.json` is already the library of the package and of the applications.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**Decode every API response with a `JsonDecoder`.** A published component that fetches takes its **[decoder](../glossary.md#decoder) from its consumer** when the response shape is the consumer's, and defines it in the package when the shape is the package's own (a listing, a selector). **Never cast a response.**

## Consequences

- A response that does not match the decoder is an error the hook reports, not a value the component renders.
- Exception: a `204 No Content` answer skips the decoder (`customFetch` returns `{ isError: false, message: '' }` before parsing), so it is never reported as a mismatch. A call to an endpoint that may answer 204 handles the empty result explicitly.
- `buildListingDecoder` is the decoder of every listing; a feature decodes only the item.
- The rule is enforced in review.

## When to Amend

- The API ships a typed client generated from its OpenAPI documentation.
