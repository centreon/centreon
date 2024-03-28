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
 * This class is used to split a 'GET' API call into multiple calls, due to URL size limitations.
 * The main cause of this division is the size of parameter lengths.
 *
 * @template T of int|string
 *
 * @implements \IteratorAggregate<int, list<T>>
 */
class ValuesByPackets implements \IteratorAggregate
{
    /**
     * @param list<T> $values
     * @param int $maxItemsByRequest
     * @param int $maxQueryStringLength
     * @param int $separatorLength
     */
    public function __construct(
        private readonly array $values,
        private readonly int $maxItemsByRequest,
        private readonly int $maxQueryStringLength,
        private readonly int $separatorLength = 1,
    ) {
    }

    /**
     * Returns the list of parameters that can be used for an API call, according to the limitations defined in the
     * $maxItemsByRequest and $maxQueryStringLength properties.
     *
     * @return \Traversable<int, list<T>>
     */
    public function getIterator(): \Traversable
    {
        $valuesToAnalyze = $this->values;
        do {
            $valuesLength = 0;
            $valuesToReturn = [];

            foreach ($valuesToAnalyze as $value) {
                $currentValueLength = mb_strlen((string) $value);
                if ($valuesLength !== 0) {
                    $currentValueLength += $this->separatorLength;
                }
                if (
                    count($valuesToReturn) >= $this->maxItemsByRequest
                    || ($currentValueLength + $valuesLength) > $this->maxQueryStringLength
                ) {
                    break;
                }
                $valuesLength += $currentValueLength;
                $firstValue = array_shift($valuesToAnalyze);
                if ($firstValue !== null) {
                    $valuesToReturn[] = $firstValue;
                }
            }

            yield $valuesToReturn;
        } while ($valuesToAnalyze !== []);
    }
}
