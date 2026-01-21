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

namespace Centreon\Tests\Application\Webservice;

use Centreon\Application\Webservice\CentreonFrontendComponent;
use Centreon\Domain\Service\FrontendComponentService;
use Centreon\ServiceProvider;
use Centreon\Tests\Resources\Traits;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * @group Centreon
 * @group Webservice
 */
class CentreonFrontendComponentTest extends TestCase
{
    use Traits\WebServiceAuthorizePublicTrait;

    /** @var CentreonFrontendComponent|(CentreonFrontendComponent&object&MockObject)|(CentreonFrontendComponent&MockObject)|(object&MockObject)|MockObject */
    public $webservice;

    /**
     * Control value for the method getComponents
     *
     * @var array
     */
    protected $getComponentsValues = [
        'pages' => ['list of pages'],
        'hooks' => ['list of hooks'],
    ];

    protected function setUp(): void
    {
        // dependencies
        $container = new Container();
        $container[ServiceProvider::CENTREON_FRONTEND_COMPONENT_SERVICE]
            = $this->createMock(FrontendComponentService::class);

        (function (FrontendComponentService&MockObject $service): void {
            $service
                ->method('getPages')
                ->willReturn($this->getComponentsValues['pages']);
            $service
                ->method('getHooks')
                ->willReturn($this->getComponentsValues['hooks']);
        })($container[ServiceProvider::CENTREON_FRONTEND_COMPONENT_SERVICE]);

        $this->webservice = $this->createPartialMock(CentreonFrontendComponent::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
            'query',
        ]);

        // load dependencies
        $this->webservice->setDi($container);
    }

    public function testGetComponents(): void
    {
        $this->webservice
            ->method('query')
            ->will($this->returnCallback(function () use (&$filters) {
                return $filters;
            }));

        $this->assertEquals($this->getComponentsValues, $this->webservice->getComponents());
    }

    public function testGetName(): void
    {
        $this->assertEquals('centreon_frontend_component', CentreonFrontendComponent::getName());
    }
}
