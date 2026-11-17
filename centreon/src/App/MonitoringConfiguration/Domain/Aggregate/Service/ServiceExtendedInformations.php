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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use Webmozart\Assert\Assert;

/**
 * The service's notes, URLs, icon, comment and coordinates, bounded by column size, not by the form.
 */
final readonly class ServiceExtendedInformations
{
    /** Bytes of a TEXT column (`esi_notes`, `esi_notes_url`, `esi_action_url`, `service_comment`). */
    public const MAX_TEXT_BYTES = 65535;

    /** Characters of `esi_icon_image_alt` (varchar). */
    public const MAX_ALT_ICON_LENGTH = 200;

    /** Form bound of a written note, in characters; see {@see self::exceedsFormBounds()}. */
    public const MAX_NOTE_LENGTH = 512;

    /** Form bound of a written note URL, in characters. */
    public const MAX_NOTE_URL_LENGTH = 2048;

    /** Form bound of a written action URL, in characters. */
    public const MAX_ACTION_URL_LENGTH = 2048;

    public ?string $noteUrl;

    public ?string $note;

    public ?string $actionUrl;

    public ?string $altIcon;

    public ?string $comment;

    public function __construct(
        ?string $noteUrl = null,
        ?string $note = null,
        ?string $actionUrl = null,
        public ?MediaId $iconId = null,
        ?string $altIcon = null,
        ?string $comment = null,
        public ?GeoCoordinates $geoCoordinates = null,
    ) {
        $this->noteUrl = $this->text($noteUrl, 'noteUrl');
        $this->note = $this->text($note, 'note');
        $this->actionUrl = $this->text($actionUrl, 'actionUrl');
        $this->comment = $this->text($comment, 'comment');

        $altIcon = $altIcon !== null ? trim($altIcon) : null;
        Assert::nullOrLengthBetween($altIcon, 1, self::MAX_ALT_ICON_LENGTH, 'ServiceExtendedInformations::altIcon must be 1 to %3$s characters long.');
        $this->altIcon = $altIcon;
    }

    /**
     * The fields over their form bound, which a write refuses though they fit their column. The alternative text
     * of the icon and the comment have none.
     *
     * @return list<string>
     */
    public function exceedsFormBounds(): array
    {
        $bounds = [
            'noteUrl' => [$this->noteUrl, self::MAX_NOTE_URL_LENGTH],
            'note' => [$this->note, self::MAX_NOTE_LENGTH],
            'actionUrl' => [$this->actionUrl, self::MAX_ACTION_URL_LENGTH],
        ];

        $fields = [];
        foreach ($bounds as $field => [$value, $maxLength]) {
            if ($value !== null && mb_strlen($value) > $maxLength) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function equals(self $other): bool
    {
        return $this->noteUrl === $other->noteUrl
            && $this->note === $other->note
            && $this->actionUrl === $other->actionUrl
            && $this->iconId?->value === $other->iconId?->value
            && $this->altIcon === $other->altIcon
            && $this->comment === $other->comment
            && $this->geoCoordinates?->latitude === $other->geoCoordinates?->latitude
            && $this->geoCoordinates?->longitude === $other->geoCoordinates?->longitude;
    }

    private function text(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        Assert::stringNotEmpty($value, 'ServiceExtendedInformations::' . $field . ' must not be blank.');
        // TEXT is bounded in bytes.
        Assert::lessThanEq(
            mb_strlen($value, '8bit'),
            self::MAX_TEXT_BYTES,
            'ServiceExtendedInformations::' . $field . ' is too long.',
        );

        return $value;
    }
}
