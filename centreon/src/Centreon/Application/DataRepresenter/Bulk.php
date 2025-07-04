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

namespace Centreon\Application\DataRepresenter;

use JsonSerializable;

class Bulk implements JsonSerializable
{
    /** @var array */
    private $lists;

    /** @var int */
    private $offset;

    /** @var int */
    private $limit;

    /** @var string */
    private $listingClass;

    /** @var string */
    private $entityClass;

    /**
     * Construct
     *
     * @param array $lists
     * @param string $listingClass
     * @param string $entityClass
     */
    public function __construct(
        array $lists,
        ?int $offset = null,
        ?int $limit = null,
        ?string $listingClass = null,
        ?string $entityClass = null
    ) {
        $this->lists = $lists;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->listingClass = $listingClass ?? Listing::class;
        $this->entityClass = $entityClass ?? Entity::class;
    }

    /**
     * JSON serialization of several lists
     *
     * @return mixed[]
     */
    public function jsonSerialize(): mixed
    {
        $result = [];

        foreach ($this->lists as $name => $entities) {
            $result[$name] = new $this->listingClass(
                $entities,
                null,
                $this->offset,
                $this->limit,
                $this->entityClass
            );
        }

        return $result;
    }
}
