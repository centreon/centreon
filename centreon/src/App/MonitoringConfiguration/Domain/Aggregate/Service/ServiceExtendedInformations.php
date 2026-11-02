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

use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use Webmozart\Assert\Assert;

/**
 * The service's notes, URLs, icon, comment and geographic coordinates (legacy `extended_service_information`
 * table, plus `service.service_comment` / `service.geo_coords`).
 *
 * Its invariant is the storage bound of each column, not the form's bounds: a longer text stored by legacy is a
 * valid value, read and returned as it is. The form bounds are write rules, checked on create and update.
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
     * The texts longer than the form allows, which a create or an update must refuse even though they fit their
     * column. The alternative text of the icon and the comment have no form bound below their column's.
     *
     * @return list<string> the names of the fields above their form bound
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

    private function text(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        Assert::notEmpty($value, 'ServiceExtendedInformations::' . $field . ' must not be blank.');
        // Byte length ('8bit'): the TEXT column is bounded in bytes, so a multibyte text is measured in the
        // bytes it occupies.
        Assert::lessThanEq(
            mb_strlen($value, '8bit'),
            self::MAX_TEXT_BYTES,
            'ServiceExtendedInformations::' . $field . ' is too long.',
        );

        return $value;
    }
}
