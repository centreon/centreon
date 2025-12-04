<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\Security\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Security\GlobalMacroPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\ServiceCategoryPermissionEnum;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Webmozart\Assert\Assert;

/**
 * @implements UserProviderInterface<CredentialUser>
 */
final readonly class LegacyCredentialUserProvider implements UserProviderInterface
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_PERMISSION_MAP = [
        Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ => ServiceCategoryPermissionEnum::CanRead->value,
        Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE => ServiceCategoryPermissionEnum::CanWrite->value,
        Contact::ROLE_CONFIGURATION_POLLERS_GLOBAL_MACRO_RW => GlobalMacroPermissionEnum::CanRead->value,
    ];

    private ContactRepositoryInterface $legacyContactRepository;

    public function __construct(LegacyContainer $legacyContainer)
    {
        $legacyAuthenticator = $legacyContainer->get(ContactRepositoryInterface::class);
        Assert::isInstanceOf($legacyAuthenticator, ContactRepositoryInterface::class);

        $this->legacyContactRepository = $legacyAuthenticator;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === CredentialUser::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        Assert::notEmpty($identifier);

        $contact = $this->legacyContactRepository->findByName($identifier);

        if (! $contact instanceof Contact) {
            throw new UserNotFoundException();
        }

        $credential = new Credential(
            identifier: new CredentialIdentifier($identifier),
            userId: new UserId($contact->getId()),
            active: $contact->isActive(),
        );

        foreach ($contact->getRoles() as $contactRole) {
            $credential->assignRole(new Role($contactRole));
        }

        foreach ($contact->getTopologyRules() as $contactTopologyRole) {
            if (! $permissionString = (self::LEGACY_PERMISSION_MAP[$contactTopologyRole] ?? null)) {
                @trigger_error(\sprintf('"%s" topology role is not mapped to any "%s", add it to "%s::LEGACY_PERMISSION_MAP".', $contactTopologyRole, Permission::class, self::class), \E_USER_DEPRECATED);
                $permissionString = \sprintf('LEGACY_%s', $contactTopologyRole);
            }

            $credential->grantPermission(new Permission($permissionString));
        }

        return new CredentialUser($credential);
    }
}
