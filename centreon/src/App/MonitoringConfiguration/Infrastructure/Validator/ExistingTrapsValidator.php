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

use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early duplicate of the create handler's own trap check, which stays the authority, through the same finder: a
 * trap without vendor or name, never offered by the selector, is unknown. Never scoped by ACL.
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially: TrapId asserts a positive int.
 */
final class ExistingTrapsValidator extends ConstraintValidator
{
    public function __construct(private readonly TrapRepository $repository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingTraps) {
            throw new UnexpectedTypeException($constraint, ExistingTraps::class);
        }

        if (! is_array($value) || $value === []) {
            return;
        }

        /** @var list<int> $requestedIds */
        $requestedIds = $value;
        $trapIds = new Collection(array_map(static fn (int $id): TrapId => new TrapId($id), $requestedIds), TrapId::class);

        if (array_diff($requestedIds, array_keys($this->repository->findByIds($trapIds)->toArray())) !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
