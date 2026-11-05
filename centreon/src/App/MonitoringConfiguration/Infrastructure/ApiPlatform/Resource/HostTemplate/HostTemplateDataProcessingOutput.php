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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use App\Shared\Domain\Aggregate\TriStateEnum;

/**
 * On a Cloud platform the acknowledgment timeout and the flap detection settings are null and the
 * event handler arguments empty.
 */
final readonly class HostTemplateDataProcessingOutput
{
    /**
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        public TriStateEnum $checkFreshness,
        public ?int $freshnessThreshold,
        public TriStateEnum $eventHandlerEnabled,
        public ?HostTemplateEventHandlerCommandOutput $eventHandler,
        public ?int $acknowledgmentTimeout,
        public ?TriStateEnum $flapDetectionEnabled,
        public ?int $lowFlapThreshold,
        public ?int $highFlapThreshold,
        public array $eventHandlerArgs,
    ) {
    }
}
