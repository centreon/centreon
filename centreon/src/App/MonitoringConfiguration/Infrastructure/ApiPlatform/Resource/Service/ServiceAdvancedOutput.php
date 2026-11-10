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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * The `advanced` sub-object; on Cloud, on-premise-only members are null (omitted) and event handler args empty.
 */
final readonly class ServiceAdvancedOutput
{
    /**
     * @param ?list<string> $stalkingOptions
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        public ?TriStateEnum $obsessOverService,
        public ?int $acknowledgmentTimeout,
        public TriStateEnum $checkFreshness,
        public ?int $freshnessThreshold,
        public ?TriStateEnum $flapDetectionEnabled,
        public ?int $lowFlapThreshold,
        public ?int $highFlapThreshold,
        public ?TriStateEnum $retainStatusInformation,
        public ?TriStateEnum $retainNonStatusInformation,
        #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ServiceStalkingOptionEnumResolver::API_VALUES]])]
        public ?array $stalkingOptions,
        public TriStateEnum $eventHandlerEnabled,
        public ?ServiceEventHandlerCommandOutput $eventHandler,
        #[Sensitive] public array $eventHandlerArgs,
        public ?string $comment,
    ) {
    }
}
