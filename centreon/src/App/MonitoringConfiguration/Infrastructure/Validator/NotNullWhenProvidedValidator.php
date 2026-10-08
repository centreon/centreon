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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostPayload;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotNullWhenProvidedValidator extends ConstraintValidator
{
    private CamelCaseToSnakeCaseNameConverter $nameConverter;

    public function __construct(private readonly RequestStack $requestStack)
    {
        $this->nameConverter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof NotNullWhenProvided) {
            throw new UnexpectedTypeException($constraint, NotNullWhenProvided::class);
        }

        if ($value !== null) {
            return;
        }

        $property = $this->context->getPropertyName();
        if ($property === null) {
            return;
        }

        $payload = PatchHostPayload::fromRequest($this->requestStack->getCurrentRequest());
        if ($payload->isNull($this->nameConverter->normalize($property))) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
