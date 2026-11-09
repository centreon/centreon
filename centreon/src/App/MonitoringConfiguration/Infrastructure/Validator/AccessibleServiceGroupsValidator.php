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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early duplicate of the create handler's own service group check, which stays the authority. An unknown id and a
 * hidden one get the same answer.
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially: ServiceGroupId asserts a positive
 * int.
 */
final class AccessibleServiceGroupsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly ServiceGroupRepository $repository,
        private readonly AccessScopeResolver $accessScopes,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleServiceGroups) {
            throw new UnexpectedTypeException($constraint, AccessibleServiceGroups::class);
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
        $ids = new Collection(
            array_map(static fn (int $id): ServiceGroupId => new ServiceGroupId($id), $requestedIds),
            ServiceGroupId::class,
        );

        $missingIds = $this->accessScopes->serviceGroups($credentialUser->credential->actingUser())->missing(
            $ids,
            array_keys($this->repository->findNamesByIds($ids)->toArray()),
        );

        if ($missingIds !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
