# ADR-0011: Keep selector endpoints out of the published API documentation

- **Status:** Accepted
- **Date:** 2026-09-17

## Context

Forms need endpoints that list related objects for their dropdowns, such as the time periods selectable in the host creation form. These endpoints exist only for the web UI. Their shape and security rules are still evolving, with a proof of concept under way on MON-208574. Documenting them publicly would commit the team to a contract it may need to change, and would widen the documented attack surface.

A selector endpoint is an endpoint consumed by the frontend to list items for a select input, an autocomplete or a similar picker. It returns only what the picker needs, typically `id` and `name`, supports the picker's criteria (pagination, search by name, exclusion of disabled items), and is not meant for API consumers.

## Decision

**Do not publish selector endpoints in the OpenAPI documentation.** Exclude the operation from the generated documentation, for example with `openapi: false` on the API Platform operation. Attach a **non-engagement notice**: these endpoints carry no stability guarantee.

## Consequences

- The team keeps the freedom to change selector endpoints without a public BC break.
- A change limited to selector operations excluded from OpenAPI does not require a regenerated `centreon-api.yaml`. Checks that require it for every `ApiResource` change need an exemption.
- Leaving an endpoint undocumented does not secure it. A selector endpoint still needs a security gate and resource ACL filtering.

## When to Amend

- Selector endpoints become part of a supported public API, for example for integrators or the MCP server.
- The final selector design (security gate, per-context or shared endpoints) is settled and stable.
