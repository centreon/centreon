<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\API\FindPerformanceMetrics;

class FindPerformanceMetricsValidator
{
    public const PARAM_STATES = 'states';
    public const PARAM_STATUSES = 'statuses';

    public const ALLOWED_STATUSES = [
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        'UNREACHABLE',
        'PENDING',
        'UP',
        'DOWN',
    ];

    /** Allowed values for states. */
    public const ALLOWED_STATES = [
        'in_downtime',
        'acknowledged',
    ];

    /** Allowed values for status types. */
    public const ALLOWED_STATUS_TYPES = [
        'hard',
        'soft',
    ];
}
