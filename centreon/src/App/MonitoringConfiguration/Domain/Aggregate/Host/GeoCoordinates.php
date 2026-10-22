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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use Webmozart\Assert\Assert;

/**
 * A `latitude,longitude` pair used by Centreon MAP to place a host on a geographic view.
 */
final readonly class GeoCoordinates implements \Stringable
{
    private const REGEX_LATITUDE = '/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)$/';
    private const REGEX_LONGITUDE = '/^[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/';
    private const MAX_DECIMALS = 6;

    public function __construct(
        public string $latitude,
        public string $longitude,
    ) {
        Assert::regex($this->latitude, self::REGEX_LATITUDE, 'GeoCoordinates::latitude must be between -90 and 90.');
        Assert::regex($this->longitude, self::REGEX_LONGITUDE, 'GeoCoordinates::longitude must be between -180 and 180.');
    }

    public function __toString(): string
    {
        return sprintf('%s,%s', $this->latitude, $this->longitude);
    }

    /**
     * Parses a raw `"latitude,longitude"` string, truncating each part to
     * {@see self::MAX_DECIMALS} decimals before validating it.
     */
    public static function fromString(string $value): self
    {
        $parts = explode(',', trim($value));
        Assert::count($parts, 2, 'GeoCoordinates value must be a "latitude,longitude" pair.');

        [$latitude, $longitude] = array_map(trim(...), $parts);

        return new self(self::truncateDecimals($latitude), self::truncateDecimals($longitude));
    }

    private static function truncateDecimals(string $value): string
    {
        $dotPosition = mb_strpos($value, '.');
        if ($dotPosition === false) {
            return $value;
        }

        return mb_substr($value, 0, $dotPosition + 1 + self::MAX_DECIMALS);
    }
}
