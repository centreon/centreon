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

namespace App\Shared\Infrastructure\Validator\Constraints;

use App\Shared\Infrastructure\ApiPlatform\CurrentRequestPayload;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotNullWhenProvidedValidator extends ConstraintValidator
{
    public function __construct(private readonly CurrentRequestPayload $currentPayload)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof NotNullWhenProvided) {
            throw new UnexpectedTypeException($constraint, NotNullWhenProvided::class);
        }

        if ($value !== null) {
            return;
        }

        $payload = $this->currentPayload->get();
        $steps = RequestPayload::steps($this->context->getPropertyPath());
        $last = array_pop($steps);
        if (! $payload instanceof RequestPayload || $last === null) {
            return;
        }

        $payload = $payload->walk($steps);
        $isNull = is_int($last) ? $payload->itemIsNull($last) : $payload->isNull($last);
        if ($isNull) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
