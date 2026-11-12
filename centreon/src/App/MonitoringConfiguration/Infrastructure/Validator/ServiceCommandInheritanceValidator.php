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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A check command, and an event handler for event handler arguments, set on the service or inherited from its
 * template chain; the create handler re-checks both as the authority. An unknown template is left to its own
 * constraint.
 */
final class ServiceCommandInheritanceValidator extends ConstraintValidator
{
    public function __construct(private readonly ServiceTemplateRepository $serviceTemplateRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ServiceCommandInheritance) {
            throw new UnexpectedTypeException($constraint, ServiceCommandInheritance::class);
        }

        if (! $value instanceof CreateServiceInput) {
            return;
        }

        $line = [];
        if ($value->serviceTemplateId !== null) {
            if ($value->serviceTemplateId < 1) {
                return;
            }

            $line = $this->serviceTemplateRepository->findInheritanceLine(new ServiceTemplateId($value->serviceTemplateId))->toArray();
            if ($line === []) {
                return;
            }
        }

        $hasCheckCommand = $value->checkOptions?->commandId !== null
            || array_any($line, static fn (ServiceTemplate $template): bool => $template->checkCommandId instanceof CommandId);
        if (! $hasCheckCommand) {
            $value->serviceTemplateId === null
                ? $this->context->buildViolation($constraint->missingCommandMessage)->atPath('checkOptions.commandId')->addViolation()
                : $this->context->buildViolation($constraint->templateWithoutCommandMessage)->atPath('serviceTemplateId')->addViolation();

            if ($this->hasArguments($value->checkOptions->args ?? [])) {
                $this->context->buildViolation($constraint->argumentsWithoutCommandMessage)->atPath('checkOptions.args')->addViolation();
            }
        }

        $hasEventHandler = $value->advanced?->eventHandlerCommandId !== null
            || array_any($line, static fn (ServiceTemplate $template): bool => $template->eventHandlerCommandId instanceof CommandId);
        if (! $hasEventHandler && $this->hasArguments($value->advanced->eventHandlerArgs ?? [])) {
            $this->context->buildViolation($constraint->eventHandlerArgumentsWithoutHandlerMessage)->atPath('advanced.eventHandlerArgs')->addViolation();
        }
    }

    /**
     * @param array<mixed> $arguments
     */
    private function hasArguments(array $arguments): bool
    {
        return array_any($arguments, static fn (mixed $argument): bool => $argument !== '');
    }
}
