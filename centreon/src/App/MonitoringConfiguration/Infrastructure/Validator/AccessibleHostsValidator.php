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
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Rejects hosts the viewer cannot see as if they did not exist, like HostRepository::findOne() does, so a
 * restricted user cannot tell an inaccessible host apart from a missing one.
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially on the property
 * (see PatchHostInput): HostId asserts a strictly positive int per element and would throw instead
 * of producing a clean violation. The command handler's own check stays the authority.
 */
final class AccessibleHostsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly HostRepository $hostRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessibleHosts) {
            throw new UnexpectedTypeException($constraint, AccessibleHosts::class);
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
        $hostIds = new Collection(
            array_map(static fn (int $id): HostId => new HostId($id), $requestedIds),
            HostId::class,
        );
        $viewerId = $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId;

        if (array_diff($requestedIds, array_keys($this->hostRepository->findNamesByIds($hostIds, $viewerId)->toArray())) !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
