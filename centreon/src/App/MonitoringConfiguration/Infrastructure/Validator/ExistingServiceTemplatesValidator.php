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
 * Service templates only: a service id is not one.
 */
final class ExistingServiceTemplatesValidator extends ConstraintValidator
{
    public function __construct(private readonly ServiceTemplateRepository $serviceTemplateRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingServiceTemplates) {
            throw new UnexpectedTypeException($constraint, ExistingServiceTemplates::class);
        }

        if (! is_array($value) || $value === []) {
            return;
        }

        /** @var list<int> $requestedIds */
        $requestedIds = $value;
        $serviceTemplateIds = new Collection(
            array_map(static fn (int $id): ServiceTemplateId => new ServiceTemplateId($id), $requestedIds),
            ServiceTemplateId::class,
        );

        if (array_diff($requestedIds, array_keys($this->serviceTemplateRepository->findNamesByIds($serviceTemplateIds)->toArray())) !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
