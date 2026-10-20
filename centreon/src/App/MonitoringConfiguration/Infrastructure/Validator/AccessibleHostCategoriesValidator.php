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

use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Rejects categories the viewer cannot reach as if they did not exist, so a restricted user cannot tell
 * an inaccessible category apart from a missing one.
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially on the property
 * (see PatchHostInput): HostCategoryId asserts a strictly positive int per element and would throw instead
 * of producing a clean violation. The command handler's own check stays the authority.
 */
final class AccessibleHostCategoriesValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly HostCategoryRepository $hostCategoryRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleHostCategories) {
            throw new UnexpectedTypeException($constraint, AccessibleHostCategories::class);
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
        $categoryIds = new Collection(
            array_map(static fn (int $id): HostCategoryId => new HostCategoryId($id), $requestedIds),
            HostCategoryId::class,
        );

        $missingIds = array_diff($requestedIds, array_keys($this->hostCategoryRepository->findNamesByIds($categoryIds)->toArray()));

        if (! $credentialUser->credential->hasUnrestrictedResourceAccess()) {
            $accessibleIds = $this->resourceAccessRepository->findAccessibleHostCategoryIds($credentialUser->credential->userId);
            if ($accessibleIds instanceof Collection) {
                $accessibleIdValues = array_map(static fn (HostCategoryId $id): int => $id->value, $accessibleIds->toArray());
                $missingIds = array_unique(array_merge($missingIds, array_diff($requestedIds, $accessibleIdValues)));
            }
        }

        if ($missingIds !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
