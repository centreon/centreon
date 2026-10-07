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

use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * One macro as submitted on a host write. It refers to the macro it changes by id + parent (a null
 * id is a new macro) and carries the target state; {@see \App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver}
 * turns it into the direct macro to persist.
 *
 * A null value means "keep the stored value". It is only meaningful for an existing password macro,
 * whose value is never echoed back; an empty string is an explicit value.
 */
final readonly class HostMacroChange
{
    public function __construct(
        public HostMacroName $name,
        // Masked in logs: may carry a password macro's plaintext.
        #[Sensitive] public ?string $value,
        public bool $isPassword,
        public ?HostMacroId $id = null,
        public ?HostMacroParentEnum $parent = null,
    ) {
        if ($value === null) {
            Assert::notNull($id, 'A new macro needs a value.');
            Assert::true($isPassword, 'Only a password macro can keep its stored value.');
        } else {
            Assert::maxLength($value, HostMacro::MAX_VALUE_LENGTH);
        }
    }

    public function isNew(): bool
    {
        return ! $this->id instanceof HostMacroId;
    }

    public function keepsStoredValue(): bool
    {
        return $this->value === null;
    }
}
