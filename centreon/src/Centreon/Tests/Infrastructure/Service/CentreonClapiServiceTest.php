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

use Centreon\Infrastructure\Service\CentreonClapiService;
use Centreon\Infrastructure\Service\Exception\NotFoundException;
use Centreon\Tests\Resources\Mock\ClapiMock;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class CentreonClapiServiceTest extends TestCase
{
    public function testAdd(): void
    {
        $service = new CentreonClapiService();
        $this->assertInstanceOf(ContainerInterface::class, $service);

        // check if return this object and add webservice
        $this->assertInstanceOf(CentreonClapiService::class, $service->add(ClapiMock::class));

        $serviceId = strtolower(ClapiMock::getName());

        // check is webservice is added
        $this->assertSame(ClapiMock::class, $service->get($serviceId));
    }

    public function testAddWithoutInterface(): void
    {
        $service = new CentreonClapiService();
        $this->assertInstanceOf(ContainerInterface::class, $service);

        $this->expectException(NotFoundException::class);

        $service->add(\stdClass::class);
    }
}
