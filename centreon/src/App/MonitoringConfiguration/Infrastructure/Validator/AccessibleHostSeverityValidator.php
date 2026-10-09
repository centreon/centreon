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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Never distinguishes "severity doesn't exist" from "exists but isn't accessible to this viewer", to
 * avoid leaking existence information to a restricted viewer.
 *
 * Always declared after Assert\Positive inside an Assert\Sequentially on the property:
 * HostSeverityId asserts a strictly positive int and would throw instead of producing a clean
 * violation, so this validator must never run on a value Positive would have rejected.
 */
final class AccessibleHostSeverityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly HostSeverityRepository $hostSeverityRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleHostSeverity) {
            throw new UnexpectedTypeException($constraint, AccessibleHostSeverity::class);
        }

        if (! is_int($value)) {
            return;
        }

        $credentialUser = $this->security->getUser();
        if (! $credentialUser instanceof CredentialUser) {
            return;
        }

        $severityId = new HostSeverityId($value);

        if (! $this->hostSeverityRepository->findNameById($severityId) instanceof HostSeverityName) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if ($credentialUser->credential->hasUnrestrictedResourceAccess()) {
            return;
        }

        $accessibleIds = $this->resourceAccessRepository->findAccessibleHostSeverityIds($credentialUser->credential->userId);
        if (
            $accessibleIds instanceof Collection
            && ! array_any(
                $accessibleIds->toArray(),
                static fn (HostSeverityId $accessibleId): bool => $accessibleId->value === $severityId->value,
            )
        ) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
