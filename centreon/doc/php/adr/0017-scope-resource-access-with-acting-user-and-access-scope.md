# ADR-0017: Scope resource access with an acting user and an access scope

- **Status:** Accepted
- **Date:** 2026-10-22

## Context

Most resources in `src/App` are restricted by ACL: a user without unrestricted resource access only sees and references what their Access Groups grant. Every read and every write must apply that rule.

Write commands carried both an actor (`int $creatorId`, `$updatedBy`...) and a `?UserId $viewerId`, where `null` meant "admin". That convention spread to the repositories, the listing criteria and every `ResourceAccessRepository` caller, each re-checking for admins with its own ternary. Its failure mode is the wrong one: a forgotten or lost viewer id reads as "admin", so the resource is not scoped and data leaks. The same `null` also stood for "no restriction" in the ACL lookups, with a different meaning.

Alternatives weighed:

- Pass the `Credential` aggregate across bounded contexts. It lost: other contexts would then depend on roles and permissions they don't use, and on an aggregate that Security must stay free to change.
- Let each handler load the credential from a `UserId` in the command. It lost: it adds a lookup per write that the authenticator already did, and it makes every handler depend on Security's repository.
- Keep the nullable viewer and require a check at each call site. It lost: the rule still lives in every caller, and a missing check still fails open.

## Decision

Represent the user performing an action as an **`ActingUser`** value object: their `UserId` and whether they have unrestricted resource access. It is built by `Credential::actingUser()` and is the only part of the credential that reaches the application and domain layers of other bounded contexts (commands, handlers, repositories). Their infrastructure, such as voters and API Platform providers, may still read the authenticated `Credential`.

Write commands carry it as **`$actor`**, built from the authenticated credential. Handlers don't load anything to know who acts.

Represent what a user may access as an **`AccessScope`**: unrestricted, or restricted to some ids. Ask it with `allows()`, `filter()` and `missing()` instead of comparing to `null`. `ResourceAccessRepository::find*AccessScope(UserId)` returns one. **`AccessScopeResolver`** is the only place where a user with unrestricted access gets an unrestricted scope. A host scope looked up among some hosts only is partial and refuses to list what it allows.

Make listing criteria **fail closed by construction**: an actor-scoped criteria is built with `forActor(ActingUser)` or, for a listing that must see every resource whoever asks, the explicit `unscoped()`. It has no other constructor.

## Consequences

- No `null` means "admin" anymore. A forgotten scoping is a missing argument or a private constructor call, which PHPStan and PHP reject, not a data leak.
- The "admin sees everything" rule lives in one place, `AccessScopeResolver`, instead of in every caller.
- Repositories receive a scope or a criteria and don't decide who is an admin.
- The logs of these commands carry their `actor` (`user_id`, `unrestricted_resource_access`) instead of a field per action (`creator_id`, `updated_by`...).
- A listing that legacy never scoped must say so with `unscoped()`, which makes it visible in review.
- `ActingUser` is a snapshot taken at authentication time. A change to a user's ACL during a request is not seen until the next request.
- Exception: `ResourceAccessRepository::hasAccessToPoller()` and `hasAccessToAllPollers()` keep a `UserId` for `InstallationCommandVoter`, which doesn't let users with unrestricted access through. Changing it would change behaviour, so it is left to its own change.
- Only the host write commands follow this rule yet. The poller, command and service category commands, and `DuplicateHostServicesCommand`, still carry a per-action actor id (`$creatorId`, `$updatedBy`, `$deletedBy`, `$duplicatedBy`). They move to `$actor` when they are next changed.
- MonitoringConfiguration and Security still depend on each other through `ResourceAccessRepository`.

## When to Amend

- An anti-corruption layer removes the cycle between MonitoringConfiguration and Security, and moves `AccessScope` or `ActingUser` with it.
- Access must be re-evaluated within a request (long-running or asynchronous commands), so a snapshot taken at authentication time is no longer enough.
- A resource needs a scope that is not a set of ids, such as a rule evaluated in SQL only.
