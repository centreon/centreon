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

namespace App\ResourceConfiguration\Domain\Collection;

use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro;

class GlobalMacroCollection implements \IteratorAggregate, \Countable
{
    /** @var GlobalMacro[] */
    private array $globalMacros = [];

    public function __construct(array $globalMacros = [])
    {
        foreach ($globalMacros as $globalMacro) {
            $this->add($globalMacro);
        }
    }

    public function add(GlobalMacro $macro): void
    {
        $this->globalMacros[] = $macro;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->globalMacros);
    }

    public function count(): int
    {
        return count($this->globalMacros);
    }
}
