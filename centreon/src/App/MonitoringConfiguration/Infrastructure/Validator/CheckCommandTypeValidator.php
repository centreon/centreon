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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Flags a referenced command that exists but is not of type "check" (→422). Existence itself is
 * deliberately NOT reported here: a missing command is left to CreateHostCommandHandler, which
 * surfaces it as a 404 like the other host-creation references — so a not-found command is
 * tolerated silently here, mirroring AccessiblePollerValidator.
 */
final class CheckCommandTypeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CommandRepository $commandRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof CheckCommandType) {
            throw new UnexpectedTypeException($constraint, CheckCommandType::class);
        }

        if (! is_int($value)) {
            return;
        }

        try {
            $command = $this->commandRepository->getById(new CommandId($value));
        } catch (CommandNotFoundException) {
            return;
        }

        if ($command->type !== CommandTypeEnum::Check) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
