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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\Shared\Domain\NoValue;
use Webmozart\Assert\Assert;

/**
 * Groups the host's "Extended Informations" fields (legacy `extended_host_information` table,
 * plus `host.geo_coords`/`host.host_comment`) behind a single VO instead of a growing list of
 * scalar properties directly on {@see Host}.
 *
 * `altIcon` and `comment` are on-prem only (legacy builds them for Cloud too but never renders
 * them there — see MON-208990/MON-208474).
 */
final readonly class ExtendedInformations
{
    public const MIN_NOTE_LENGTH = 1;
    public const MAX_NOTE_LENGTH = 512;
    public const MIN_NOTE_URL_LENGTH = 1;
    public const MAX_NOTE_URL_LENGTH = 2048;
    public const MIN_ACTION_URL_LENGTH = 1;
    public const MAX_ACTION_URL_LENGTH = 2048;
    public const MIN_ALT_ICON_LENGTH = 1;
    public const MAX_ALT_ICON_LENGTH = 200;
    public const MIN_COMMENT_LENGTH = 1;
    public const MAX_COMMENT_LENGTH = 65535;

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
        $noteUrl = $noteUrl !== null ? trim($noteUrl) : null;
        Assert::nullOrLengthBetween($noteUrl, self::MIN_NOTE_URL_LENGTH, self::MAX_NOTE_URL_LENGTH);
        $this->noteUrl = $noteUrl;

        $note = $note !== null ? trim($note) : null;
        Assert::nullOrLengthBetween($note, self::MIN_NOTE_LENGTH, self::MAX_NOTE_LENGTH);
        $this->note = $note;

        $actionUrl = $actionUrl !== null ? trim($actionUrl) : null;
        Assert::nullOrLengthBetween($actionUrl, self::MIN_ACTION_URL_LENGTH, self::MAX_ACTION_URL_LENGTH);
        $this->actionUrl = $actionUrl;

        $altIcon = $altIcon !== null ? trim($altIcon) : null;
        Assert::nullOrLengthBetween($altIcon, self::MIN_ALT_ICON_LENGTH, self::MAX_ALT_ICON_LENGTH);
        $this->altIcon = $altIcon;

        $comment = $comment !== null ? trim($comment) : null;
        Assert::nullOrLengthBetween($comment, self::MIN_COMMENT_LENGTH, self::MAX_COMMENT_LENGTH);
        $this->comment = $comment;
    }

    public function with(
        NoValue|string|null $noteUrl = new NoValue(),
        NoValue|string|null $note = new NoValue(),
        NoValue|string|null $actionUrl = new NoValue(),
        NoValue|MediaId|null $iconId = new NoValue(),
        NoValue|string|null $altIcon = new NoValue(),
        NoValue|string|null $comment = new NoValue(),
        NoValue|GeoCoordinates|null $geoCoordinates = new NoValue(),
    ): self {
        return new self(
            noteUrl: NoValue::resolve($noteUrl, $this->noteUrl),
            note: NoValue::resolve($note, $this->note),
            actionUrl: NoValue::resolve($actionUrl, $this->actionUrl),
            iconId: NoValue::resolve($iconId, $this->iconId),
            altIcon: NoValue::resolve($altIcon, $this->altIcon),
            comment: NoValue::resolve($comment, $this->comment),
            geoCoordinates: NoValue::resolve($geoCoordinates, $this->geoCoordinates),
        );
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
}
