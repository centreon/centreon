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

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostNotificationsTransformer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Legacy could not express this combination at all: it took the options as a bit flag, where
 * "none" is the zero value. A list can express it, so it has to be rejected explicitly — the
 * Notifications value object asserts the same invariant, this only turns it into a clean 422.
 */
final class ExclusiveNotificationOptionValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExclusiveNotificationOption) {
            throw new UnexpectedTypeException($constraint, ExclusiveNotificationOption::class);
        }

        if (! is_array($value) || count($value) < 2) {
            return;
        }

        if (in_array(HostNotificationsTransformer::optionToApi(NotificationOptionEnum::None), $value, true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
