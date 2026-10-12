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

namespace Tests\www\class;

use CentreonGMT;

beforeEach(function (): void {
    // The PHP timezone must differ from the platform one to tell both fallbacks apart
    $this->phpTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');
});

afterEach(function (): void {
    date_default_timezone_set($this->phpTimezone);
});

/**
 * Builds a CentreonGMT with an in-memory timezone list instead of the database one.
 */
function createCentreonGmt(string $platformTimezoneId): CentreonGMT
{
    return new class ($platformTimezoneId) extends CentreonGMT {
        public function __construct(string $platformTimezoneId)
        {
            $this->timezoneById = [1 => 'UTC', 2 => 'Europe/Paris', 3 => 'America/New_York'];
            $this->timezones = ['UTC' => 1, 'Europe/Paris' => 2, 'America/New_York' => 3];
            $this->sDefaultTimezone = $platformTimezoneId;
        }
    };
}

it('returns the timezone matching a timezone id', function (): void {
    expect(createCentreonGmt('2')->getActiveTimezone('3'))->toBe('America/New_York');
});

it('returns the timezone matching a timezone name', function (): void {
    expect(createCentreonGmt('2')->getActiveTimezone('America/New_York'))->toBe('America/New_York');
});

it('falls back to the platform timezone when the timezone is unknown', function (): void {
    expect(createCentreonGmt('2')->getActiveTimezone('0'))->toBe('Europe/Paris');
});

it('falls back to the PHP timezone when no platform timezone is configured', function (): void {
    expect(createCentreonGmt('')->getActiveTimezone('0'))->toBe('UTC');
});

it('falls back to the PHP timezone when the platform timezone is unknown', function (): void {
    expect(createCentreonGmt('99')->getActiveTimezone('0'))->toBe('UTC');
});
