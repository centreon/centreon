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

namespace Core\Application\Common\UseCase;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationErrorResponse extends AbstractResponse implements BodyResponseInterface
{
    private readonly ConstraintViolationListInterface $violations;

    /**
     * The property that would carry the context / violations
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $body = [];

    public function __construct(ConstraintViolationListInterface $violations)
    {
        $violationsByField = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            $violationsByField[$field][] = (string) $violation->getMessage();
        }

        parent::__construct(
            'validation_failed',
            ['violations' => $violationsByField]
        );

        $this->violations = $violations;
        $this->body = ['violations' => $violationsByField];
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function setBody(mixed $body): void
    {
        $this->body = $body;
    }

    /**
     * Get the body of the response.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public function getBody(): array
    {
        return $this->body;
    }
}
