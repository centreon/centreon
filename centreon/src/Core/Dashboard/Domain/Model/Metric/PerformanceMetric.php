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

namespace Core\Dashboard\Domain\Model\Metric;

class PerformanceMetric
{
    public function __construct(
        private int $id,
        private string $name,
        private string $unit,
        private ?float $warningHighThreshold,
        private ?float $criticalHighThreshold,
        private ?float $warningLowThreshold,
        private ?float $criticalLowThreshold,
        private ?float $currentValue,
        private ?float $minimumValue,
        private ?float $maximumValue
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getWarningHighThreshold(): ?float
    {
        return $this->warningHighThreshold;
    }

    public function getCriticalHighThreshold(): ?float
    {
        return $this->criticalHighThreshold;
    }

    public function getWarningLowThreshold(): ?float
    {
        return $this->warningLowThreshold;
    }

    public function getCriticalLowThreshold(): ?float
    {
        return $this->criticalLowThreshold;
    }

    public function getCurrentValue(): ?float
    {
        return $this->currentValue;
    }

    public function getMinimumValue(): ?float
    {
        return $this->minimumValue;
    }

    public function getMaximumValue(): ?float
    {
        return $this->maximumValue;
    }
}
