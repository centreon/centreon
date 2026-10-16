# ADR-0001: Validate write requests in both input and handler

- **Status:** Accepted
- **Date:** 2026-10-09

## Context

A write endpoint in `src/App` validates the same request in two places. The constraints on the Input DTO run first, before the processor and outside the command transaction. The checks in the command handler run inside the transaction, against the state the write is about to change.

Practice has not been uniform. Some endpoints check referenced resources and uniqueness in both layers, others only in the Input. The existing validators describe themselves as an "early, best-effort duplicate" of a handler check that "stays authoritative", while the exception listener states that a reference check "can live only in the handler". No written rule settles it, so the question comes back in review on each new write endpoint.

One alternative was weighed: validate in the Input only. It lost because the Input runs before and outside the transaction. It cannot catch what changes between validation and execution (a concurrent write, a stale access grant, a resource deleted in between), and it protects nothing for a caller that does not go through the Input.

## Decision

Validate every write request in both layers. Put in the Input DTO every rule that can be checked from the payload and the authenticated viewer: shape, bounds, and the existence and accessibility of referenced resources. Re-check in the command handler or the domain layer every rule the write depends on, as the authority. Duplicate a rule rather than move it.

## Consequences

- The Input validator is early and best effort: it returns a field-level violation (HTTP 422) and saves a round trip. The handler check is authoritative: what changed since validation surfaces there as a domain exception, mapped to an HTTP status by the existing exception mapping. The handler contains no HTTP vocabulary.
- What the Input adds depends on the rule. For shape and bounds, it prevents a value object from throwing an unmapped exception (HTTP 500). For references and uniqueness, the handler already reports a field-level violation, so the Input only fails earlier, before the transaction opens, and points to the exact field.
- Both layers enforce the same rule, but they do not report it the same way. The Input is soft validation: a violated constraint always leads to HTTP 422 with the field path. The handler is hard validation: a violated rule raises a domain exception mapped by the existing exception mapping, for example 404 for a missing resource or 409 for a conflict, or 500 if the exception is not mapped. Within each layer, a missing resource and an inaccessible one produce the same outcome, so a restricted viewer cannot probe for what they cannot see.
- In the handler, references are authorized before any check that could reveal another resource (a uniqueness check, for example). Every validation runs before the first irreversible external write, such as a write to the vault.
- Every duplicated rule has a validator test and a handler test.
- The cost is that a rule exists twice. Changing it means changing both copies and keeping their messages aligned.
- Exception: a rule that needs the identity or the stored state of the targeted resource cannot be an Input constraint, because the Input does not know them. It lives in the handler only.

## When to Amend

- Another entry point (a CLI command, an asynchronous message, another service) dispatches commands. The handler checks keep protecting it. This ADR is then amended to say which early validation that entry point gets, the equivalent of the Input.
- A validator and its handler check give different answers in production.
