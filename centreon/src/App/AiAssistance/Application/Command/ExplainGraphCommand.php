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

use Webmozart\Assert\Assert;

/**
 * The performance graph displayed in the details panel, to explain. The data
 * comes from the caller (it already passed the user's ACL): this feature never
 * reads monitoring data on its own.
 */
final readonly class ExplainGraphCommand
{
    public const MAX_METRICS = 50;

    /**
     * @param list<GraphMetric> $metrics
     */
    public function __construct(
        public string $name,
        public string $resourceType,
        public array $metrics,
        public ?string $parentName = null,
        public ?string $timeRange = null,
        public ?string $performanceData = null,
    ) {
        Assert::lengthBetween($name, 1, 255);
        Assert::lengthBetween($resourceType, 1, 50);
        Assert::notEmpty($metrics);
        Assert::maxCount($metrics, self::MAX_METRICS);
        Assert::allIsInstanceOf($metrics, GraphMetric::class);
        if ($parentName !== null) {
            Assert::maxLength($parentName, 255);
        }
        if ($timeRange !== null) {
            Assert::maxLength($timeRange, 255);
        }
        if ($performanceData !== null) {
            Assert::maxLength($performanceData, 10000);
        }
    }
}
