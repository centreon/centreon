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

use CentreonModule\Application\DataRepresenter\ModuleEntity;
use CentreonModule\Infrastructure\Entity\Module;
use PHPUnit\Framework\TestCase;

class ModuleEntityTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $data = [
            'id' => '1',
            'type' => 'module',
            'name' => 'Test Module',
            'author' => 'John Doe',
            'versionCurrent' => '1.0.0',
            'version' => '1.0.1',
            'is_internal' => false,
            'license' => [
                'required' => true,
                'expiration_date' => '2019-04-21T00:25:55-0700',
            ],
        ];

        $entity = new Module;
        $entity->setId($data['id']);
        $entity->setType($data['type']);
        $entity->setName($data['name']);
        $entity->setAuthor($data['author']);
        $entity->setVersionCurrent($data['versionCurrent']);
        $entity->setVersion($data['version']);
        $entity->setInternal($data['is_internal']);
        $entity->setLicense($data['license']);

        $check = function () use ($entity): void {
            $outdated = $entity->isInstalled() && ! $entity->isUpdated()
                ? true
                : false;

            $controlResult = [
                'id' => $entity->getId(),
                'type' => $entity->getType(),
                'description' => $entity->getName(),
                'label' => $entity->getAuthor(),
                'version' => [
                    'current' => $entity->getVersionCurrent(),
                    'available' => $entity->getVersion(),
                    'outdated' => $outdated,
                    'installed' => $entity->isInstalled(),
                ],
                'is_internal' => $entity->isInternal(),
                'license' => $entity->getLicense(),
            ];

            $dataRepresenter = new ModuleEntity($entity);
            $result = $dataRepresenter->jsonSerialize();

            $this->assertEquals($result, $controlResult);
        };

        $check();

        $entity->setInstalled(true);
        $check();
    }
}
