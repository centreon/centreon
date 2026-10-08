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

use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotNullWhenProvidedValidator extends ConstraintValidator
{
    private readonly CamelCaseToSnakeCaseNameConverter $nameConverter;

    private ?Request $payloadRequest = null;

    private ?RequestPayload $payload = null;

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

        $request = $this->requestStack->getCurrentRequest();
        $path = $this->context->getPropertyPath();
        if (! $request instanceof Request || $path === '') {
            return;
        }

        $steps = $this->stepsOf($path);
        $last = array_pop($steps);
        if ($last === null) {
            return;
        }

        $payload = $this->payloadOf($request);
        foreach ($steps as $step) {
            $payload = is_int($step) ? $payload->item($step) : $payload->section($step);
        }

        $isNull = is_int($last) ? $payload->itemIsNull($last) : $payload->isNull($last);
        if ($isNull) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    /**
     * The path of a property in the body, as the keys to walk down and the indexes of the lists crossed
     * on the way: `macros[0].name` gives `macros`, 0, `name`.
     *
     * @return list<int|string>
     */
    private function stepsOf(string $path): array
    {
        preg_match_all('/\[(?<index>\d+)\]|(?<key>[^.\[\]]+)/', $path, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $steps = [];
        foreach ($matches as $match) {
            $steps[] = $match['key'] !== null ? $this->nameConverter->normalize($match['key']) : (int) $match['index'];
        }

        return $steps;
    }

    /**
     * Decoded once per request, however many properties carry the constraint.
     */
    private function payloadOf(Request $request): RequestPayload
    {
        if (! $this->payload instanceof RequestPayload || $this->payloadRequest !== $request) {
            $this->payloadRequest = $request;
            $this->payload = RequestPayload::fromRequest($request);
        }

        return $this->payload;
    }
}
