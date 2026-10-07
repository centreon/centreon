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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use PHPUnit\Framework\TestCase;

final class GeoCoordinatesTest extends TestCase
{
    public function testItParsesAValidPair(): void
    {
        $coordinates = GeoCoordinates::fromString('48.8566,2.3522');

        self::assertSame('48.8566', $coordinates->latitude);
        self::assertSame('2.3522', $coordinates->longitude);
    }

    public function testItStringifiesBackToTheWireFormat(): void
    {
        $coordinates = GeoCoordinates::fromString('48.8566,2.3522');

        self::assertSame('48.8566,2.3522', (string) $coordinates);
    }

    public function testItTrimsSpacesAroundEachPart(): void
    {
        $coordinates = GeoCoordinates::fromString(' 48.8566 , 2.3522 ');

        self::assertSame('48.8566', $coordinates->latitude);
        self::assertSame('2.3522', $coordinates->longitude);
    }

    public function testItTruncatesDecimalsBeyondSixDigits(): void
    {
        $coordinates = GeoCoordinates::fromString('48.856614159,2.352222222');

        self::assertSame('48.856614', $coordinates->latitude);
        self::assertSame('2.352222', $coordinates->longitude);
    }

    public function testItAcceptsTheExtremeValidValues(): void
    {
        $coordinates = GeoCoordinates::fromString('-90,-180');
        self::assertSame('-90', $coordinates->latitude);
        self::assertSame('-180', $coordinates->longitude);

        $coordinates = GeoCoordinates::fromString('90,180');
        self::assertSame('90', $coordinates->latitude);
        self::assertSame('180', $coordinates->longitude);
    }

    public function testItRejectsALatitudeAbove90(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GeoCoordinates::fromString('90.1,2.3522');
    }

    public function testItRejectsALongitudeAbove180(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GeoCoordinates::fromString('48.8566,180.1');
    }

    public function testItRejectsAValueMissingTheLongitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GeoCoordinates::fromString('48.8566');
    }

    public function testItRejectsANonNumericValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GeoCoordinates::fromString('not,valid');
    }
}
