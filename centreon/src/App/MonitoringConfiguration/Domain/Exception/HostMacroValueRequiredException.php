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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\Shared\Domain\Exception\AggregateConflictException;

/**
 * A submitted macro asks to keep its stored value (null) while the macro it refers to is not a
 * stored password: only a password's value is never echoed back, so only it can be kept that way.
 * Reported against `checkOptions`, the payload field the macros are submitted in.
 */
final class HostMacroValueRequiredException extends AggregateConflictException
{
    /**
     * @param list<string> $names
     */
    public function __construct(array $names)
    {
        parent::__construct(['checkOptions' => $names], 'Only an existing password macro can keep its stored value.');
    }
}
