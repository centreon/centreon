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

/**
 * @OA\Schema(
 *   schema="Pagination",
 *   allOf={
 *     @OA\Schema(
 *       @OA\Property(property="total", type="integer"),
 *       @OA\Property(property="offset", type="integer"),
 *       @OA\Property(property="limit", type="integer")
 *     )
 *   }
 * )
 */
class Listing implements JsonSerializable
{
    /** @var array */
    private $entities;

    /** @var int|null */
    private $offset;

    /** @var int */
    private $limit;

    /** @var int */
    private $total;

    /** @var string */
    private $entityClass;

    /**
     * Construct
     *
     * @param mixed $entities
     * @param string $entityClass Entity JSON wrap class
     * @param int $total
     * @param int $offset
     * @param int $limit
     * @param string $entityClass
     */
    public function __construct(
        $entities,
        ?int $total = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $entityClass = null
    ) {
        $this->entities = $entities ?? [];
        $this->total = $total ?: count($this->entities);
        $this->offset = $offset;
        $this->limit = $limit ?? $this->total;
        $this->entityClass = $entityClass ?? Entity::class;
    }

    /**
     * JSON serialization of list
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): mixed
    {
        $result = [
            'pagination' => [
                'total' => $this->total,
                'offset' => $this->offset ?? 0,
                'limit' => $this->limit,
            ],
            'entities' => [],
        ];

        foreach ($this->entities as $entity) {
            $result['entities'][] = new $this->entityClass($entity);
        }

        return $result;
    }
}
