# ADR-0070: Authorize every `src/App` write operation through a voter declared on the operation

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

Every `src/App` write operation already declares its authorization on the ApiPlatform operation: `security: "is_granted(...)"`, or `securityPostValidation:` when the decision needs the deserialized object, with a permission enum value (`CommandActionEnum::Update`, `HostPermissionEnum::CanReadAndWrite`) that a voter of `Infrastructure/Security/` decides (`CommandPermissionVoter`). Handlers check no permission. No record says so, and an operation that declares nothing is open to every authenticated user.

Two alternatives were weighed:

- **Check the permission in the handler.** It lost: the handler would depend on the security layer, and a forgotten check would still leave the operation open.
- **Check it in the provider or the processor.** It lost: the check is invisible on the resource, where a reviewer reads the operation.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Every write operation** (`Post`, `Put`, `Patch`, `Delete`) **declares its authorization on the operation**, with `security:` or, when the decision needs the deserialized object, `securityPostValidation:`, calling `is_granted()` with a permission enum value.

**A voter of the context's `Infrastructure/Security/` decides it.** A handler checks no permission. A provider or a processor that must decide per item, such as `ListCommandsProvider`, asks the same voter through `Security::isGranted()`.

Which resources a user may see or change is the access scope of [ADR-0015](0015-apip-scope-resource-access-with-acting-user-and-access-scope.md), not a permission.

## Consequences

- The authorization of an operation is read on the resource.
- A read operation declares one too, unless it is open to every authenticated user, which its pull request states. `PluginResource`, `ListPluginResource` and `StandardMacroResource` declare none today.
- The rule is enforced in review.

## When to Amend

- Authorization moves out of ApiPlatform, such as to a messenger middleware for commands dispatched outside HTTP.
