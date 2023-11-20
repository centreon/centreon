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

class ModuleDetailEntity implements JsonSerializable
{
    /** @var \CentreonModule\Infrastructure\Entity\Module */
    private $entity;

    /**
     * Construct.
     *
     * @param \CentreonModule\Infrastructure\Entity\Module $entity
     */
    public function __construct(Module $entity)
    {
        $this->entity = $entity;
    }

    /**
     * @OA\Schema(
     *   schema="ModuleDetailEntity",
     *
     *       @OA\Property(property="id", type="integer"),
     *       @OA\Property(property="type", type="string", enum={"module","widget"}),
     *       @OA\Property(property="title", type="string"),
     *       @OA\Property(property="description", type="string"),
     *       @OA\Property(property="label", type="string"),
     *       @OA\Property(property="stability", type="string"),
     *       @OA\Property(property="version", type="object",
     *          @OA\Property(property="current", type="string"),
     *          @OA\Property(property="available", type="string"),
     *          @OA\Property(property="outdated", type="boolean"),
     *          @OA\Property(property="installed", type="boolean")
     *       ),
     *       @OA\Property(property="is_internal", type="boolean"),
     *       @OA\Property(property="license", type="string"),
     *       @OA\Property(property="images", type="array", items={"string"}),
     *       @OA\Property(property="last_update", type="string"),
     *       @OA\Property(property="release_note", type="string")
     * )
     *
     * JSON serialization of entity
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): mixed
    {
        $outdated = ! $this->entity->isInternal() && $this->entity->isInstalled() && ! $this->entity->isUpdated();

        return [
            'id' => $this->entity->getId(),
            'type' => $this->entity->getType(),
            'title' => $this->entity->getName(),
            'description' => $this->entity->getDescription(),
            'label' => $this->entity->getAuthor(),
            'stability' => $this->entity->getStability(),
            'version' => [
                'current' => $this->entity->getVersionCurrent(),
                'available' => $this->entity->getVersion(),
                'outdated' => $outdated,
                'installed' => $this->entity->isInstalled(),
            ],
            'is_internal' => $this->entity->isInternal(),
            'license' => $this->entity->getLicense(),
            'images' => $this->entity->getImages(),
            'last_update' => $this->entity->getLastUpdate(),
            'release_note' => $this->entity->getReleaseNote(),
        ];
    }
}
