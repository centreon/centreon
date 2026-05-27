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

namespace App\MonitoringConfiguration\Infrastructure\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Service\PollerUidGenerator;
use Godruoyi\Snowflake\Snowflake;

/**
 * Generates unique 64-bit poller identifiers using the Twitter Snowflake algorithm.
 *
 * Snowflake ID structure (64 bits):
 *   [1 bit sign][41 bits timestamp ms][5 bits datacenter][5 bits worker][12 bits sequence]
 *
 * Custom epoch: 2024-01-01T00:00:00Z (1704067200000 ms) — provides ~69 years of unique IDs (until ~2093).
 *
 * @see https://en.wikipedia.org/wiki/Snowflake_ID
 * @see https://github.com/godruoyi/php-snowflake
 */
final readonly class SnowflakePollerUidGenerator implements PollerUidGenerator
{
    /** 2024-01-01T00:00:00Z in milliseconds. */
    public const CUSTOM_EPOCH_MS = 1704067200000;

    private Snowflake $snowflake;

    /**
     * Both IDs default to 0: Centreon runs as a single-instance generator.
     */
    public function __construct(int $datacenterId = 0, int $workerId = 0)
    {
        $this->snowflake = new Snowflake($datacenterId, $workerId);
        $this->snowflake->setStartTimeStamp(self::CUSTOM_EPOCH_MS);
    }

    public function generate(): PollerUid
    {
        return new PollerUid((int) $this->snowflake->id());
    }
}
