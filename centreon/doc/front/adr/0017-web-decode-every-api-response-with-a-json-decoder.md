# ADR-0017: Decode every API response of `www/front_src` with a `JsonDecoder`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A response typed with a TypeScript `as` cast is checked by nobody at runtime: a field the API renamed, a `null` it started to send, surface as `undefined` deep in a component, far from the call. `www/front_src` uses `JsonDecoder` from `ts.data.json` to validate the shape of a response and type it from the validation: 55 files at the time of writing, with the [decoders](../glossary.md#decoder) of a feature in its `api/decoders.ts` and the listing decoders built with `buildListingDecoder` from `@centreon/ui`. The data hooks take the decoder with the endpoint.

Two alternatives were weighed:

- **Trust the API and cast.** It lost: the type is a promise, not a check, and a backend change is found by a user.
- **Another validation library (Zod, Yup for responses).** It lost: `ts.data.json` is already used throughout, and Yup stays the form validation library; two libraries for one job.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**Decode every API response with a `JsonDecoder`** from the feature's **`api/decoders.ts`**, passed to `useFetchQuery` or `useMutationQuery`. **Never cast a response.** The TypeScript type of the data is derived from the decoder, or the decoder is typed with the model of `models.ts`.

## Consequences

- A response that does not match the decoder is an error the hook reports, not a value the component renders.
- A decoder is written once per response shape and reused by every call that returns it.
- A test of a component mocks a response that the decoder accepts; a mock the decoder rejects is a failing test, which is the point.
- The rule is enforced in review.

## When to Amend

- The API ships a typed client generated from its OpenAPI documentation; decoders may then be generated rather than written.
