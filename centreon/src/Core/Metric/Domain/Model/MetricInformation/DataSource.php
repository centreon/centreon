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

namespace Core\Metric\Domain\Model\MetricInformation;

class DataSource
{
    public function __construct(
        private readonly ?int $minimum,
        private readonly ?int $maximum,
        private readonly ?int $minMax,
        private readonly ?int $lastValue,
        private readonly ?int $averageValue,
        private readonly ?int $total,
        private readonly ?float $transparency,
        private readonly ?string $colorArea,
        private readonly bool $isFilled,
        private readonly bool $isInverted,
        private readonly ?string $legend,
        private readonly bool $isStacked,
        private readonly ?int $order,
        private readonly int $tickness,
        private readonly int $colorMode,
        private readonly string $lineColor
    ) {
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function getMinMax(): ?int
    {
        return $this->minMax;
    }

    public function getLastValue(): ?int
    {
        return $this->lastValue;
    }

    public function getAverageValue(): ?int
    {
        return $this->averageValue;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getTickness(): int
    {
        return $this->tickness;
    }

    public function getColorMode(): int
    {
        return $this->colorMode;
    }

    public function getLineColor(): string
    {
        return $this->lineColor;
    }

    public function getTransparency(): ?float
    {
        return $this->transparency;
    }

    public function getColorArea(): ?string
    {
        return $this->colorArea;
    }

    public function isFilled(): bool
    {
        return $this->isFilled;
    }

    public function isInverted(): bool
    {
        return $this->isInverted;
    }

    public function getLegend(): ?string
    {
        return $this->legend;
    }

    public function isStacked(): bool
    {
        return $this->isStacked;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }
}
