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

namespace CentreonModule\Application\DataRepresenter;

use CentreonModule\Infrastructure\Entity\Module;
use JsonSerializable;

class UpdateAction implements JsonSerializable
{
    /**
     * Construct.
     *
     * @param Module $entity
     * @param Module|null $entity
     * @param string $message
     */
    public function __construct(private ?Module $entity = null, private ?string $message = null)
    {
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
