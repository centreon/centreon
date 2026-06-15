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

namespace App\AiAssistance\Application\Command;

/**
 * Correlate several monitored resources that are in a non-OK state, to surface
 * a likely common root cause rather than treating each alert in isolation.
 */
final readonly class CorrelateAlertsCommand
{
    public const MAX_RESOURCES = 50;

    /**
     * @param list<CorrelatedResource> $resources
     */
    public function __construct(
        public array $resources,
    ) {
    }
}
