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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * @extends AggregateRoot<HostTemplateId>
 */
final class HostTemplate extends AggregateRoot
{
    /** @var Collection<HostMacro> */
    public readonly Collection $macros;

    /**
     * @param ?Collection<HostMacro> $macros the template's own custom macros, in `macro_order`;
     *                                       possibly lazy
     */
    public function __construct(
        ?HostTemplateId $id,
        public readonly HostTemplateName $name,
        ?Collection $macros = null,
    ) {
        parent::__construct($id);
        $this->macros = $macros ?? new Collection([], HostMacro::class);
    }

    /**
     * The template's own macros, as a host inheriting from it sees them.
     *
     * @return list<HostMacro>
     */
    public function macrosAsInherited(): array
    {
        return array_values(array_map(
            static function (HostMacro $macro): HostMacro {
                // A template only ever owns direct macros: what it inherits itself is resolved
                // along the inheritance line, never stored on it.
                Assert::true($macro->isDirect(), 'A host template only owns direct macros.');

                return $macro->inheritedFrom(HostMacroParentEnum::Template);
            },
            $this->macros->toArray(),
        ));
    }
}
