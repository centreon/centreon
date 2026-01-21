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

use Centreon\Application\Webservice\CentreonI18n;
use Centreon\Domain\Service\I18nService;
use Centreon\ServiceProvider;
use Centreon\Tests\Resources\Traits;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * @group Centreon
 * @group Webservice
 */
class CentreonI18nTest extends TestCase
{
    use Traits\WebServiceAuthorizePublicTrait;

    /** @var Container */
    public $container;

    /** @var CentreonI18n|(CentreonI18n&object&\PHPUnit\Framework\MockObject\MockObject)|(CentreonI18n&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject */
    public $webservice;

    protected function setUp(): void
    {
        // dependencies
        $this->container = new Container([
            ServiceProvider::CENTREON_I18N_SERVICE => $this->createMock(I18nService::class),
        ]);

        $this->webservice = $this->createPartialMock(CentreonI18n::class, [
            'loadDb',
            'loadArguments',
            'loadToken',
            'query',
        ]);

        // load dependencies
        $this->webservice->setDi($this->container);
    }

    public function testGetName(): void
    {
        $this->assertEquals('centreon_i18n', CentreonI18n::getName());
    }

    public function testGetTranslation(): void
    {
        $value = ['test OK'];
        $this->container->offsetGet(ServiceProvider::CENTREON_I18N_SERVICE)
            ->method('getTranslation')
            ->willReturn($value);

        $this->assertEquals($value, $this->webservice->getTranslation());
    }

    public function testGetTranslationWithException(): void
    {
        $this->container->offsetGet(ServiceProvider::CENTREON_I18N_SERVICE)
            ->method('getTranslation')
            ->will($this->returnCallback(function (): void {
                throw new \Exception('');
            }));

        $this->expectException(\Exception::class);

        $this->webservice->getTranslation();
    }
}
