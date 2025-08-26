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

namespace Core\Common\Infrastructure\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class SortValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof Sort) {
            throw new UnexpectedTypeException($constraint, Sort::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        try {
            $invalidSortKeys = [];
            $invalidSortDirections = [];
            $decodedValue = json_decode($value, true, JSON_THROW_ON_ERROR);

            foreach ($decodedValue as $sortKey => $sortDirection) {
                if (! in_array($sortKey, $constraint->sortKeys, true)) {
                    $invalidSortKeys[] = $sortKey;
                }

                if (! in_array($sortDirection, $constraint->sortDirections, true)) {
                    $invalidSortDirections[$sortKey] = $sortDirection;
                }
            }

            foreach ($invalidSortKeys as $invalidKey) {
                $this->context->buildViolation($constraint->invalidSortKeyMessage)
                    ->setParameters([
                        '{{ key }}' => $invalidKey,
                        '{{ values }}' => implode(', ', $constraint->sortKeys),
                    ])
                    ->addViolation();
            }

            foreach ($invalidSortDirections as $key => $invalidDirection) {
                $this->context->buildViolation($constraint->invalidSortDirectionMesssage)
                    ->setParameters([
                        '{{ key }}' => $key,
                        '{{ direction }}' => $invalidDirection,
                        '{{ values }}' => implode(',', $constraint->sortDirections),
                    ])
                    ->addViolation();
            }
        } catch (\JsonException $exception) {
            $this->context->buildViolation('The value is not a valid JSON string.')
                ->addViolation();
        }
    }
}
