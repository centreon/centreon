# ADR-0008: Keep one API Platform resource as far as possible, and add Input or Output DTOs when formats differ

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

Endpoints on the same resource often take different input formats per verb, and sometimes live on different URLs. The question, raised in a [review comment on PR 10293](https://github.com/centreon/centreon/pull/10293#discussion_r3279788920), is when to create a dedicated resource and when to add Input or Output DTOs.

## Decision

Keep **one resource** for a business object **as far as possible**. Shape the resource so it covers **the majority of the input and output cases**, and use it directly as input and output for them. When an operation's **input or output differs**, give that operation an **Input or Output DTO** on the same resource. **Avoid creating another resource** for it, even when its URL differs.

Put a resource's Input and Output DTOs in the same `Infrastructure/ApiPlatform/Resource/<Context>/` folder as the resource.

## Consequences

- Fewer classes: no DTO for an endpoint whose payload already matches the resource.
- Most operations share one representation, and each DTO marks an exception.
- Another resource is a design choice to justify, not the default answer to a different URL or format.
- A DTO signals a real format difference, which makes it worth reviewing.

## When to Amend

- API Platform changes how resources and DTOs are modelled, for example in a new major version.
- Per-verb output differences become the norm rather than the exception.
