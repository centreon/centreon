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

namespace Centreon\Tests\Infrastructure\Webservice;

use Centreon\Infrastructure\Webservice\WebServiceAbstract;
use PHPUnit\Framework\TestCase;

class WebServiceAbstractTest extends TestCase
{
    /** @var WebServiceAbstract|\PHPUnit\Framework\MockObject\MockObject */
    private $webservice;

    public function setUp(): void
    {
        $this->webservice = $this->getMockBuilder(WebServiceAbstract::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getName',
            ])
            ->getMockForAbstractClass();
    }

    public function testQuery(): void
    {
        $_GET = [
            'test1' => '1',
            'test2' => '2',
        ];

        $this->assertEquals($_GET, $this->webservice->query());
    }

    public function testPayloadRaw(): void
    {
        $this->assertEquals('', $this->webservice->payloadRaw());
    }

    public function testPayload(): void
    {
        $webservice = $this->getMockBuilder(WebServiceAbstract::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'payloadRaw',
            ])
            ->getMockForAbstractClass();

        $webservice
            ->method('payloadRaw')
            ->will($this->returnValue('{"id":"1"}'));

        $this->assertEquals([
            'id' => '1',
        ], $webservice->payload());
    }

    public function testPayloadWithException(): void
    {
        $webservice = $this->getMockBuilder(WebServiceAbstract::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'payloadRaw',
            ])
            ->getMockForAbstractClass();

        $webservice
            ->method('payloadRaw')
            ->will($this->returnValue('{id":"1"}'));

        $this->assertEquals([], $webservice->payload());
    }
}
