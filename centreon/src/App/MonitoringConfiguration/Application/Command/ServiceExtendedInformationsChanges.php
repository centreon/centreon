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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a service's notes, URLs, icon, comment and geographic coordinates.
 *
 * - `NoValue`: the field is not provided, the stored value stays as it is;
 * - `null`: the value is cleared;
 * - a value: it replaces the stored one.
 */
final readonly class ServiceExtendedInformationsChanges
{
    public function __construct(
        public NoValue|string|null $noteUrl = new NoValue(),
        public NoValue|string|null $note = new NoValue(),
        public NoValue|string|null $actionUrl = new NoValue(),
        public NoValue|MediaId|null $iconId = new NoValue(),
        public NoValue|string|null $altIcon = new NoValue(),
        public NoValue|string|null $comment = new NoValue(),
        public NoValue|GeoCoordinates|null $geoCoordinates = new NoValue(),
    ) {
    }

    public function applyTo(?ServiceExtendedInformations $current): ServiceExtendedInformations
    {
        $base = $current ?? new ServiceExtendedInformations();

        return new ServiceExtendedInformations(
            noteUrl: NoValue::resolve($this->noteUrl, $base->noteUrl),
            note: NoValue::resolve($this->note, $base->note),
            actionUrl: NoValue::resolve($this->actionUrl, $base->actionUrl),
            iconId: NoValue::resolve($this->iconId, $base->iconId),
            altIcon: NoValue::resolve($this->altIcon, $base->altIcon),
            comment: NoValue::resolve($this->comment, $base->comment),
            geoCoordinates: NoValue::resolve($this->geoCoordinates, $base->geoCoordinates),
        );
    }

    /**
     * The names of the texts this change provides, as {@see ServiceExtendedInformations::exceedsFormBounds()} names
     * them.
     *
     * @return list<string>
     */
    public function providedTexts(): array
    {
        return array_keys(array_filter(
            ['noteUrl' => $this->noteUrl, 'note' => $this->note, 'actionUrl' => $this->actionUrl],
            static fn (NoValue|string|null $value): bool => ! $value instanceof NoValue,
        ));
    }
}
