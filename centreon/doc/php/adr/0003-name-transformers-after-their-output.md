# ADR-0003: Name a Transformer after the object it produces

- **Status:** Accepted
- **Date:** 2026-09-03

## Context

Transformer names are inconsistent and partly inverted: `Infrastructure/ApiPlatform/State/ResourceXxxTransformer` and `Infrastructure/Dbal/DbalXxxTransformer`. The `Dbal` prefix repeats what the folder already says, and a name like `ResourceConnectorTransformer`, which produces a `ConnectorResource`, reads backwards.

The input format is given by the folder: a scalar array on the Dbal side, a resource object on the API Platform side. The output is what identifies the class.

One alternative was weighed: rename the Dbal Transformers to `XxxHydrator`, behind a `Hydrator` interface. It lost because a Transformer does more than hydrate, as explained in [ADR-0004](0004-transformers-are-dedicated-classes.md).

## Decision

Name a Transformer **after the object it outputs**, and **omit the technology prefix** when the class already sits in a technology folder:

- `Infrastructure/Dbal/<Context>/XxxTransformer` produces the domain object `Xxx`.
- `Infrastructure/ApiPlatform/State/XxxResourceTransformer` produces the resource `XxxResource`.

Organise `Infrastructure/` **by technology first** (`Dbal/`, `ApiPlatform/`), **then by context**: `src/App/<BC>/Infrastructure/Dbal/<Context>/XxxTransformer.php`.

## Consequences

- Around 11 existing Transformers must be renamed. The renames are done incrementally in a dedicated refactor, never as drive-by changes in a feature change, so that backports stay clean.
- Older code and documents that say `DbalXxxTransformer` refer to the old naming.
- The rule is not enforced by tooling yet. A search for `class Dbal\w*Transformer` under `Dbal/` catches a violation.

## When to Amend

- An ORM replaces the hand-written Dbal hydration, and the Dbal Transformers disappear.
- Transformers start having several outputs per input folder, and the name stops being unambiguous.
