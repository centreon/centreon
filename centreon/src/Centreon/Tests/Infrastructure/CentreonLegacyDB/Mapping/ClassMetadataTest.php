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

namespace Centreon\Tests\Infrastructure\CentreonLegacyDB\Mapping;

use Centreon\Infrastructure\CentreonLegacyDB\Mapping\ClassMetadata;
use Centreon\Tests\Resources\Mock\EntityMock;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Class
 *
 * @class ClassMetadataTest
 * @package Centreon\Tests\Infrastructure\CentreonLegacyDB\Mapping
 */
class ClassMetadataTest extends TestCase
{
    /** @var ClassMetadata */
    public $metadata;

    public function setUp(): void
    {
        $this->metadata = new ClassMetadata();
        EntityMock::loadMetadata($this->metadata);
    }

    public function testGetTableName(): void
    {
        $this->assertEquals('mock_table', $this->metadata->getTableName());
    }

    public function testGetPrimaryKey(): void
    {
        $this->assertEquals('id', $this->metadata->getPrimaryKey());
    }

    public function testGetPrimaryKeyColumn(): void
    {
        $this->assertEquals('id_column', $this->metadata->getPrimaryKeyColumn());
    }

    public function testGetType(): void
    {
        $this->assertEquals(PDO::PARAM_INT, $this->metadata->getType('id'));
    }

    public function testGet(): void
    {
        $this->assertEquals([
            ClassMetadata::COLUMN => 'name_column',
            ClassMetadata::TYPE => PDO::PARAM_STR,
            ClassMetadata::FORMATTER => null,
        ], $this->metadata->get('name'));
    }
}
