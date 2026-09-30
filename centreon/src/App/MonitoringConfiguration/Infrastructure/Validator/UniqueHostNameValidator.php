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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early, best-effort duplicate of CreateHostCommandHandler's own uniqueness check: a nicer 422
 * with a field-level violation for the common case, reachable through the real webapp/API
 * clients. The handler's check is the actual authority and is deliberately kept — this constraint
 * only shortens the round-trip for the vast majority of requests, it does not replace it (a
 * race between two concurrent creations is still caught there, surfacing as 409).
 *
 * Always declared after NotBlank/Length/Regex inside an Assert\Sequentially on the property (see
 * CreateHostInput): HostName asserts a length between MIN_LENGTH and MAX_LENGTH and would throw
 * instead of producing a clean violation, so this validator must never run on a value those
 * constraints would have rejected.
 */
final class UniqueHostNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly HostRepository $repository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof UniqueHostName) {
            throw new UnexpectedTypeException($constraint, UniqueHostName::class);
        }

        if (! is_string($value)) {
            return;
        }

        try {
            $name = new HostName($value);
        } catch (\InvalidArgumentException) {
            return;
        }

        if ($this->repository->isNameUsedByHostOrTemplate($name)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
