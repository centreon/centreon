<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Domain\Common;

use Core\Domain\Exception\InvalidGeoCoordException;

/**
 * This a basic value object which represents a pair of latitude and longitude.
 */
class GeoCoords implements \Stringable
{
    /** @var string -90.0 to +90.0 */
    private const REGEX_LATITUDE = '[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)';

    /** @var string -180.0 to +180.0 */
    private const REGEX_LONGITUDE = '[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)';

    /** @var string Full lat,lng string */
    private const REGEX_FULL = '/^' . self::REGEX_LATITUDE . ',\s*' . self::REGEX_LONGITUDE . '$/';

    /**
     * @param numeric-string $latitude
     * @param numeric-string $longitude
     *
     * @throws InvalidGeoCoordException
     */
    public function __construct(
        public readonly string $latitude,
        public readonly string $longitude,
    ) {
        if (
            ! preg_match(self::REGEX_FULL, $latitude . ',' . $longitude)
        ) {
            throw InvalidGeoCoordException::invalidValues();
        }
    }

    public function __toString(): string
    {
        return $this->latitude . ',' . $this->longitude;
    }

    /**
     * @param string $coords
     *
     * @throws InvalidGeoCoordException
     *
     * @return self
     */
    public static function fromString(string $coords): self
    {
        $parts = explode(',', $coords);

        if (2 !== \count($parts)) {
            throw InvalidGeoCoordException::invalidFormat();
        }

        if (! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            throw InvalidGeoCoordException::invalidValues();
        }

        return new self($parts[0], $parts[1]);
    }
}
