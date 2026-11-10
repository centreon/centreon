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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Early mirror of the handler's check. Not ACL-scoped. Needs a prior Assert\Positive.
 */
final class ExistingServiceTemplateValidator extends ConstraintValidator
{
    public function __construct(private readonly ServiceTemplateRepository $repository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingServiceTemplate) {
            throw new UnexpectedTypeException($constraint, ExistingServiceTemplate::class);
        }

        if (! is_int($value)) {
            return;
        }

        $names = $this->repository->findNamesByIds(new Collection([new ServiceTemplateId($value)], ServiceTemplateId::class));
        if (! isset($names->toArray()[$value])) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
