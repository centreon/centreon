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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceExtendedInformationsTest extends TestCase
{
    public function testItAcceptsAllFieldsLeftEmpty(): void
    {
        $informations = new ServiceExtendedInformations();

        self::assertNull($informations->noteUrl);
        self::assertNull($informations->note);
        self::assertNull($informations->actionUrl);
        self::assertNull($informations->iconId);
        self::assertNull($informations->altIcon);
        self::assertNull($informations->comment);
        self::assertNull($informations->geoCoordinates);
    }

    public function testItAcceptsAllFieldsFilled(): void
    {
        $informations = new ServiceExtendedInformations(
            noteUrl: 'https://example.com/notes',
            note: 'A note',
            actionUrl: 'https://example.com/action',
            iconId: new MediaId(5),
            altIcon: 'An icon',
            comment: 'A comment',
            geoCoordinates: new GeoCoordinates('48.85', '2.35'),
        );

        self::assertSame('https://example.com/notes', $informations->noteUrl);
        self::assertSame('A note', $informations->note);
        self::assertSame('https://example.com/action', $informations->actionUrl);
        self::assertSame(5, $informations->iconId?->value);
        self::assertSame('An icon', $informations->altIcon);
        self::assertSame('A comment', $informations->comment);
        self::assertSame('48.85,2.35', (string) $informations->geoCoordinates);
    }

    public function testItTrimsSurroundingWhitespace(): void
    {
        $informations = new ServiceExtendedInformations(
            noteUrl: ' https://example.com ',
            note: ' note ',
            actionUrl: ' https://example.com/a ',
            altIcon: ' alt ',
            comment: ' comment ',
        );

        self::assertSame('https://example.com', $informations->noteUrl);
        self::assertSame('note', $informations->note);
        self::assertSame('https://example.com/a', $informations->actionUrl);
        self::assertSame('alt', $informations->altIcon);
        self::assertSame('comment', $informations->comment);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function textFieldProvider(): iterable
    {
        yield 'note url' => ['noteUrl'];

        yield 'note' => ['note'];

        yield 'action url' => ['actionUrl'];

        yield 'alt icon' => ['altIcon'];

        yield 'comment' => ['comment'];
    }

    #[DataProvider('textFieldProvider')]
    public function testItRejectsAWhitespaceOnlyText(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceExtendedInformations(...[$field => '   ']);
    }

    #[DataProvider('textFieldProvider')]
    public function testItAcceptsAZeroText(string $field): void
    {
        $informations = new ServiceExtendedInformations(...[$field => '0']);

        self::assertSame('0', $informations->{$field});
    }

    /**
     * The form bound of a note (512 characters) is a write rule: a longer note stored by legacy is still a
     * valid value, read and returned as it is.
     */
    public function testItAcceptsAStoredNoteAboveTheFormBound(): void
    {
        $informations = new ServiceExtendedInformations(
            noteUrl: str_repeat('u', 3000),
            note: str_repeat('n', 600),
            actionUrl: str_repeat('a', 3000),
        );

        self::assertSame(600, mb_strlen((string) $informations->note));
        self::assertSame(3000, mb_strlen((string) $informations->noteUrl));
        self::assertSame(3000, mb_strlen((string) $informations->actionUrl));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function textColumnProvider(): iterable
    {
        yield 'note url' => ['noteUrl'];

        yield 'note' => ['note'];

        yield 'action url' => ['actionUrl'];

        yield 'comment' => ['comment'];
    }

    #[DataProvider('textColumnProvider')]
    public function testItAcceptsATextFillingTheColumn(string $field): void
    {
        $informations = new ServiceExtendedInformations(...[$field => str_repeat('a', 65535)]);

        $stored = match ($field) {
            'noteUrl' => $informations->noteUrl,
            'note' => $informations->note,
            'actionUrl' => $informations->actionUrl,
            default => $informations->comment,
        };
        self::assertSame(65535, mb_strlen((string) $stored, '8bit'));
    }

    #[DataProvider('textColumnProvider')]
    public function testItRejectsATextAboveTheColumn(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceExtendedInformations(...[$field => str_repeat('a', 65536)]);
    }

    /**
     * The TEXT columns are bounded in bytes: 32768 two-byte characters do not fit.
     */
    #[DataProvider('textColumnProvider')]
    public function testItMeasuresATextInBytes(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceExtendedInformations(...[$field => str_repeat('é', 32768)]);
    }

    public function testItAcceptsAnAltIconFillingTheColumn(): void
    {
        $informations = new ServiceExtendedInformations(altIcon: str_repeat('é', 200));

        self::assertSame(200, mb_strlen((string) $informations->altIcon));
    }

    public function testItRejectsAnAltIconLongerThanTheColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceExtendedInformations(altIcon: str_repeat('a', 201));
    }
}
