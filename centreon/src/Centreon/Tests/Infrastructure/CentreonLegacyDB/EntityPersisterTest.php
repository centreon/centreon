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

namespace Centreon\Tests\Infrastructure\CentreonLegacyDB;

use Centreon\Infrastructure\CentreonLegacyDB\EntityPersister;
use Centreon\Infrastructure\CentreonLegacyDB\Mapping\ClassMetadata;
use Centreon\Tests\Resources\Mock\EntityMock;
use PHPUnit\Framework\TestCase;

class EntityPersisterTest extends TestCase
{
    public function testLoad(): void
    {
        $metadata = new ClassMetadata();
        EntityMock::loadMetadata($metadata);

        $metadata->add('text', 'text');
        $metadata->add('name', 'name_column', \PDO::PARAM_STR, function ($value) {
            return "{$value} with formatter";
        });

        $entity = new EntityMock();
        $entity->setId(2);
        $entity->setName('test name with formatter');

        $entityPersister = new EntityPersister(EntityMock::class, $metadata);

        $this->assertEquals($entity, $entityPersister->load([
            'id_column' => '2',
            'name_column' => 'test name',
            'description_column' => 'test description',
            'text_column' => 'test text',
        ]));
    }
}
