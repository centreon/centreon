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

namespace Centreon\Tests\Application\DataRepresenter;

use Centreon\Application\DataRepresenter\Entity;
use Centreon\Application\DataRepresenter\Listing;
use PHPUnit\Framework\TestCase;

class ListingTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $data = [
            1, 2, 3, 4, 5, 6,
        ];

        $dataRepresenter = new Listing($data, null, 1, 2);
        $result = $dataRepresenter->jsonSerialize();

        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('total', $result['pagination']);
        $this->assertArrayHasKey('offset', $result['pagination']);
        $this->assertArrayHasKey('limit', $result['pagination']);

        $this->assertArrayHasKey('entities', $result);
        $this->assertEquals(6, $result['pagination']['total']);
        $this->assertEquals(1, $result['pagination']['offset']);
        $this->assertEquals(2, $result['pagination']['limit']);

        $this->assertInstanceOf(Entity::class, current($result['entities']));
    }
}
