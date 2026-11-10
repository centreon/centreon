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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityName;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early mirror of the handler's check; unknown and hidden ids answer alike. Needs a prior Assert\Positive.
 */
final class AccessibleServiceSeverityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly ServiceSeverityRepository $repository,
        private readonly AccessScopeResolver $accessScopes,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleServiceSeverity) {
            throw new UnexpectedTypeException($constraint, AccessibleServiceSeverity::class);
        }

        if (! is_int($value)) {
            return;
        }

        $credentialUser = $this->security->getUser();
        if (! $credentialUser instanceof CredentialUser) {
            return;
        }

        $severityId = new ServiceSeverityId($value);

        if (
            ! $this->repository->findNameById($severityId) instanceof ServiceSeverityName
            || ! $this->accessScopes->serviceSeverities($credentialUser->credential->actingUser())->allows($severityId)
        ) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
