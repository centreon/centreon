<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Tests\Application\Webservice;

use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Centreon\ServiceProvider;
use Centreon\Application\Webservice\ImagesWebservice;
use Centreon\Tests\Resources\Mock\CentreonPaginationServiceMock;
use Centreon\Tests\Resources\Traits;

/**
 * @group Centreon
 * @group Webservice
 */
class ImagesWebserviceTest extends TestCase
{
    use Traits\WebServiceAuthorizeRestApiTrait;
    use Traits\WebServiceExecuteTestTrait;

    public const METHOD_GET_LIST = 'getList';

    /** @var ImagesWebservice|(ImagesWebservice&object&\PHPUnit\Framework\MockObject\MockObject)|(ImagesWebservice&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject */
    public $webservice;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        // dependencies
        $container = new Container;
        $container[ServiceProvider::CENTREON_PAGINATION] = new CentreonPaginationServiceMock;

        $this->webservice = $this->createPartialMock(ImagesWebservice::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
            'query',
        ]);

        // load dependencies
        $this->webservice->setDi($container);
        $this->fixturePath = __DIR__ . '/../../Resources/Fixture/Images/';
    }

    /**
     * Test the method getList
     */
    public function testGetList(): void
    {
        // without applied filters
        $this->mockQuery();
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-1.json');
    }

    /**
     * Test different case of method getList with filters
     */
    public function testGetList2(): void
    {
        // with search, searchByIds, limit, and offset
        $this->mockQuery([
            'search' => 'test',
            'searchByIds' => '3,5,7',
            'limit' => '1a',
            'offset' => '2b',
        ]);
        $this->executeTest(static::METHOD_GET_LIST, 'response-list-2.json');
    }

    /**
     * Test the method getName
     */
    public function testGetName(): void
    {
        $this->assertEquals('centreon_images', ImagesWebservice::getName());
    }
}
