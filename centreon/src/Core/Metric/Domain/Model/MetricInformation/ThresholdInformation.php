<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Metric\Domain\Model\MetricInformation;

class ThresholdInformation
{
    public function __construct(
        private readonly ?float $warningThreshold,
        private readonly ?float $warningLowThreshold,
        private readonly ?float $criticalThreshold,
        private readonly ?float $criticalLowThreshold,
        private readonly string $colorWarning,
        private readonly string $colorCritical,
    ){
    }

    public function getWarningThreshold(): ?float
    {
        return $this->warningThreshold;
    }

    public function getWarningLowThreshold(): ?float
    {
        return $this->warningLowThreshold;
    }

    public function getCriticalThreshold(): ?float
    {
        return $this->criticalThreshold;
    }

    public function getCriticalLowThreshold(): ?float
    {
        return $this->criticalLowThreshold;
    }

    public function getColorWarning(): string
    {
        return $this->colorWarning;
    }

    public function getColorCritical(): string
    {
        return $this->colorCritical;
    }
}
