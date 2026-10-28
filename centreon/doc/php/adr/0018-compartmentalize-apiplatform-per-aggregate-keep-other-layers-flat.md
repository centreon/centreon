# ADR-0018: Compartmentalize the ApiPlatform layer per aggregate, and keep the other layers flat

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

[ADR-0002](0002-name-domain-folder-model.md) settled the Domain side of per-aggregate grouping: under `Domain/Model/`, each aggregate and each read model gets its own `<Agg>/` subfolder. It says nothing about the infrastructure or application layers.

The `Infrastructure/ApiPlatform/` layer grew the same pressure: `Resource/` and `State/` held the resources, Input/Output sub-objects, providers and processors of every aggregate side by side in one flat folder, and the aggregate a class belonged to was no longer obvious. On the `contact-group` migration (PR 11627) a reviewer started asking to compartmentalize other layers too — `Infrastructure/Dbal`, `Application/Command` — which multiplies thin, often single-file folders and buys little. The team drew a line in meeting review rather than compartmentalize everything or nothing.

## Decision

Compartmentalize per aggregate in exactly **two** layers:

- the Domain model, under `Domain/Model/<Agg>/`, as set by [ADR-0002](0002-name-domain-folder-model.md);
- the ApiPlatform layer: group each aggregate's classes under `Infrastructure/ApiPlatform/Resource/<Agg>/` (the `<Agg>Resource` and the request `*Input` / response `*Output` sub-objects it exposes) and `Infrastructure/ApiPlatform/State/<Agg>/` (its providers, processors and resource transformers).

**Every other layer stays flat.** `Domain/Repository`, `Domain/Security`, `Application/Command`, `Application/Query`, `Infrastructure/Dbal`, `Infrastructure/Security`, … hold their classes directly, with no `<Agg>/` segment. Do not add an aggregate subfolder there, and do not ask for one in review.

## Consequences

- The two layers that carry the most per-aggregate classes are navigable by aggregate; the layers that hold one or two classes per aggregate are not fragmented into thin folders.
- A new file landing directly in `ApiPlatform/Resource/` or `ApiPlatform/State/` with no `<Agg>/` segment is the violation; a new file directly in `Application/Command/` or `Infrastructure/Dbal/` is correct.
- A reviewer asking to compartmentalize any layer other than the Domain model and ApiPlatform is now out of scope — the flat placement is the decision, not an oversight.
- The rule applies to new files. ApiPlatform classes placed flat before this decision migrate opportunistically, context by context, as [ADR-0002](0002-name-domain-folder-model.md) does for the Domain.

## When to Amend

- A flat non-model layer grows enough that finding a class in it costs more than the extra folders would.
- The ApiPlatform `Resource` / `State` split itself changes.
