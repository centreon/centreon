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

use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidCommandArgumentsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ValidCommandArguments) {
            throw new UnexpectedTypeException($constraint, ValidCommandArguments::class);
        }

        if (! is_array($value)) {
            return;
        }

        $arguments = [];
        foreach ($value as $argument) {
            if (! is_string($argument)) {
                return;
            }

            $arguments[] = $argument;
        }

        $subject = ucfirst(preg_match('/^[aeiou]/i', $constraint->subject) === 1 ? 'an ' : 'a ') . $constraint->subject;

        foreach ($arguments as $index => $argument) {
            if (str_contains($argument, '!')) {
                $this->context->buildViolation($subject . ' argument cannot contain "!".')
                    ->atPath('[' . $index . ']')
                    ->addViolation();
            }

            if (preg_match('/#(?:BR|T|R)#/', $argument) === 1) {
                $this->context->buildViolation($subject . ' argument cannot contain the reserved escape tokens #BR#, #T# or #R#.')
                    ->atPath('[' . $index . ']')
                    ->addViolation();
            }
        }

        // No per-argument or count limit: only the single string the repository ultimately stores in a
        // TEXT column is bounded. The exact formatter the repository uses measures what will be
        // persisted, in bytes since the column limit is in bytes.
        $formatted = CommandArgumentsFormatter::format($arguments);
        if ($formatted !== null && \mb_strlen($formatted, '8bit') > CommandArgumentsFormatter::MAX_STORAGE_LENGTH) {
            $this->context->buildViolation('The ' . $constraint->subject . ' arguments are too long.')->addViolation();
        }
    }
}
