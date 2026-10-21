# ADR-0008: Use one API Platform resource per path, and add Input or Output DTOs only when they differ

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

Endpoints on the same resource often take different input formats per verb, and sometimes live on different URLs. The question, raised on MON-198543, is when to create a dedicated resource and when to add Input or Output DTOs.

## Decision

Keep **one resource per path** by default. For REST consistency, every verb on that path returns the **same output**: the resource itself. When the **URL differs**, extract a **dedicated resource** for that endpoint. Create an **Input or Output DTO only when needed**, when its format really differs from the reference resource. Otherwise, use the resource itself as input and output.

## Consequences

- Fewer classes: no DTO for an endpoint whose payload already matches the resource.
- Clients get one consistent representation per path.
- A DTO signals a real format difference, which makes it worth reviewing.

## When to Amend

- API Platform changes how resources and DTOs are modelled, for example in a new major version.
- Per-verb output differences become the norm rather than the exception.
