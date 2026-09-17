# Permission handling — from the Domain down to the Infrastructure

This document describes how to implement and check permissions in Centreon's DDD/CQRS architecture, following the patterns already in place under `src/App/`.

---

## Table of contents

1. [Overview](#1-overview)
2. [Step 1 — Define the Domain permission enum](#2-step-1--define-the-domain-permission-enum)
3. [Step 2 — Map legacy permissions in the Transformer](#3-step-2--map-legacy-permissions-in-the-transformer)
4. [Step 3 — Create the Infrastructure Voter](#4-step-3--create-the-infrastructure-voter)
5. [Step 4 — Declare the security on the API Platform Resource](#5-step-4--declare-the-security-on-the-api-platform-resource)
6. [End-to-end flow](#6-end-to-end-flow)
7. [Advanced patterns](#7-advanced-patterns)
8. [File reference](#8-file-reference)

---

## 1. Overview

The permission stack is a four-link chain:

```
┌─────────────────────────────────────────────────────────────────────┐
│  Domain                                                             │
│  PermissionEnum (business-facing values)                            │
│         ↓                                                           │
│  Credential::isPermissionGranted(Permission)                        │
├─────────────────────────────────────────────────────────────────────┤
│  Infrastructure / Dbal                                              │
│  DbalCredentialTransformer                                          │
│  (legacy ACL → Domain Permission mapping)                           │
├─────────────────────────────────────────────────────────────────────┤
│  Infrastructure / Security                                          │
│  Symfony Voter                                                      │
│  (calls Credential::isPermissionGranted)                            │
├─────────────────────────────────────────────────────────────────────┤
│  Infrastructure / ApiPlatform                                       │
│  Resource (security / securityPostValidation)                       │
│  (declares which permission each operation requires)                │
└─────────────────────────────────────────────────────────────────────┘
```

### `Credential` aggregate — runtime source of truth

The `Credential` aggregate (`src/App/Security/Domain/Aggregate/Credential.php`) carries two collections:

- **`roles`** — `Collection<Role>`: Symfony roles (e.g. `ROLE_HOST_CHECK`).
- **`permissions`** — `Collection<Permission>`: business-facing permissions (e.g. `can_read_command_checks`).

Permissions are populated at authentication time by `DbalCredentialTransformer`, then evaluated by the Voters.

```php
// Domain-side check
$credential->isPermissionGranted(new Permission('can_read_command_checks')); // true|false
```

### Admin vs non-admin

There is **no** `isAdmin()` method on the aggregate. The admin flag (`contact.contact_admin = '1'`) is resolved at load time:

- **Admin** — receives **every** permission listed in `LEGACY_ROLE_MAP` plus read-write access on every topology.
- **Non-admin** — receives only the permissions that match the ACLs attached to their groups.

---

## 2. Step 1 — Define the Domain permission enum

Create a backed-string `enum` under the `Domain/Security` namespace of your bounded context.

**Naming convention** — `<BoundedContext>/Domain/Security/<Feature>PermissionEnum.php`.

### Example

```php
// src/App/MonitoringConfiguration/Domain/Security/ServiceCategoryPermissionEnum.php

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Security;

enum ServiceCategoryPermissionEnum: string
{
    case CanRead = 'can_read_service_category';
    case CanWrite = 'can_write_service_category';
}
```

### Rules

| Rule | Detail |
|------|--------|
| Value naming | `can_read_<feature>`, `can_write_<feature>`, `can_read_and_write_<feature>` |
| Case naming | `CanRead`, `CanWrite`, `CanReadAndWrite` (optionally suffixed by a qualifier) |
| Location | Always under `<BoundedContext>/Domain/Security/` |
| Backed type | Always `string` |

### When CRUD actions need their own enum

When the permission depends on the **subject type** (e.g. Check vs Notification commands), also expose an `ActionEnum`:

```php
// src/App/MonitoringConfiguration/Domain/Security/CommandActionEnum.php

enum CommandActionEnum: string
{
    case Create = 'command_create';
    case Update = 'command_update';
    case Delete = 'command_delete';
    case Read   = 'command_read';
}
```

---

## 3. Step 2 — Map legacy permissions in the Transformer

`src/App/Security/Infrastructure/Dbal/DbalCredentialTransformer.php` bridges the legacy ACL tables and the Domain permissions.

### Two maps to feed

#### `LEGACY_PERMISSION_MAP` — topologies → permissions

Maps the topology roles (built from the `acl_topology` pages) to Domain permissions:

```php
private const LEGACY_PERMISSION_MAP = [
    // Key   = generated topology role (ROLE_<TOPOLOGY_PATH>_R or _RW)
    // Value = enum permission value
    'ROLE_CONFIGURATION_SERVICES_CATEGORIES_R'  => ServiceCategoryPermissionEnum::CanRead->value,
    'ROLE_CONFIGURATION_SERVICES_CATEGORIES_RW' => ServiceCategoryPermissionEnum::CanWrite->value,

    // Add your own topology permissions here
    'ROLE_CONFIGURATION_MY_FEATURE_R'  => MyFeaturePermissionEnum::CanRead->value,
    'ROLE_CONFIGURATION_MY_FEATURE_RW' => MyFeaturePermissionEnum::CanWrite->value,
];
```

> **How to figure out the topology key?**
> The key is computed by `DbalCredentialRepository::buildTopologyPermissions()` from the topology pages hierarchy. The shape is `ROLE_<LEVEL1>_<LEVEL2>_..._R` (or `_RW`).
> When in doubt, drop a temporary `dump()` inside `mapTopologyToPermission()` to inspect the generated keys.

#### `LEGACY_ROLE_MAP` — action rules → permissions

Maps the ACL action rules (`acl_actions_rules.acl_action_name`) to permissions:

```php
private const LEGACY_ROLE_MAP = [
    // Key   = action rule name in the database
    // Value = enum permission value
    'see_check_commands'    => CommandPermissionEnum::CanReadChecks->value,
    'manage_check_commands' => CommandPermissionEnum::CanReadAndWriteChecks->value,
    // ...
];
```

> **Important** — admin users automatically receive **every** permission listed in `LEGACY_ROLE_MAP` (cf. `transform()` lines 92-96). You do not need to special-case admins inside Voters.

---

## 4. Step 3 — Create the Infrastructure Voter

The Voter is the glue between Symfony Security and the `Credential` aggregate.

**Naming convention** — `<BoundedContext>/Infrastructure/Security/<Feature>PermissionVoter.php`.

### Simple pattern — direct permission

Use this shape whenever it is enough to check the permission, without any extra logic:

```php
// src/App/MonitoringConfiguration/Infrastructure/Security/ServiceCategoryPermissionVoter.php

declare(strict_types=1);

namespace App\MonitoringConfiguration\Infrastructure\Security;

use App\MonitoringConfiguration\Domain\Security\ServiceCategoryPermissionEnum;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<value-of<ServiceCategoryPermissionEnum>, mixed>
 */
final class ServiceCategoryPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return ServiceCategoryPermissionEnum::tryFrom($attribute) !== null;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (! $user instanceof CredentialUser) {
            $vote?->addReason('The user is not logged in.');
            return false;
        }

        if (! $user->credential->isPermissionGranted(new Permission($attribute))) {
            $vote?->addReason('The user does not have the required permission.');
            return false;
        }

        return true;
    }
}
```

### Voter checklist

| Item | Detail |
|------|--------|
| `supports()` | Use `::tryFrom($attribute) !== null` so the voter only handles its own enum |
| `$token->getUser()` | Always assert it is a `CredentialUser` |
| `isPermissionGranted()` | Build a fresh `new Permission($attribute)` and ask the `credential` aggregate |
| `?Vote $vote` | Optional — pushing reasons is useful for debugging vote outcomes |

### Advanced pattern — Voter with business logic (action + subject)

When the permission depends on the **subject type** (e.g. `CommandResource` carrying a Check / Notification type), declare one Voter per action:

```php
// ReadCommandVoter — read access depends on the command type

protected function supports(string $attribute, mixed $subject): bool
{
    return CommandActionEnum::tryFrom($attribute) === CommandActionEnum::Read;
}

protected function voteOnAttribute(
    string $attribute,
    mixed $subject,
    TokenInterface $token,
    ?Vote $vote = null,
): bool {
    // Collection read (subject === null): allow if at least one type is readable.
    if ($subject === null) {
        foreach (CommandTypeEnum::cases() as $commandType) {
            $readPermission  = Command::getReadPermissionForType($commandType);
            $writePermission = Command::getWritePermissionForType($commandType);
            if ($this->security->isGranted($readPermission->value)
                || $this->security->isGranted($writePermission->value)) {
                return true;
            }
        }
        return false;
    }

    // Single-resource read: check the exact type.
    if ($subject instanceof CommandResource) {
        $type = CommandTypeEnum::fromName($subject->type);
        return $this->security->isGranted(Command::getReadPermissionForType($type)->value)
            || $this->security->isGranted(Command::getWritePermissionForType($type)->value);
    }

    return false;
}
```

### Advanced pattern — Voter with resource-access check

When a permission also targets a **specific resource** (e.g. a poller):

```php
// InstallationCommandVoter — permission + poller access

protected function voteOnAttribute(
    string $attribute,
    mixed $subject,
    TokenInterface $token,
    ?Vote $vote = null,
): bool {
    $user = $token->getUser();
    if (! $user instanceof CredentialUser) {
        return false;
    }

    // 1. Check the permission.
    if (! $user->credential->isPermissionGranted(new Permission($attribute))) {
        return false;
    }

    // 2. Check the resource (poller) access.
    if (! $this->resourceAccessRepository->hasAccessToAllPollers($user->credential->userId)
        && ! $this->resourceAccessRepository->hasAccessToPoller(
            new PollerId((int) $subject),
            $user->credential->userId,
        )
    ) {
        $vote?->addReason('The user has no access to this monitoring server.');
        return false;
    }

    return true;
}
```

`Credential::userId` is a `UserId` value object (not a raw `int`); pass it straight to the repository methods.

---

## 5. Step 4 — Declare the security on the API Platform Resource

The last step ties the permission to the API operation.

### `security` vs `securityPostValidation`

| Attribute | Evaluated | `$subject` available | When to use |
|-----------|-----------|----------------------|-------------|
| `security` | **Before** the entity is loaded | No (or the raw request body for `POST`) | Permission that does not depend on the subject |
| `securityPostValidation` | **After** the entity is loaded via the Provider | Yes (`object` = the hydrated Resource) | Permission whose outcome depends on the subject state |

### Example with `security` (pre-validation)

Use this form when the permission does not depend on the loaded object:

```php
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/services/categories',
            security: "is_granted('" . ServiceCategoryPermissionEnum::CanRead->value . "')",
            securityMessage: 'You are not allowed to list service categories',
        ),
        new Post(
            uriTemplate: '/configuration/services/categories',
            security: "is_granted('" . ServiceCategoryPermissionEnum::CanWrite->value . "')",
            securityMessage: 'You are not allowed to create service categories',
        ),
    ],
)]
```

### Example with `securityPostValidation` (post-validation)

Use this form when the permission depends on the loaded object (here the command type):

```php
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/configuration/commands/{id}',
            provider: FindCommandProvider::class,
            securityPostValidation: "is_granted('"
                . CommandActionEnum::Read->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to access this command',
        ),
        new Patch(
            uriTemplate: '/configuration/commands/{id}',
            provider: FindCommandProvider::class,
            processor: UpdateCommandProcessor::class,
            securityPostValidation: "is_granted('"
                . CommandActionEnum::Update->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to update this command',
        ),
    ],
)]
```

### `OR` combination of permissions

When access is granted by either of two permissions:

```php
new GetCollection(
    uriTemplate: '/configuration/connectors',
    security: 'is_granted("' . ConnectorPermissionEnum::CanRead->value . '") or '
            . 'is_granted("' . ConnectorPermissionEnum::CanReadAndWrite->value . '")',
    securityMessage: 'You are not allowed to list connectors',
),
```

---

## 6. End-to-end flow

```
HTTP request
    │
    ▼
┌──────────────────────────┐
│ Authenticator             │  TokenAuthenticator / SessionAuthenticator
│ → CredentialRepository    │  Loads from the database (contact_admin, ACLs, topologies)
│ → CredentialTransformer   │  Maps legacy → Domain Permission & Role
│ → CredentialUser          │  Wraps the Credential for Symfony Security
└──────────┬───────────────┘
           │
           ▼
┌──────────────────────────┐
│ API Platform Resource     │  security / securityPostValidation
│ → is_granted('perm')      │  Triggers the Voter system
└──────────┬───────────────┘
           │
           ▼
┌──────────────────────────┐
│ Voter                     │  supports() → filter by enum
│ → CredentialUser          │  voteOnAttribute()
│ → credential              │     → isPermissionGranted(Permission)
│    .isPermissionGranted() │     → (optional) resource-access check
└──────────┬───────────────┘
           │
           ▼
    ┌──────┴──────┐
    │ Granted?    │
    ├─── Yes ────→ Processor / Provider runs
    └─── No  ────→ 403 Forbidden
```

### Decision strategy — `unanimous`

`config/packages/security.yaml` runs the Symfony Security manager in **unanimous** mode:

- **Every** non-abstaining Voter must vote `true`.
- A Voter abstains when `supports()` returns `false`.
- When every Voter abstains, access is denied (`allow_if_all_abstain: false`).

---

## 7. Advanced patterns

### Adding a new bounded context with permissions

Full checklist:

1. **Domain** — create `<BC>/Domain/Security/<Feature>PermissionEnum.php`.
2. **Transformer** — add entries to `LEGACY_PERMISSION_MAP` and / or `LEGACY_ROLE_MAP`.
3. **Voter** — create `<BC>/Infrastructure/Security/<Feature>PermissionVoter.php`.
4. **Resource** — add `security` or `securityPostValidation` on the API Platform operations.

### Read vs ReadAndWrite

A common pattern for read-side permissions:

- `CanRead` — read only.
- `CanReadAndWrite` — read **and** write.

A user holding `CanReadAndWrite` is also allowed to read. In the Voter or the Resource, check both:

```php
// In the API Platform resource
security: 'is_granted("can_read_connector") or is_granted("can_read_and_write_connector")',

// Or inside a dedicated Voter
$this->security->isGranted($readPermission->value)
    || $this->security->isGranted($writePermission->value);
```

### Resource-level access (Pollers)

For permissions that target specific resources, use `ResourceAccessRepository`:

```php
interface ResourceAccessRepository
{
    public function hasAccessToAllPollers(UserId $userId): bool;
    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool;
}
```

A user with no `acl_resources` rows has access to **every** poller (default behaviour).

---

## 8. File reference

### Shared `Security` bounded context

| File | Role |
|------|------|
| `src/App/Security/Domain/Aggregate/Credential.php` | Aggregate carrying roles and permissions |
| `src/App/Security/Domain/Aggregate/Permission.php` | Permission value object |
| `src/App/Security/Domain/Aggregate/Role.php` | Role value object |
| `src/App/Security/Domain/Repository/CredentialRepository.php` | Credential loading interface |
| `src/App/Security/Domain/Repository/ResourceAccessRepository.php` | Resource-access loading interface |
| `src/App/Security/Infrastructure/Dbal/DbalCredentialRepository.php` | SQL loader (topology rules, action rules) |
| `src/App/Security/Infrastructure/Dbal/DbalCredentialTransformer.php` | Legacy → Domain mapping (both maps) |
| `src/App/Security/Infrastructure/Security/CredentialUser.php` | Credential → Symfony `UserInterface` adapter |
| `src/App/Security/Infrastructure/Security/TokenAuthenticator.php` | API token authenticator |
| `src/App/Security/Infrastructure/Security/SessionAuthenticator.php` | Session authenticator |
| `config/packages/security.yaml` | Symfony config (firewalls, `unanimous` strategy) |

### Existing examples (`MonitoringConfiguration`)

| File | Pattern illustrated |
|------|---------------------|
| `Domain/Security/CommandPermissionEnum.php` | Enum with Read / ReadAndWrite variants per type |
| `Domain/Security/ServiceCategoryPermissionEnum.php` | Plain Read / Write enum |
| `Domain/Security/ConnectorPermissionEnum.php` | Read / ReadAndWrite enum |
| `Domain/Security/CommandActionEnum.php` | CRUD action enum |
| `Infrastructure/Security/CommandPermissionVoter.php` | Simple voter — direct permission |
| `Infrastructure/Security/ReadCommandVoter.php` | Compound voter — action + subject type |
| `Infrastructure/Security/CreateCommandVoter.php` | Voter with `Security` service injected |
| `Infrastructure/Security/InstallationCommandVoter.php` | Voter with resource-access check |
| `Infrastructure/Security/ServiceCategoryPermissionVoter.php` | Plain permission voter |
| `Infrastructure/ApiPlatform/Resource/Command/CommandResource.php` | `securityPostValidation` with subject |
| `Infrastructure/ApiPlatform/Resource/ServiceCategoryResource.php` | Plain `security` (pre-validation) |
| `Infrastructure/ApiPlatform/Resource/ConnectorResource.php` | `security` with an `OR` combination |
