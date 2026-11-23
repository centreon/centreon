# ADR-0019: Group classes per aggregate only in the Domain model and ApiPlatform layers

- **Status:** Accepted
- **Date:** 2026-10-28
- **Scope:** APIP

## Context

[ADR-0002](0002-apip-name-domain-folder-model.md) settled the Domain side of per-aggregate grouping: under `Domain/Model/`, each aggregate and each read model gets its own `<Agg>/` subfolder. It says nothing about the infrastructure or application layers.

The `Infrastructure/ApiPlatform/` layer grew the same pressure: `Resource/` and `State/` held the resources, Input/Output DTOs, providers, processors and transformers of every aggregate side by side, and a class's aggregate was no longer obvious from its folder. On the contact group migration ([#11627](https://github.com/centreon/centreon/pull/11627)), a reviewer asked to group other layers too, such as `Infrastructure/Dbal`, `Domain/Repository` and `Application/Query`. In those layers, the class name already names its aggregate (`DbalContactGroupRepository`, `ContactGroupRepository`), so a subfolder repeats it.

Two alternatives were weighed:

- **Group every layer per aggregate.** It lost: it adds a folder level everywhere, while the class name already carries the aggregate in the other layers.
- **Keep every layer flat.** It lost for ApiPlatform: one resource brings several Input/Output DTOs, a provider, processors and transformers whose names do not all carry the aggregate.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

Group classes per aggregate in exactly **two** layers:

- the Domain model, under `Domain/Model/<Agg>/` for aggregate- or read-model-owned classes; shared value objects and enums stay at the `Domain/Model/` root, and contexts not yet migrated keep `Domain/Aggregate/`, as set by [ADR-0002](0002-apip-name-domain-folder-model.md);
- the ApiPlatform layer, under `Infrastructure/ApiPlatform/Resource/<Agg>/` (the resources and their `*Input` / `*Output` DTOs) and `Infrastructure/ApiPlatform/State/<Agg>/` (their providers, processors, transformers, and the builders, factories or helpers only that aggregate's resources use). `<Agg>` is the aggregate the resource's path belongs to, even when the resource exposes a read model: `HostAddressResolutionResource`, on `/configuration/hosts/_resolve`, goes in `Host/`. A resource whose path belongs to no aggregate uses the name of the domain read model it exposes. Helpers shared across resources stay at the `State/` root, and other `ApiPlatform/` folders stay flat.

**Every other layer stays flat.** `Domain/Repository`, `Domain/Security`, `Application/Command`, `Application/Query`, `Infrastructure/Dbal`, `Infrastructure/Security`, … hold their classes directly. Do not add an aggregate subfolder there, and do not ask for one in review.

## Consequences

- A new aggregate-owned file directly in `ApiPlatform/Resource/` or `ApiPlatform/State/` is a violation.
- This refines the paths of [ADR-0003](0003-apip-name-transformers-after-their-output.md) and [ADR-0008](0008-apip-one-resource-per-path.md): their `State/` transformers and `Resource/<Context>/` DTOs go in the `<Agg>/` subfolder.
- The rule applies to new files. ApiPlatform classes placed flat before this decision move in a dedicated refactor, one bounded context at a time, never inside a feature change. Until then, unlike [ADR-0002](0002-apip-name-domain-folder-model.md) for the Domain, a new file follows the rule even if its neighbours are flat.
- The rule is enforced in review.

## When to Amend

- A flat layer grows enough that finding a class in it costs more than the extra folders would.
- The ApiPlatform `Resource` / `State` split itself changes.
