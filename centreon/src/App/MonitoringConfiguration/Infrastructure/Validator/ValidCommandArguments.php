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

use Symfony\Component\Validator\Constraint;

/**
 * The arguments a command is called with: none may carry the delimiter or an escape token of the
 * stored form, and the stored form has to fit its column (see {@see ValidCommandArgumentsValidator}).
 *
 * Declare it next to Assert\All(Type('string')): it leaves the entries that are not strings to it.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidCommandArguments extends Constraint
{
    /**
     * @param string $subject what the arguments are for, named in the messages (e.g. `check command`)
     */
    public function __construct(
        public string $subject,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }
}
