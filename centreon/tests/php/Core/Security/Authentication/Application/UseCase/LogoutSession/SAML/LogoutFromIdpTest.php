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

declare(strict_types=1);

namespace Tests\Core\Security\Authentication\Application\UseCase\LogoutSession\SAML;

use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\UseCase\LogoutSession\SAML\LogoutFromIdp;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LogoutFromIdpTest extends TestCase
{
    /** @var ProviderAuthenticationFactoryInterface&MockObject */
    private $providerFactory;

    protected function setUp(): void
    {
        $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
    }

    public function testProcessesCallbackWithoutInitiatingNewLogout(): void
    {
        $provider = $this->createProvider(isActive: true);
        $provider->expects($this->once())->method('handleCallbackLogoutResponse');
        $provider->expects($this->never())->method('logout');

        $this->providerFactory->method('create')->with(Provider::SAML)->willReturn($provider);

        (new LogoutFromIdp($this->providerFactory))();
    }

    public function testDoesNothingWhenProviderIsInactive(): void
    {
        $provider = $this->createProvider(isActive: false);
        $provider->expects($this->never())->method('handleCallbackLogoutResponse');
        $provider->expects($this->never())->method('logout');

        $this->providerFactory->method('create')->with(Provider::SAML)->willReturn($provider);

        (new LogoutFromIdp($this->providerFactory))();
    }

    /**
     * @return SAML&MockObject
     */
    private function createProvider(bool $isActive): SAML
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('isActive')->willReturn($isActive);

        $provider = $this->createMock(SAML::class);
        $provider->method('getConfiguration')->willReturn($configuration);

        return $provider;
    }
}
