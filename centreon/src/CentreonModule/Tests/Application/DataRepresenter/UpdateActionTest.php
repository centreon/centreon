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

namespace CentreonModule\Tests\Application\DataRepresenter;

use CentreonModule\Application\DataRepresenter\ModuleEntity;
use CentreonModule\Application\DataRepresenter\UpdateAction;
use CentreonModule\Infrastructure\Entity\Module;
use PHPUnit\Framework\TestCase;

class UpdateActionTest extends TestCase
{
    /** @var Module */
    private $entity;

    public function setUp(): void
    {
        $data = [
            'id' => '1',
            'type' => 'module',
            'name' => 'Test Module',
            'author' => 'John Doe',
            'versionCurrent' => '1.0.0',
            'version' => '1.0.1',
            'license' => [
                'required' => true,
                'expiration_date' => '2019-04-21T00:25:55-0700',
            ],
        ];

        $this->entity = new Module();
        $this->entity->setId($data['id']);
        $this->entity->setType($data['type']);
        $this->entity->setName($data['name']);
        $this->entity->setAuthor($data['author']);
        $this->entity->setVersionCurrent($data['versionCurrent']);
        $this->entity->setVersion($data['version']);
        $this->entity->setLicense($data['license']);
    }

    public function testJsonSerialize(): void
    {
        $this->entity = $this->entity;
        $message = 'OK';

        $controlResult = [
            'entity' => new ModuleEntity($this->entity),
            'message' => $message,
        ];

        $dataRepresenter = new UpdateAction($this->entity, $message);
        $result = $dataRepresenter->jsonSerialize();

        $this->assertEquals($result, $controlResult);
    }

    /**
     * @covers \CentreonModule\Application\DataRepresenter\UpdateAction::jsonSerialize
     */
    public function testJsonSerializeWithoutEntityAndMessage(): void
    {
        $controlResult = [
            'entity' => null,
            'message' => null,
        ];

        $dataRepresenter = new UpdateAction();
        $result = $dataRepresenter->jsonSerialize();

        $this->assertEquals($result, $controlResult);
    }
}
