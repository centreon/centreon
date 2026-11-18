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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Always declared after Assert\Positive inside an Assert\Sequentially on the property (see
 * CreateHostInput): MediaId asserts a strictly positive int and would throw instead of producing
 * a clean violation, so this validator must never run on a value Positive would have rejected.
 */
final class ExistingMediaValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingMedia) {
            throw new UnexpectedTypeException($constraint, ExistingMedia::class);
        }

        if (! is_int($value)) {
            return;
        }

        if (! $this->mediaRepository->existsOne(new MediaId($value))) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
