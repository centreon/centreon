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

namespace App\AiAssistance\Infrastructure\ApiPlatform\Dto;

use ApiPlatform\Metadata\ApiProperty;
use App\AiAssistance\Application\Command\ExplainGraphCommand;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The performance graph displayed in the details panel, as summarised by the
 * caller (the data already passed the user's ACL).
 */
final class ExplainGraphInput
{
    /**
     * @param list<GraphMetricInput> $metrics
     */
    public function __construct(
        #[ApiProperty(
            description: 'Name of the resource',
            openapiContext: ['example' => 'Ping'],
        )]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[ApiProperty(
            description: 'Type of the resource',
            openapiContext: ['example' => 'service'],
        )]
        #[Assert\Length(min: 1, max: 50)]
        public string $resourceType,

        #[ApiProperty(description: 'The graph metrics summarised over the displayed window')]
        #[Assert\Count(min: 1, max: ExplainGraphCommand::MAX_METRICS)]
        #[Assert\Valid]
        public array $metrics,

        #[ApiProperty(
            description: 'Name of the parent host, for services',
            openapiContext: ['example' => 'db-server-01'],
        )]
        #[Assert\Length(max: 255)]
        public ?string $parentName = null,

        #[ApiProperty(
            description: 'Human-readable label of the displayed time window',
            openapiContext: ['example' => '2026-06-14 13:00 → 2026-06-15 13:00'],
        )]
        #[Assert\Length(max: 255)]
        public ?string $timeRange = null,

        #[ApiProperty(description: 'Latest performance data, carrying thresholds')]
        #[Assert\Length(max: 10000)]
        public ?string $performanceData = null,
    ) {
    }
}
