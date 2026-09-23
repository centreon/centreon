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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\Shared\Domain\Collection;

interface InheritedHostMacroRepository
{
    /**
     * Resolves the custom macros a host would inherit from its template chain and from its check
     * command (the `$_HOST<NAME>$` macros a command declares). Used to strip redundant submitted
     * macros before persisting (see HostMacroInheritanceResolver).
     *
     * @param Collection<HostTemplateId> $templateIds the host's template chain, in order
     * @param ?CommandId $checkCommandId the host's check command, if any
     *
     * @return list<HostMacro>
     */
    public function findInheritedMacros(Collection $templateIds, ?CommandId $checkCommandId): array;
}
