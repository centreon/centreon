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

namespace Core\Metric\Domain\Model;

/**
 * Data source type of a metric (centreon_storage.metrics.data_source_type), as in RRD: it decides
 * how the raw values stored in a time series database become the values of a graph.
 */
enum MetricDataSourceType: string
{
    /** Value as measured, averaged. */
    case Gauge = '0';

    /** Increasing counter: per-second rate, never negative. */
    case Counter = '1';

    /** Counter that may decrease: per-second rate, may be negative. */
    case Derive = '2';

    /** Counter reset at each read: value divided by the time since the previous one. */
    case Absolute = '3';

    /**
     * NULL and unknown values are gauges, as Broker writes them.
     */
    public static function fromColumn(int|string|null $value): self
    {
        return $value === null ? self::Gauge : (self::tryFrom((string) $value) ?? self::Gauge);
    }
}
