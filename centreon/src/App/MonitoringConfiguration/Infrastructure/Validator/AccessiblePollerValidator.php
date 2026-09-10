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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early, best-effort duplicate of CreateHostCommandHandler's own poller-scoping check: a nicer
 * 422 with a field-level violation for the common case, reachable through the real webapp/API
 * clients. The handler's check is the actual authority and is deliberately kept — this constraint
 * only shortens the round-trip for the vast majority of requests, it does not replace it (a
 * cache-stale or racing access-grant change between validation and command execution is still
 * caught there, surfacing as 404).
 *
 * Never distinguishes "poller doesn't exist" from "exists but isn't accessible to this viewer",
 * matching the handler, to avoid leaking existence information to a restricted viewer.
 */
final class AccessiblePollerValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly PollerRepository $pollerRepository,
        private readonly ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AccessiblePoller) {
            throw new UnexpectedTypeException($constraint, AccessiblePoller::class);
        }

        // Type/Positive constraints report the shape issue; nothing to check here without one.
        if (! is_int($value)) {
            return;
        }

        $credentialUser = $this->security->getUser();
        if (! $credentialUser instanceof CredentialUser) {
            return;
        }

        $pollerId = new PollerId($value);

        try {
            $this->pollerRepository->get($pollerId);
        } catch (PollerNotFoundException) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (
            ! $credentialUser->credential->hasUnrestrictedResourceAccess()
            && ! $this->resourceAccessRepository->hasAccessToPoller($pollerId, $credentialUser->credential->userId)
        ) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
