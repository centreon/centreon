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

namespace Core\Metric\Domain\Model;

class Metric
{
    private ?string $unit = null;

    private ?float $currentValue = null;

    private ?float $warningHighThreshold = null;

    private ?float $warningLowThreshold = null;

    private ?float $criticalHighThreshold = null;

    private ?float $criticalLowThreshold = null;

    /**
     * @param int $id
     * @param string $name
     */
    public function __construct(private int $id, private string $name)
    {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setCurrentValue(?float $currentValue): self
    {
        $this->currentValue = $currentValue;

        return $this;
    }

    public function getCurrentValue(): ?float
    {
        return $this->currentValue;
    }

    public function setWarningHighThreshold(?float $warningHighThreshold): self
    {
        $this->warningHighThreshold = $warningHighThreshold;

        return $this;
    }

    public function getWarningHighThreshold(): ?float
    {
        return $this->warningHighThreshold;
    }

    public function setWarningLowThreshold(?float $warningLowThreshold): self
    {
        $this->warningLowThreshold = $warningLowThreshold;

        return $this;
    }

    public function getWarningLowThreshold(): ?float
    {
        return $this->warningLowThreshold;
    }

    public function setCriticalHighThreshold(?float $criticalHighThreshold): self
    {
        $this->criticalHighThreshold = $criticalHighThreshold;

        return $this;
    }

    public function getCriticalHighThreshold(): ?float
    {
        return $this->criticalHighThreshold;
    }

    public function setCriticalLowThreshold(?float $criticalLowThreshold): self
    {
        $this->criticalLowThreshold = $criticalLowThreshold;

        return $this;
    }

    public function getCriticalLowThreshold(): ?float
    {
        return $this->criticalLowThreshold;
    }
}
