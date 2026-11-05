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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\Shared\Domain\NoValue;

/**
 * NoValue keeps the current value, null clears it.
 */
final readonly class HostTemplateExtendedInformationsChange
{
    public function __construct(
        public NoValue|string|null $noteUrl = new NoValue(),
        public NoValue|string|null $note = new NoValue(),
        public NoValue|string|null $actionUrl = new NoValue(),
        public NoValue|MediaId|null $iconId = new NoValue(),
        public NoValue|string|null $altIcon = new NoValue(),
        public NoValue|string|null $comment = new NoValue(),
    ) {
    }

    /**
     * Null when every member ends up empty.
     */
    public function applyTo(?HostTemplateExtendedInformations $current): ?HostTemplateExtendedInformations
    {
        $informations = new HostTemplateExtendedInformations(
            noteUrl: NoValue::resolve($this->noteUrl, $current?->noteUrl),
            note: NoValue::resolve($this->note, $current?->note),
            actionUrl: NoValue::resolve($this->actionUrl, $current?->actionUrl),
            iconId: NoValue::resolve($this->iconId, $current?->iconId),
            altIcon: NoValue::resolve($this->altIcon, $current?->altIcon),
            comment: NoValue::resolve($this->comment, $current?->comment),
        );

        return $informations->isEmpty() ? null : $informations;
    }
}
