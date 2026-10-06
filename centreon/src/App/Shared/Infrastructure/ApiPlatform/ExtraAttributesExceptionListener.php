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

namespace App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Reports a payload field an input refuses (denormalized with allow_extra_attributes = false) as a
 * field-level violation, like any other invalid field, instead of the 500 the serializer exception
 * would otherwise produce.
 *
 * Priority 10 keeps /api/latest's downgrade to 400 working (LegacyValidationStatusListener).
 */
#[AsEventListener(priority: 10)]
final readonly class ExtraAttributesExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (! $exception instanceof ExtraAttributesException) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach ($exception->getExtraAttributes() as $attribute) {
            // Left in PHP form: ApiPlatform's normalizer applies the name converter.
            $violations->add(new ConstraintViolation('This field is not allowed.', null, [], null, $attribute, null));
        }

        $event->setThrowable(new ValidationException($violations, previous: $exception));
    }
}
