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

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a host's extended information (notes, action URL, icon, comment, GPS).
 *
 * Every property can mean three different things, which is why the types read
 * `NoValue|<type>|null`:
 * - `NoValue`: the caller did not provide the field, the stored value stays as it is;
 * - `null`: the caller provided the field empty, the value is cleared;
 * - a value: it replaces the stored one.
 */
final readonly class ExtendedInformationsChanges
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

    public function applyTo(?ExtendedInformations $current): ExtendedInformations
    {
        return ($current ?? new ExtendedInformations())->with(
            noteUrl: $this->noteUrl,
            note: $this->note,
            actionUrl: $this->actionUrl,
            iconId: $this->iconId,
            altIcon: $this->altIcon,
            comment: $this->comment,
            geoCoordinates: $this->geoCoordinates,
        );
    }
}
