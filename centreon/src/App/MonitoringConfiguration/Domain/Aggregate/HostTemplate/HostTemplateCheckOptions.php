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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\Shared\Domain\Collection;

final readonly class HostTemplateCheckOptions
{
    /** @var list<string> */
    public array $args;

    /** @var Collection<HostTemplateMacro> */
    public Collection $macros;

    /**
     * @param ?CommandId $checkCommandId null when none is set; arguments may still be set
     * @param array<int, string> $args ordered check command arguments
     * @param ?Collection<HostTemplateMacro> $macros the template's own custom macros, in insertion
     *                                               order; possibly lazy
     */
    public function __construct(
        public ?CommandId $checkCommandId = null,
        array $args = [],
        ?Collection $macros = null,
    ) {
        HostTemplateCommandArguments::assertValid($args, 'HostTemplateCheckOptions::args');

        $this->args = array_values($args);
        $this->macros = $macros ?? new Collection([], HostTemplateMacro::class);
    }

    public function hasSameCommandAs(self $other): bool
    {
        return $this->checkCommandId?->value === $other->checkCommandId?->value
            && $this->args === $other->args;
    }
}
