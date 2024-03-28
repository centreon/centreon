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

use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;

class RealTimeDataInformation
{
    /**
     * @param array<float|null> $values
     * @param array<array<string>> $labels
     * @param float|null $minimumValueLimit
     * @param float|null $maximumValueLimit
     * @param float|null $minimumValue float handled as string to not lose decimals on *.O value
     * @param float|null $maximumValue float handled as string to not lose decimals on *.O value
     * @param float|null $lastValue
     * @param float|null $averageValue
     *
     * @throws AssertionException
     */
    public function __construct(
        private readonly array $values,
        private readonly array $labels,
        private readonly ?float $minimumValueLimit,
        private readonly ?float $maximumValueLimit,
        private readonly ?float $minimumValue,
        private readonly ?float $maximumValue,
        private readonly ?float $lastValue,
        private readonly ?float $averageValue,
    ) {
        Assertion::arrayOfTypeOrNull('float',  $values, 'values');
        foreach ($labels as $label) {
            Assertion::arrayOfTypeOrNull('string', $label, 'labels');
        }
    }

    /**
     * @return array<float|null>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return array<array<string>>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getMinimumValueLimit(): ?float
    {
        return $this->minimumValueLimit;
    }

    public function getMaximumValueLimit(): ?float
    {
        return $this->maximumValueLimit;
    }

    public function getMinimumValue(): ?float
    {
        return $this->minimumValue;
    }

    public function getMaximumValue(): ?float
    {
        return $this->maximumValue;
    }

    public function getLastValue(): ?float
    {
        return $this->lastValue;
    }

    public function getAverageValue(): ?float
    {
        return $this->averageValue;
    }
}
