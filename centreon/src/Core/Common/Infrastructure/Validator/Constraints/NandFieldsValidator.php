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

final class NandFieldsValidator extends ConstraintValidator
{
    public function validate(mixed $object, Constraint $constraint): void
    {
        if (! $constraint instanceof NandFields) {
            throw new UnexpectedTypeException($constraint, NandFields::class);
        }

        if (! is_object($object)) {
            throw new UnexpectedValueException($object, 'object');
        }

        $properties = $constraint->properties;
        $presentCount = 0;
        foreach ($properties as $property) {
            if (property_exists($object, $property) && $object->{$property} !== null) {
                $presentCount++;
            }

            if ($presentCount > 1) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ properties }}', implode(', ', $properties))
                    ->addViolation();

                return;
            }
        }
    }
}
