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

namespace Core\Dashboard\Application\UseCase\FindPerformanceMetricsData;

use Core\Common\Infrastructure\Validator\DateFormat;
use Symfony\Component\Validator\Constraints as Assert;

final class FindPerformanceMetricsDataRequest
{
    /**
     * @param string $start
     * @param string $end
     * @param string[] $metricNames
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(
            format: DateFormat::ISO8601,
            message: DateFormat::INVALID_DATE_MESSAGE
        )]
        public readonly string $start,
        #[Assert\NotBlank]
        #[Assert\DateTime(
            format: DateFormat::ISO8601,
            message: DateFormat::INVALID_DATE_MESSAGE
        )]
        public readonly string $end,
        #[Assert\NotNull]
        #[Assert\Type('array')]
        #[Assert\All(
            new Assert\Type('string'),
        )]
        public readonly array $metricNames,
    ) {
    }

    /**
     * @return FindPerformanceMetricsDataRequestDto
     */
    public function toDto(): FindPerformanceMetricsDataRequestDto
    {
        return new FindPerformanceMetricsDataRequestDto(
            startDate: new \DateTimeImmutable($this->start),
            endDate: new \DateTimeImmutable($this->end),
            metricNames: $this->sanitizeMetricNames()
        );
    }

    /**
     * @return string[]
     */
    private function sanitizeMetricNames(): array
    {
        return array_map(
            static fn (string $metricName): string => \trim($metricName, '"'),
            $this->metricNames
        );
    }
}
