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
 * Keeping the stored value (a null value) applies only to a stored password macro.
 */
final class HostTemplateMacroValueRequiredException extends AggregateConflictException
{
    /**
     * @param list<string> $names
     */
    public function __construct(array $names)
    {
        parent::__construct(['checkOptions' => $names], 'A value is required: these macros are not stored as passwords, so their value cannot be kept.');
    }
}
