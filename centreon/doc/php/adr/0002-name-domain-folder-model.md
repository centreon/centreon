# ADR-0002: Name the domain folder `Model`, not `Aggregate`

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

`Domain/Aggregate/` holds more than aggregates. In BAM, next to the BA and BA Tree aggregates, it holds the value objects and enums they share, and read-only models such as the Timeline (an ordered collection of status bands, clipped to a time window). The Timeline has no identity, is never written and does not extend `AggregateRoot`, so the folder name no longer describes its contents.

The same split exists in `src/App/`. `MonitoringConfiguration/Domain/` has both an `Aggregate/` folder, for its aggregates and their value objects, and a `Model/` folder, for value objects such as `CentralUrl`, `UrlPath` and `ResolvableAddress`.

The terms, as agreed:

- **Value object:** defined by its values, immutable.
- **Entity:** unique and identifiable.
- **Aggregate:** a set of entities handled as one unit, entered through an identifiable **aggregate root**.
- **Model:** the wider concept that covers value objects, entities, aggregates and read models.

An aggregate is a model; a model is not always an aggregate.

Two alternatives were weighed:

- Keep `Aggregate/` and treat a read model as an aggregate like any other, as the install commands already do. It lost because the name stays misleading and nothing tells a reader which classes are write-side.
- Keep `Aggregate/` for write-side roots, add `Model/` for read models and `ValueObject/` for shared value objects. It lost on the definitions above: a value object is a model, like an aggregate or a read model. A single `Model/` folder holds every kind of domain object without multiplying folders.

## Decision

This applies to every bounded context under `src/App/` and to the BAM module.

Replace `Domain/Aggregate/` with `Domain/Model/`. At the root of `Model/`, put the value objects and enums shared across the domain. Group each aggregate, and each read model, in its own subfolder.

The shared building blocks in `App/Shared/Domain/Aggregate/` follow the same rule and move to `App/Shared/Domain/Model/`: `AggregateRoot`, `AggregateRootId`, `TriStateEnum`, and the `AclScopedInterface`, `PollerScopedInterface` and `VaultScopedInterface` interfaces.

Migrate the existing `Aggregate/` folders, one bounded context per dedicated refactor. A new bounded context uses `Model/` from the start.

In BAM:

```
Domain/Model/
├── BaStatusEnum.php                 # enum shared by the BA and the Timeline
├── CalculationMethod.php            # value object
├── Impact.php                       # value object
├── ImpactValue.php                  # value object
├── KpiTypeEnum.php                  # enum
├── BusinessActivity/
│   ├── BusinessActivity.php         # aggregate root
│   └── Icon.php                     # value object, used by this aggregate only
├── BusinessActivityTree/
│   ├── BusinessActivityTree.php     # aggregate root
│   ├── BusinessActivityNode.php     # entity
│   └── Kpi.php                      # entity
└── Timeline/
    ├── Timeline.php                 # read model, ordered collection of status bands
    ├── StatusBand.php               # read model, one contiguous status segment
    └── TimelinePeriod.php           # value object, the requested time window
```

In `App/MonitoringConfiguration` (excerpt):

```
Domain/Model/
├── CentralUrl.php                   # value object
├── ResolvableAddress.php            # value object
├── UrlPath.php                      # value object, shared by Poller and the infrastructure
├── Host/
│   ├── Host.php                     # aggregate root
│   ├── HostId.php                   # value object, identifies the root
│   ├── HostMacro.php                # entity, identified by HostMacroId
│   └── SnmpVersionEnum.php          # enum
└── Poller/
    ├── Poller.php                   # aggregate root
    └── PollerName.php               # value object
```

## Consequences

- The folder name matches its contents, whatever the class is: aggregate, entity, read model or value object.
- Write and read are not physically separated. Whether a class is an aggregate shows in the code (it extends `AggregateRoot`), not in its path.
- Moving existing classes changes their namespace and the imports that use them, and the deptrac layer rules must follow. The move is done in a dedicated refactor, never inside a feature change.
- Until its refactor, a bounded context keeps its `Aggregate/` folder, and new classes go there. Both conventions coexist across contexts for a while, never inside one context.
- A value object used by a single aggregate stays in that aggregate's subfolder. It moves to the root of `Model/` once a second subfolder needs it.

## When to Amend

- Read models need behaviour or dependencies the domain should not carry. They then move to dedicated read models in the infrastructure layer, an alternative raised but not explored.
- The mix of read and write classes in `Model/` causes a write-side rule to be applied to a read model, or the reverse.
