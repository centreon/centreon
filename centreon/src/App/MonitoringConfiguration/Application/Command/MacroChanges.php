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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;

/**
 * How a partial update changes the custom macros of a host: it replaces them all, writes some of
 * them, or removes some of them.
 *
 * - a replacement and a write carry the macros as submitted, each one pointing to the macro it
 *   changes by id and parent, or being new when it has no id. A write also takes a macro without id as
 *   the update of the host's own macro of the same name, when there is one;
 * - a removal carries the ids of the host's own macros.
 */
final readonly class MacroChanges
{
    /**
     * @param list<HostMacroChange> $changes
     * @param list<HostMacroId> $removedIds
     */
    private function __construct(
        public MacroChangeModeEnum $mode,
        public array $changes,
        public array $removedIds,
    ) {
    }

    /**
     * @param list<HostMacroChange> $changes
     */
    public static function replace(array $changes): self
    {
        return new self(MacroChangeModeEnum::Replace, $changes, []);
    }

    /**
     * @param list<HostMacroChange> $changes
     */
    public static function upsert(array $changes): self
    {
        return new self(MacroChangeModeEnum::Upsert, $changes, []);
    }

    /**
     * @param list<HostMacroId> $ids
     */
    public static function remove(array $ids): self
    {
        return new self(MacroChangeModeEnum::Remove, [], $ids);
    }
}
