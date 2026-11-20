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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Host templates are never scoped by access rights: legacy has no access-group variant for them.
 *
 * Always declared after Assert\All([Type, Positive]) inside an Assert\Sequentially on the property
 * (see PatchHostInput): HostTemplateId asserts a strictly positive int per element and would throw instead
 * of producing a clean violation. The command handler's own check stays the authority.
 */
final class ExistingHostTemplatesValidator extends ConstraintValidator
{
    public function __construct(private readonly HostTemplateRepository $hostTemplateRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingHostTemplates) {
            throw new UnexpectedTypeException($constraint, ExistingHostTemplates::class);
        }

        if (! is_array($value) || $value === []) {
            return;
        }

        /** @var list<int> $requestedIds */
        $requestedIds = $value;
        $templateIds = new Collection(
            array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $requestedIds),
            HostTemplateId::class,
        );

        if (array_diff($requestedIds, array_keys($this->hostTemplateRepository->findNamesByIds($templateIds)->toArray())) !== []) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
