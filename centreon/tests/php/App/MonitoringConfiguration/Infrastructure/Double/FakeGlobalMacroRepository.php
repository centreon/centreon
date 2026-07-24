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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GlobalMacroCriteria;
use App\MonitoringConfiguration\Domain\Repository\GlobalMacroRepository;
use App\Shared\Domain\Collection;

final class FakeGlobalMacroRepository implements GlobalMacroRepository
{
    /** @var Collection<GlobalMacro> */
    private readonly Collection $macros;

    /**
     * @param GlobalMacro[] $macros
     */
    public function __construct(array $macros = [])
    {
        $this->macros = new Collection($macros, GlobalMacro::class);
    }

    public function findAll(?GlobalMacroCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return $this->macros;
    }
}
