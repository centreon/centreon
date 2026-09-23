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

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Time periods carry no ACL scoping of their own (see DbalTimePeriodRepository), so plain
 * existence is the whole check — unlike contacts, which are also scoped to the viewer.
 *
 * Always declared after Assert\Positive inside an Assert\Sequentially on the property (see
 * CreateHostNotificationsInput): TimePeriodId asserts a strictly positive int and would throw
 * instead of producing a clean violation.
 */
final class ExistingTimePeriodValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TimePeriodRepository $timePeriodRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExistingTimePeriod) {
            throw new UnexpectedTypeException($constraint, ExistingTimePeriod::class);
        }

        if (! is_int($value)) {
            return;
        }

        $ids = new Collection([new TimePeriodId($value)], TimePeriodId::class);

        if (count($this->timePeriodRepository->findNamesByIds($ids)) === 0) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
