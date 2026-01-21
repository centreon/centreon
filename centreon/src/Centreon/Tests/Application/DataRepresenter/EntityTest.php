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
use PHPUnit\Framework\TestCase;

class EntityTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $data = 'OK';

        $dataRepresenter = new Entity($data);
        $result = $dataRepresenter->jsonSerialize();

        $this->assertEquals([$data], $result);
    }

    public function testJsonSerializeWithObject(): void
    {
        $value = [
            'prop1' => true,
            'prop2' => null,
            'prop3' => 'OK',
        ];

        $data = new class () {
            public $prop1 = true;

            protected $prop2;

            private $prop3 = 'OK';
        };

        $dataRepresenter = new Entity($data);
        $result = $dataRepresenter->jsonSerialize();

        $this->assertEquals($value, $result);
    }
}
