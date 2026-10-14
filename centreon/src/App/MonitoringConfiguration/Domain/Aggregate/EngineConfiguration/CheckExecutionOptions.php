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

namespace App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration;

final readonly class CheckExecutionOptions
{
    public function __construct(
        public bool $enableNotifications = true,
        public bool $executeServiceChecks = true,
        public bool $acceptPassiveServiceChecks = true,
        public bool $executeHostChecks = true,
        public bool $acceptPassiveHostChecks = true,
        public bool $enableEventHandlers = true,
        public bool $enablePredictiveHostDependencyChecks = true,
        public bool $enablePredictiveServiceDependencyChecks = true,
        public bool $hostDownDisableServiceChecks = true,
        public bool $softStateDependencies = false,
        public bool $checkForOrphanedServices = true,
        public bool $checkForOrphanedHosts = true,
    ) {
    }
}
