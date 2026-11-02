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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Domain\Aggregate\TriStateEnum;

/**
 * The `monitoring_engine` sub-object of a service: its scheduling options and its notes, URLs, icon and
 * coordinates. On a Cloud platform the on-premise-only members (active and passive checks, volatility) are left
 * null, and so out of the payload.
 */
final readonly class ServiceMonitoringEngineOutput
{
    public function __construct(
        #[ApiProperty(readableLink: true)]
        public ?TimePeriodResource $checkPeriod,
        public ?int $maxCheckAttempts,
        public ?int $normalCheckInterval,
        public ?int $retryCheckInterval,
        public ?TriStateEnum $activeCheckEnabled,
        public ?TriStateEnum $passiveCheckEnabled,
        public ?TriStateEnum $isVolatile,
        public ?string $noteUrl,
        public ?string $note,
        public ?string $actionUrl,
        public ?ServiceIconOutput $icon,
        public ?string $altIcon,
        public ?string $geoCoordinates,
    ) {
    }
}
