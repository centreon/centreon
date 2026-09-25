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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Infrastructure\Validator\ReservedMacroName;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HostMacroInput
{
    public function __construct(
        // The short name (e.g. "MYMACRO"); the domain wraps it to $_HOST<NAME>$. Length is bounded so
        // the wrapped form fits on_demand_macro_host.host_macro_name, matching HostMacroName's own limit.
        // No character-set rule: legacy only upper-cases and stores the name (its sole check is the
        // reserved-name one below), so the endpoint stays as permissive. Only blankness, storage width
        // and reserved names are enforced.
        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Length(max: HostMacroName::MAX_STORAGE_LENGTH - 7, normalizer: 'trim'),
            new ReservedMacroName(),
        ])]
        public string $name,

        // Masked in logs: may carry a password macro's plaintext (see HostMacro::$value).
        #[Sensitive]
        #[Assert\Length(max: HostMacro::MAX_VALUE_LENGTH)]
        public string $value = '',

        public bool $isPassword = false,

        // Counted in bytes, not characters: on_demand_macro_host.description is a MySQL TEXT column
        // bounded to 65,535 bytes, so a multibyte description must be measured as the bytes it occupies
        // to reject it with a 422 instead of failing at insertion time.
        #[Assert\Length(max: HostMacro::MAX_DESCRIPTION_LENGTH, countUnits: Assert\Length::COUNT_BYTES)]
        public ?string $description = null,
    ) {
    }
}
