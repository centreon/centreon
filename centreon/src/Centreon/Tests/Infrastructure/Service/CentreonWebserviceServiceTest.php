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

namespace Centreon\Tests\Infrastructure\Service;

use Centreon\Infrastructure\Service\CentreonWebserviceService;
use Centreon\Infrastructure\Service\Exception\NotFoundException;
use Centreon\Tests\Resources\Mock\WebserviceMock;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class CentreonWebserviceServiceTest extends TestCase
{
    public function testAdd(): void
    {
        $service = new CentreonWebserviceService();
        $this->assertInstanceOf(ContainerInterface::class, $service);

        // check if return this object and add webservice
        $this->assertInstanceOf(CentreonWebserviceService::class, $service->add(WebserviceMock::class));

        $serviceId = strtolower(WebserviceMock::getName());

        // check is webservice is added
        $this->assertSame(WebserviceMock::class, $service->get($serviceId));
    }

    public function testAddWithoutInterface(): void
    {
        $service = new CentreonWebserviceService();
        $this->assertInstanceOf(ContainerInterface::class, $service);

        $this->expectException(NotFoundException::class);

        $service->add(\stdClass::class);
    }

    public function testAll(): void
    {
        $service = new CentreonWebserviceService();
        $service->add(WebserviceMock::class);

        $serviceId = strtolower(WebserviceMock::getName());

        // check is webservice is added
        $this->assertEquals([
            $serviceId => WebserviceMock::class,
        ], $service->all());
    }

    public function testHas(): void
    {
        $service = new CentreonWebserviceService();
        $service->add(WebserviceMock::class);

        $serviceId = strtolower(WebserviceMock::getName());

        $this->assertTrue($service->has($serviceId));
        $this->assertTrue($service->has(ucfirst($serviceId)));
        $this->assertFalse($service->has('non-exists'));
    }

    public function testGet(): void
    {
        $service = new CentreonWebserviceService();
        $service->add(WebserviceMock::class);

        $this->assertEquals(WebserviceMock::class, $service->get(WebserviceMock::getName()));
    }

    public function testGetWithNonExistsId(): void
    {
        $service = new CentreonWebserviceService();

        $this->expectException(NotFoundException::class);

        $this->assertEquals(WebserviceMock::class, $service->get(WebserviceMock::getName()));
    }
}
