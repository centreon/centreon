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

namespace App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Same contract as {@see AccessibleContactsValidator}, for contact groups. A group that exists
 * only in LDAP and was never imported has no id at all, so it is simply not addressable here —
 * unlike the legacy form, which created it on the fly (CentreonContactgroup::insertLdapGroup()).
 */
final class AccessibleContactGroupsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly ContactGroupRepository $contactGroupRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleContactGroups) {
            throw new UnexpectedTypeException($constraint, AccessibleContactGroups::class);
        }

        if (! is_array($value) || $value === []) {
            return;
        }

        $credentialUser = $this->security->getUser();
        if (! $credentialUser instanceof CredentialUser) {
            return;
        }

        /** @var list<int> $requestedIds */
        $requestedIds = $value;
        $contactGroupIds = new Collection(
            array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), $requestedIds),
            ContactGroupId::class,
        );

        $foundIds = array_keys($this->contactGroupRepository->findNamesByIds($contactGroupIds)->toArray());
        $missingIds = array_diff($requestedIds, $foundIds);

        if (! $credentialUser->credential->hasUnrestrictedResourceAccess()) {
            $accessibleIds = array_map(
                static fn (ContactGroupId $id): int => $id->value,
                $this->resourceAccessRepository->findAccessibleContactGroupIds($credentialUser->credential->userId)->toArray(),
            );
            $missingIds = array_unique(array_merge($missingIds, array_diff($requestedIds, $accessibleIds)));
        }

        if ($missingIds !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
