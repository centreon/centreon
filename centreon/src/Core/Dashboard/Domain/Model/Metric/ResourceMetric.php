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

namespace Core\Dashboard\Domain\Model\Metric;

use Core\Dashboard\Domain\Model\PerformanceMetric;

class ResourceMetric
{
    /**
     *
     * @param integer $serviceId
     * @param string $resourceName
     * @param PerformanceMetric[] $metrics
     */
    public function __construct(
        private readonly int $serviceId,
        private readonly string $resourceName,
        private readonly array $metrics
    ) {
    }

    public function getServiceId(): int
    {
        return $this->serviceId;
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    /**
     * @return PerformanceMetric[]
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}