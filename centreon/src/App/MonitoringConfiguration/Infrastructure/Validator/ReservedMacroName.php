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
 * A custom macro name must not be one of the engine's reserved macros once wrapped in its stored form
 * (`<prefix><NAME>$`): `$_HOST` for a host macro (the default), `$_SERVICE` for a service macro.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ReservedMacroName extends Constraint
{
    public string $message = 'This macro name is reserved and cannot be used.';

    /**
     * @param string $prefix the start of the stored macro name, before the upper-cased short name
     */
    public function __construct(
        public string $prefix = '$_HOST',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }
}
