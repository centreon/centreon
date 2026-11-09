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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacroName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\StandardMacroCriteria;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\Shared\Domain\Collection;

final readonly class FakeStandardMacroRepository implements StandardMacroRepository
{
    /** @var Collection<StandardMacro> */
    private Collection $macros;

    /**
     * @param list<string> $names the reserved macro names, in their `$...$` form
     */
    public function __construct(array $names = [])
    {
        $this->macros = new Collection(
            array_map(static fn (string $name): StandardMacro => new StandardMacro(null, new StandardMacroName($name)), $names),
            StandardMacro::class,
        );
    }

    public function findAll(?StandardMacroCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return $this->macros;
    }
}
