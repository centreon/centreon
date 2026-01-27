<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Tests\App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Provider\OpenId\AbsoluteUrl;
use App\Security\Domain\Aggregate\Provider\OpenId\AuthenticationTypeEnum;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientId;
use App\Security\Domain\Aggregate\Provider\OpenId\ClientSecret;
use App\Security\Domain\Aggregate\Provider\OpenId\LoginClaim;
use App\Security\Domain\Aggregate\Provider\OpenId\OpenIdConfiguration;
use App\Security\Domain\Aggregate\Provider\OpenId\Url;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use App\Security\Infrastructure\Dbal\DbalOpenIdProviderRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOpenIdProviderRepositoryTest extends KernelTestCase
{
    private DbalOpenIdProviderRepository $repository;

    private OpenIdConfiguration $configuration;

    protected function setUp(): void
    {
        /** @var DbalOpenIdProviderRepository $repository */
        $repository = self::getContainer()->get(DbalOpenIdProviderRepository::class);

        $this->repository = $repository;

        $this->configuration = new OpenIdConfiguration(
            baseUrl: new AbsoluteUrl('https://default.example.com'),
            redirectUrl: null,
            clientId: new ClientId('default-client-id'),
            clientSecret: new ClientSecret('default-secret'),
            loginClaim: new LoginClaim('sub'),
            tokenEndpoint: new Url('https://default.example.com/token'),
            userInfoEndpoint: null,
            authenticationType: AuthenticationTypeEnum::ClientSecretPost,
            endSessionEndpoint: null,
            authorizationEndpoint: new Url('https://default.example.com/authorize'),
            introspectionTokenEndpoint: null,
            connectionScopes: [],
            shouldVerifyPeer: true,
            isActive: false,
            isForced: false,
        );
    }

    public function testGetConfigurationReturnsOpenIdConfiguration(): void
    {
        $this->repository->update($this->configuration);
        $configuration = $this->repository->getConfiguration();
        self::assertSame($this->configuration->baseUrl->value, $configuration->baseUrl->value);
    }

    public function testGetConfigurationThrowsExceptionIfNotFound(): void
    {
        $this->repository->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider Configuration with token IDP ' . TokenIdpEnum::OpenId->value . ' does not exist.');
        $this->repository->getConfiguration();
    }
}
