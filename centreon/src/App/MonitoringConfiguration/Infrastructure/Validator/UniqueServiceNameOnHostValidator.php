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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * The name of a created service must be free on its host, compared as it will be stored. The create handler checks
 * it again, as the authority, against a name taken in between (409).
 *
 * A class constraint, since it needs the host too, and it only runs once the host is valid: an invalid host is
 * reported on its own field, and an actor whose access is restricted cannot learn that a name exists on a host
 * they cannot use. A name the other rules refuse is left to them.
 */
final class UniqueServiceNameOnHostValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly ServiceRepository $serviceRepository,
        private readonly HostRepository $hostRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof UniqueServiceNameOnHost) {
            throw new UnexpectedTypeException($constraint, UniqueServiceNameOnHost::class);
        }

        if (! $value instanceof CreateServiceInput || $value->hostId < 1) {
            return;
        }

        $credentialUser = $this->security->getUser();
        if (! $credentialUser instanceof CredentialUser) {
            return;
        }

        $hostId = new HostId($value->hostId);
        if (! $this->hostRepository->findState($hostId, $credentialUser->credential->actingUser()) instanceof HostState) {
            return;
        }

        try {
            $name = new ServiceName(CreateServiceInput::normalizeName($value->name));
        } catch (\InvalidArgumentException) {
            return;
        }

        if ($this->serviceRepository->isNameUsedOnHost($name, $hostId)) {
            $this->context->buildViolation($constraint->message)->atPath('name')->addViolation();
        }
    }
}
