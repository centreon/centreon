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

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use PHPUnit\Framework\TestCase;

final class ExtendedInformationsTest extends TestCase
{
    public function testItAcceptsAllFieldsLeftEmpty(): void
    {
        $extendedInformations = new ExtendedInformations();

        self::assertNull($extendedInformations->noteUrl);
        self::assertNull($extendedInformations->note);
        self::assertNull($extendedInformations->actionUrl);
        self::assertNull($extendedInformations->iconId);
        self::assertNull($extendedInformations->altIcon);
        self::assertNull($extendedInformations->comment);
        self::assertNull($extendedInformations->geoCoordinates);
    }

    public function testItAcceptsAllFieldsFilled(): void
    {
        $iconId = new MediaId(42);

        $extendedInformations = new ExtendedInformations(
            noteUrl: 'https://example.com/notes',
            note: 'a free-text note',
            actionUrl: 'https://example.com/actions',
            iconId: $iconId,
            altIcon: 'server icon',
            comment: 'internal comment',
        );

        self::assertSame('https://example.com/notes', $extendedInformations->noteUrl);
        self::assertSame('a free-text note', $extendedInformations->note);
        self::assertSame('https://example.com/actions', $extendedInformations->actionUrl);
        self::assertSame($iconId, $extendedInformations->iconId);
        self::assertSame('server icon', $extendedInformations->altIcon);
        self::assertSame('internal comment', $extendedInformations->comment);
    }

    public function testItTrimsSurroundingWhitespace(): void
    {
        $extendedInformations = new ExtendedInformations(
            noteUrl: '  https://example.com/notes  ',
            note: '  a free-text note  ',
            actionUrl: '  https://example.com/actions  ',
            altIcon: '  server icon  ',
            comment: '  internal comment  ',
        );

        self::assertSame('https://example.com/notes', $extendedInformations->noteUrl);
        self::assertSame('a free-text note', $extendedInformations->note);
        self::assertSame('https://example.com/actions', $extendedInformations->actionUrl);
        self::assertSame('server icon', $extendedInformations->altIcon);
        self::assertSame('internal comment', $extendedInformations->comment);
    }

    public function testItRejectsAWhitespaceOnlyNote(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedInformations(note: '   ');
    }

    public function testItRejectsANoteLongerThan512Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedInformations(note: str_repeat('a', 513));
    }

    public function testItAcceptsANoteExactly512CharactersLong(): void
    {
        $extendedInformations = new ExtendedInformations(note: str_repeat('a', 512));

        self::assertSame(512, mb_strlen((string) $extendedInformations->note));
    }

    public function testItRejectsANoteUrlLongerThan2048Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedInformations(noteUrl: str_repeat('a', 2049));
    }

    public function testItRejectsAnActionUrlLongerThan2048Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedInformations(actionUrl: str_repeat('a', 2049));
    }

    public function testItRejectsAnAltIconLongerThan200Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedInformations(altIcon: str_repeat('a', 201));
    }

    public function testWithKeepsEveryValueWhenNothingIsProvided(): void
    {
        $original = new ExtendedInformations('https://a.example', 'note', 'https://b.example', new MediaId(2), 'icon', 'comment', new GeoCoordinates('48.85', '2.35'));

        self::assertTrue($original->equals($original->with()));
    }

    public function testWithReplacesOnlyTheProvidedValues(): void
    {
        $original = new ExtendedInformations(note: 'note', comment: 'comment');

        $changed = $original->with(note: 'other', geoCoordinates: new GeoCoordinates('1', '2'));

        self::assertSame('other', $changed->note);
        self::assertSame('comment', $changed->comment);
        self::assertSame('1', $changed->geoCoordinates?->latitude);
    }

    public function testWithClearsAValueSetToNull(): void
    {
        $original = new ExtendedInformations(note: 'note', iconId: new MediaId(2), geoCoordinates: new GeoCoordinates('1', '2'));

        $changed = $original->with(note: null, iconId: null, geoCoordinates: null);

        self::assertNull($changed->note);
        self::assertNull($changed->iconId);
        self::assertNull($changed->geoCoordinates);
    }

    public function testWithStillEnforcesTheInvariants(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ExtendedInformations())->with(altIcon: str_repeat('a', ExtendedInformations::MAX_ALT_ICON_LENGTH + 1));
    }

    public function testEqualsComparesTheGeoCoordinates(): void
    {
        $informations = new ExtendedInformations(geoCoordinates: new GeoCoordinates('1', '2'));

        self::assertTrue($informations->equals(new ExtendedInformations(geoCoordinates: new GeoCoordinates('1', '2'))));
        self::assertFalse($informations->equals(new ExtendedInformations(geoCoordinates: new GeoCoordinates('1', '3'))));
        self::assertFalse($informations->equals(new ExtendedInformations()));
    }
}
