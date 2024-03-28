<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace CentreonModule\Application\DataRepresenter;

use CentreonModule\Infrastructure\Entity\Module;
use JsonSerializable;

class UpdateAction implements JsonSerializable
{
    /** @var Module */
    private $entity;

    /** @var string|null */
    private $message;

    /**
     * Construct.
     *
     * @param Module $entity
     * @param Module|null $entity
     * @param string $message
     */
    public function __construct(?Module $entity = null, ?string $message = null)
    {
        $this->entity = $entity;
        $this->message = $message;
    }

    /**
     * @OA\Schema(
     *   schema="UpdateAction",
     *
     *       @OA\Property(property="entity", ref="#/components/schemas/ModuleEntity"),
     *       @OA\Property(property="message", type="string")
     * )
     *
     * JSON serialization of entity
     *
     * @return array{entity:ModuleEntity|string|null, message:string|null}
     */
    public function jsonSerialize(): mixed
    {
        $entity = null;

        if ($this->entity !== null) {
            $entity = new ModuleEntity($this->entity);
        }

        return [
            'entity' => $entity,
            'message' => $this->message,
        ];
    }
}
