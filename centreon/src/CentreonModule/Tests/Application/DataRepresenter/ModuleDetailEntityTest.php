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

namespace CentreonModule\Tests\Application\DataRepresenter;

use CentreonModule\Application\DataRepresenter\ModuleDetailEntity;
use CentreonModule\Infrastructure\Entity\Module;
use PHPUnit\Framework\TestCase;

class ModuleDetailEntityTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $data = [
            'id' => '1',
            'type' => 'module',
            'name' => 'Test Module',
            'description' => 'Test Module description',
            'author' => 'John Doe',
            'versionCurrent' => '1.0.0',
            'version' => '1.0.1',
            'is_internal' => false,
            'license' => [
                'required' => true,
                'expiration_date' => '2019-04-21T00:25:55-0700',
            ],
            'image' => 'media/screanshot.png',
            'stability' => 'beta',
            'last_update' => '2000-01-01',
            'release_note' => 'http://localhost/',
        ];

        $entity = new Module;
        $entity->setId($data['id']);
        $entity->setType($data['type']);
        $entity->setName($data['name']);
        $entity->setDescription($data['name']);
        $entity->setAuthor($data['author']);
        $entity->setVersionCurrent($data['versionCurrent']);
        $entity->setVersion($data['version']);
        $entity->setInternal($data['is_internal']);
        $entity->setLicense($data['license']);
        $entity->addImage($data['image']);
        $entity->setStability($data['stability']);
        $entity->setLastUpdate($data['last_update']);
        $entity->setReleaseNote($data['release_note']);

        $check = function () use ($entity): void {
            $outdated = $entity->isInstalled() && ! $entity->isUpdated()
                ? true
                : false;

            $controlResult = [
                'id' => $entity->getId(),
                'type' => $entity->getType(),
                'title' => $entity->getName(),
                'description' => $entity->getDescription(),
                'label' => $entity->getAuthor(),
                'version' => [
                    'current' => $entity->getVersionCurrent(),
                    'available' => $entity->getVersion(),
                    'outdated' => $outdated,
                    'installed' => $entity->isInstalled(),
                ],
                'is_internal' => $entity->isInternal(),
                'license' => $entity->getLicense(),
                'images' => $entity->getImages(),
                'stability' => $entity->getStability(),
                'last_update' => $entity->getLastUpdate(),
                'release_note' => $entity->getReleaseNote(),
            ];

            $dataRepresenter = new ModuleDetailEntity($entity);
            $result = $dataRepresenter->jsonSerialize();

            $this->assertEquals($result, $controlResult);
        };

        $check();

        $entity->setInstalled(true);
        $check();
    }
}
