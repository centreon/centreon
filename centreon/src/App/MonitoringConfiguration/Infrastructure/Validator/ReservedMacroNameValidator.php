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

use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Rejects a custom host macro whose `$_HOST<NAME>$` form collides with a reserved macro listed in
 * `nagios_macro` (see MON-208474). This restores the validation legacy intended but never actually
 * enforced — its QuickForm rule bound each name double-quoted, so the IN () check never matched.
 */
final class ReservedMacroNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly StandardMacroRepository $standardMacroRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ReservedMacroName) {
            throw new UnexpectedTypeException($constraint, ReservedMacroName::class);
        }

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $storageName = '$_HOST' . mb_strtoupper(trim($value)) . '$';

        /** @var StandardMacro $reservedMacro */
        foreach ($this->standardMacroRepository->findAll() as $reservedMacro) {
            if ($reservedMacro->name->value === $storageName) {
                $this->context->buildViolation($constraint->message)->addViolation();

                return;
            }
        }
    }
}
