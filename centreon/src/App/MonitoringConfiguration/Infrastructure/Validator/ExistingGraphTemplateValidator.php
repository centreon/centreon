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

use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early duplicate of the create handler's own graph template check, which stays the authority. Never scoped by ACL.
 *
 * Always declared after Assert\Positive inside an Assert\Sequentially: GraphTemplateId asserts a positive int.
 */
final class ExistingGraphTemplateValidator extends ConstraintValidator
{
    public function __construct(private readonly GraphTemplateRepository $repository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingGraphTemplate) {
            throw new UnexpectedTypeException($constraint, ExistingGraphTemplate::class);
        }

        if (! is_int($value)) {
            return;
        }

        $names = $this->repository->findNamesByIds(new Collection([new GraphTemplateId($value)], GraphTemplateId::class));
        if (! isset($names->toArray()[$value])) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
