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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Reports a domain failure caused by the payload as a field-level violation, so a reference check
 * can live only in the handler, the one layer able to check it against the state it writes.
 *
 * A criterion naming a field of the operation's input DTO came from the payload; anything else
 * keeps its own status, which is what preserves 404 for a missing target aggregate.
 *
 * Priority 10 keeps /api/latest's downgrade to 400 working (LegacyValidationStatusListener).
 */
#[AsEventListener(priority: 10)]
final readonly class InvalidReferenceExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        // AggregateAlreadyExistsException is absent on purpose: it conflicts with stored state,
        // not with the payload, and stays a 409.
        if (! $exception instanceof AggregateNotFoundException && ! $exception instanceof AggregateConflictException) {
            return;
        }

        $inputClass = $this->inputClass($event);
        if ($inputClass === null) {
            return;
        }

        // PHP turns a numeric criteria key into an int, which property_exists() would reject.
        /** @var array<array-key, mixed> $criteria */
        $criteria = $exception->criteria;

        $violations = new ConstraintViolationList();
        foreach (array_keys($criteria) as $criterion) {
            if (is_string($criterion) && property_exists($inputClass, $criterion)) {
                // Left in PHP form: ApiPlatform's normalizer applies the name converter.
                $violations->add(new ConstraintViolation($exception->getMessage(), null, [], null, $criterion, null));
            }
        }

        if (count($violations) === 0) {
            return;
        }

        $event->setThrowable(new ValidationException($violations, previous: $exception));
    }

    /**
     * @return class-string|null
     */
    private function inputClass(ExceptionEvent $event): ?string
    {
        $operation = $event->getRequest()->attributes->get('_api_operation');
        if (! $operation instanceof Operation) {
            return null;
        }

        // ['class' => ...] through the metadata factory, a bare string when built directly.
        $input = $operation->getInput();
        $class = is_array($input) ? ($input['class'] ?? null) : $input;

        return is_string($class) && class_exists($class) ? $class : null;
    }
}
