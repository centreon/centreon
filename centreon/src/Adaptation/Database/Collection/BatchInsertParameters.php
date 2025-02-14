<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Adaptation\Database\Collection;

use Core\Common\Domain\Collection\Collection;
use Core\Common\Domain\Exception\CollectionException;

/**
 * Class
 *
 * @class   BatchInsertParameters
 * @package Adaptation\Database\Collection
 * @extends Collection<QueryParameters>
 */
class BatchInsertParameters extends Collection
{
    /**
     * Factory
     *
     * @param QueryParameters[] $batchInsertParameters
     *
     * @throws CollectionException
     * @return Collection<QueryParameters>
     */
    public static function create(array $batchInsertParameters): Collection
    {
        $batchInsertParametersCollection = new static();
        $index = 1;
        $lastLength = 0;
        foreach ($batchInsertParameters as $queryParametersCollection) {
            $batchInsertParametersCollection->validateItem($queryParametersCollection);
            if ($index > 1 && $lastLength !== $queryParametersCollection->length()) {
                throw new CollectionException('All QueryParameters must have the same length');
            }
            $batchInsertParametersCollection->add("batch_insert_param_{$index}", $queryParametersCollection);
            $index++;
            $lastLength = $queryParametersCollection->length();
        }

        return $batchInsertParametersCollection;
    }

    /**
     * @return class-string<QueryParameters>
     */
    protected function itemClass(): string
    {
        return QueryParameters::class;
    }
}
