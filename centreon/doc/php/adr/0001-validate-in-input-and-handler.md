# ADR-0001: validate-in-input-and-handler

## Context

A write endpoint in `src/App` validates the same request in two places. The constraints on the Input DTO run first, before the processor and outside the command transaction. The checks in the command handler run inside the transaction, against the state the write is about to change.

Practice has not been uniform. Some endpoints check referenced resources and uniqueness in both layers, others only in the Input. The existing validators describe themselves as an "early, best-effort duplicate" of a handler check that "stays authoritative", while the exception listener states that a reference check "can live only in the handler". No written rule settles it, so the question comes back in review on each new write endpoint.

One alternative was weighed: validate in the Input only. It lost because the Input runs before and outside the transaction. It cannot catch what changes between validation and execution (a concurrent write, a stale access grant, a resource deleted in between), and it protects nothing for a caller that does not go through the Input.

## Decision

Validate every write request in both layers. Put in the Input DTO every rule that can be checked from the payload and the authenticated viewer: shape, bounds, and the existence and accessibility of referenced resources. Re-check in the command handler every rule the write depends on, as the authority. Duplicate a rule rather than move it.

## Consequences

- The Input validator is early and best effort: it returns a field-level violation (HTTP 422) and saves a round trip. The handler check is authoritative: what changed since validation surfaces there as a domain exception, mapped to an HTTP status by the existing exception mapping. The handler contains no HTTP vocabulary.
- Both layers give the same answer for the same case: the same status and, when the payload is at fault, the same field path. A missing resource and an inaccessible one are never told apart, so a restricted viewer cannot probe for what they cannot see.
- A custom validator that builds a domain value object is declared after the shape constraints, inside `Assert\Sequentially`. It must never run on a value the shape constraints would reject, since the value object would throw instead of producing a violation.
- In the handler, references are authorized before any check that could reveal another resource (a uniqueness check, for example). Every validation runs before the first irreversible external write, such as a write to the vault.
- Every duplicated rule has a validator test and a handler test.
- The cost is that a rule exists twice. Changing it means changing both copies and keeping their messages aligned.
- Exception: a rule that needs the identity or the stored state of the targeted resource cannot be an Input constraint, because the Input does not know them. It lives in the handler only.

## When to Revise

- Another entry point (a CLI command, an asynchronous message, another service) dispatches commands, so the Input is no longer the front door.
- A validator and its handler check give different answers in production.
