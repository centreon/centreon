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

use Centreon\Application\DataRepresenter\Bulk;
use Centreon\Application\DataRepresenter\Listing;
use PHPUnit\Framework\TestCase;

class BulkTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $lists = [
            'mocks' => [
                'First',
                'Second',
            ],
            'drafts' => [],
        ];

        $dataRepresenter = new Bulk($lists);
        $result = $dataRepresenter->jsonSerialize();

        $this->assertArrayHasKey('mocks', $result);
        $this->assertArrayHasKey('drafts', $result);

        $this->assertInstanceOf(Listing::class, $result['mocks']);
        $this->assertInstanceOf(Listing::class, $result['drafts']);
    }
}
