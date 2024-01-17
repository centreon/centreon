<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Common\Infrastructure\Repository;

/**
 * @implements \IteratorAggregate<int, list<int>>
 */
class IntByPackets implements \IteratorAggregate
{
    /**
     * @param list<int> $ids
     * @param int $maxItemsByRequest
     */
    public function __construct(private readonly array $ids, private readonly int $maxItemsByRequest)
    {
    }

    /**
     * @return \Traversable<int, list<int>>
     */
    public function getIterator(): \Traversable
    {
        $idsToAnalyze = $this->ids;
        do {
            $idsToReturn = [];
            foreach ($idsToAnalyze as $id) {
                if (count($idsToReturn) >= $this->maxItemsByRequest) {
                    break;
                }
                $topValue = array_shift($idsToAnalyze);
                if ($topValue !== null) {
                    $idsToReturn[] = $topValue;
                }
            }

            yield $idsToReturn;
        } while ($idsToAnalyze !== []);
    }
}
