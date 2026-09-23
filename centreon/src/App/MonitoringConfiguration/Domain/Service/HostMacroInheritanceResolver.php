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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;

/**
 * Keeps only the macros a host genuinely overrides or owns, dropping those that merely repeat an
 * inherited definition — mirroring legacy CentreonHost::hasMacroFromHostChanged(): a submitted
 * macro is discarded when its name, value and password flag are all identical to a macro inherited
 * from the host's template chain or its check command, so the host relies on inheritance at runtime
 * instead of storing a redundant copy.
 *
 * Names are matched through HostMacroName's normalized (upper-cased) value.
 */
final readonly class HostMacroInheritanceResolver
{
    /**
     * @param list<HostMacro> $submitted the macros the client sent
     * @param list<HostMacro> $inherited the macros resolved from templates + the check command
     *
     * @return list<HostMacro>
     */
    public function keepOverridesOnly(array $submitted, array $inherited): array
    {
        $inheritedByName = [];
        foreach ($inherited as $macro) {
            $inheritedByName[$macro->name->value] = $macro;
        }

        return array_values(array_filter(
            $submitted,
            static function (HostMacro $macro) use ($inheritedByName): bool {
                $inheritedMacro = $inheritedByName[$macro->name->value] ?? null;

                // Own macro (nothing to inherit from) → keep; otherwise keep only if it differs.
                return $inheritedMacro === null
                    || $inheritedMacro->value !== $macro->value
                    || $inheritedMacro->isPassword !== $macro->isPassword;
            },
        ));
    }
}
