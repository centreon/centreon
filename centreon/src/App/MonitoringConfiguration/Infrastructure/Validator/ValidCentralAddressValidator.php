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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Turns a malformed central address into a 400 with an actionable message.
 *
 * The rules are not restated here: the value object is the single source of truth, and without this
 * constraint its assertion would surface as an unmapped exception from the processor, which
 * api_platform.yaml has no status for.
 */
final class ValidCentralAddressValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ValidCentralAddress) {
            throw new UnexpectedTypeException($constraint, ValidCentralAddress::class);
        }

        // Emptiness is NotBlank's job, and reporting it twice would just clutter the response.
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            new CentralAddress($value);
        } catch (\InvalidArgumentException) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
