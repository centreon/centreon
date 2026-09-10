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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early, best-effort duplicate of CreateHostCommandHandler's own host-group-scoping check (see
 * AccessiblePollerValidator for why the handler's own check stays authoritative and is not
 * removed).
 */
final class AccessibleHostGroupsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly HostGroupRepository $hostGroupRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleHostGroups) {
            throw new UnexpectedTypeException($constraint, AccessibleHostGroups::class);
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
        $hostGroupIds = new Collection(
            array_map(static fn (int $id): HostGroupId => new HostGroupId($id), $requestedIds),
            HostGroupId::class,
        );

        $foundIds = array_keys($this->hostGroupRepository->findNamesByIds($hostGroupIds)->toArray());
        $missingIds = array_diff($requestedIds, $foundIds);

        if (! $credentialUser->credential->hasUnrestrictedResourceAccess()) {
            $accessibleIds = $this->resourceAccessRepository->findAccessibleHostGroupIds($credentialUser->credential->userId);
            if ($accessibleIds instanceof Collection) {
                $accessibleIdValues = array_map(static fn (HostGroupId $id): int => $id->value, $accessibleIds->toArray());
                $missingIds = array_unique(array_merge($missingIds, array_diff($requestedIds, $accessibleIdValues)));
            }
        }

        if ($missingIds !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
