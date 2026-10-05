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

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Rejects contacts the viewer cannot reach as if they did not exist, so a restricted user cannot
 * tell an inaccessible contact apart from a missing one — and cannot link a contact absent from
 * the very selector the UI offers them (ListNotificationContactsProvider scopes it identically).
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially on the property
 * (see CreateHostNotificationsInput): NotificationContactId asserts a strictly positive int per
 * element and would throw instead of producing a clean violation.
 */
final class AccessibleContactsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationContactRepository $contactRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleContacts) {
            throw new UnexpectedTypeException($constraint, AccessibleContacts::class);
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
        $contactIds = new Collection(
            array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $requestedIds),
            NotificationContactId::class,
        );

        $foundIds = array_keys($this->contactRepository->findNamesByIds($contactIds)->toArray());
        $missingIds = array_diff($requestedIds, $foundIds);

        if (! $credentialUser->credential->hasUnrestrictedResourceAccess()) {
            $accessibleIds = array_map(
                static fn (NotificationContactId $id): int => $id->value,
                $this->resourceAccessRepository->findAccessibleContactIds($credentialUser->credential->userId)->toArray(),
            );
            $missingIds = array_unique(array_merge($missingIds, array_diff($requestedIds, $accessibleIds)));
        }

        if ($missingIds !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
