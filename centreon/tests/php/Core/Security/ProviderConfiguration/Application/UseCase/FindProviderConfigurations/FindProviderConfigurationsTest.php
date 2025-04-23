<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
declare(strict_types=1);

namespace Tests\Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadProviderConfigurationsRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\{FindProviderConfigurations,
    FindProviderConfigurationsPresenterInterface,
    FindProviderConfigurationsResponse,
    ProviderConfigurationDto,
    ProviderConfigurationDtoFactoryInterface
};
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->readProviderConfigurationRepository = $this->createMock(
        ReadProviderConfigurationsRepositoryInterface::class
    );
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->readVaultConfigurationRepositoryInterface = $this->createMock(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $this->readVaultRepositoryInterface = $this->createMock(ReadVaultRepositoryInterface::class);

    $this->providerDtoFactory = $this->createMock(ProviderConfigurationDtoFactoryInterface::class);
    $this->presenter = $this->createMock(FindProviderConfigurationsPresenterInterface::class);
    $this->localConfiguration = $this->createMock(Configuration::class);
    $this->readConfigurationRepository = $this->createMock(ReadConfigurationRepositoryInterface::class);

    $this->useCase = new FindProviderConfigurations(
        new \ArrayObject([$this->providerDtoFactory]),
        $this->readConfigurationRepository
    );
});

it('returns error when there is an issue during configurations search', function (): void {
    $errorMessage = 'error during configurations search';

    $this->readConfigurationRepository
        ->expects($this->once())
        ->method('findConfigurations')
        ->willThrowException(new \Exception($errorMessage));

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse($errorMessage));

    ($this->useCase)($this->presenter);
});

it('presents an empty array when configurations are not found', function (): void {
    $this->readConfigurationRepository
        ->expects($this->once())
        ->method('findConfigurations')
        ->willReturn([]);

    $response = new FindProviderConfigurationsResponse();
    $response->providerConfigurations = [];

    $this->presenter
        ->expects($this->once())
        ->method('presentResponse')
        ->with($response);

    ($this->useCase)($this->presenter);
});

it('presents found configurations', function (): void {
    $this->readConfigurationRepository
        ->expects($this->once())
        ->method('findConfigurations')
        ->willReturn([$this->localConfiguration]);

    $this->localConfiguration
        ->expects($this->once())
        ->method('getType')
        ->willReturn('local');

    $providerConfigurationDto = new ProviderConfigurationDto();
    $providerConfigurationDto->id = 1;
    $providerConfigurationDto->type = 'local';
    $providerConfigurationDto->name = 'local';
    $providerConfigurationDto->isActive = true;
    $providerConfigurationDto->isForced = true;

    $this->providerDtoFactory
        ->expects($this->once())
        ->method('supports')
        ->with('local')
        ->willReturn(true);

    $this->providerDtoFactory
        ->expects($this->once())
        ->method('createResponse')
        ->with($this->localConfiguration)
        ->willReturn($providerConfigurationDto);

    $response = new FindProviderConfigurationsResponse();
    $response->providerConfigurations = [$providerConfigurationDto];

    $this->presenter
        ->expects($this->once())
        ->method('presentResponse')
        ->with($response);

    ($this->useCase)($this->presenter);
});
